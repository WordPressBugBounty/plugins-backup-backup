<?php

namespace BMI\Plugin\Database\Export;

require_once __DIR__ . '/interface-database-export-repository.php';
require_once __DIR__ . '/class-database-export-repository.php';
require_once __DIR__ . '/class-database-export-engine.php';
require_once BMI_INCLUDES . '/progress/class-file-progress-storage.php';

use BMI\Plugin\BMI_Logger as Logger;
use BMI\Plugin\Dashboard as Dashboard;
use BMI\Plugin\Staging\BMI_Staging as Staging;
use BMI\Plugin\Progress\ProgressStorageInterface;
use BMI\Plugin\Progress\FileProgressStorage;

/**
 * Class DatabaseExportProcessor
 *
 * Orchestrates database export across all tables.
 * Handles table discovery, exclusion rules, staging site filtering, progress tracking,
 * and logging. Delegates per-table export to DatabaseExportEngine.
 *
 * Architecture:
 *
 *   DatabaseExportProcessor             (Orchestrator: tables loop, logging, progress)
 *       └── DatabaseExportEngine        (Engine: single-table batching, pagination, streaming)
 *               ├── DatabaseExportRepository    (Data: SQL queries, connections, escape)
 *               └── FileProgressStorage      (State: file-based JSON persistence)
 *
 * Usage:
 *   $processor = new DatabaseExportProcessor($outputDir, $logger);
 *   do {
 *       $result = $processor->exportBatch();
 *   } while ($result['status'] !== 'completed');
 *   $files = $result['files'];
 *
 * Or for non-batched (all at once):
 *   $result = $processor->exportAll();
 *   $files = $result['files'];
 */
class DatabaseExportProcessor
{
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    /**
     * @var string The output directory for SQL files.
     */
    private $outputDir;

    /**
     * @var object The logger instance.
     */
    private $logger;

    /**
     * @var DatabaseExportRepositoryInterface The shared repository instance.
     */
    private $repository;

    /**
     * @var ProgressStorageInterface The master state storage.
     */
    private $stateStorage;

    /**
     * @var callable|null Optional factory for creating engine instances (for testing).
     */
    private $engineFactory;

    /**
     * @var string The time-based prefix for temporary table names.
     */
    private $tablePrefix;

    /**
     * @var bool Whether debug mode is enabled.
     */
    private $debugEnabled;

    /**
     * Constructor.
     *
     * @param string $outputDir Directory to write SQL files.
     * @param object $logger Logger instance.
     * @param string|int|null $tablePrefix Optional time prefix. Defaults to current timestamp.
     * @param DatabaseExportRepositoryInterface|null $repository Optional repository.
     * @param ProgressStorageInterface|null $stateStorage Optional master state storage.
     * @param callable|null $engineFactory Optional factory: fn(string $table) => DatabaseExportEngine
     * @param bool|null $debugEnabled Optional debug mode flag. Defaults to false.
     */
    public function __construct(
        $outputDir,
        $logger,
        $tablePrefix = null,
        $repository = null,
        $stateStorage = null,
        $engineFactory = null,
        $debugEnabled = null
    ) {
        $this->outputDir = rtrim($outputDir, DIRECTORY_SEPARATOR);
        $this->logger = $logger;
        $this->repository = $repository !== null ? $repository : new DatabaseExportRepository();
        $this->stateStorage = $stateStorage !== null ? $stateStorage : new FileProgressStorage(
            null,
            'db-export-master-state.json'
        );
        $this->engineFactory = $engineFactory;
        $this->tablePrefix = $tablePrefix !== null ? (string) $tablePrefix : (string) time();
        $this->debugEnabled = $debugEnabled !== null ? (bool) $debugEnabled : $this->isDebugMode();
    }

    /**
     * Runs the entire export in one call (non-batched mode).
     *
     * Iterates all tables and exports them completely.
     *
     * @return array{status: string, files: array, total_tables: int, total_rows: int, total_queries: int, total_size_mb: float}
     */
    public function exportAll()
    {
        $this->logger->log('Starting full database export (non-batched mode)', 'STEP');
        $startTime = microtime(true);

        // Discover tables
        $tables = $this->discoverTables();
        $this->logger->log(
            sprintf('Found %d tables to export', count($tables)),
            'INFO'
        );

        $files = [];
        $totalRows = 0;
        $totalQueries = 0;
        $totalSizeMb = 0;

        foreach ($tables as $index => $tableData) {
            $tableName = $tableData['name'];
            $this->logger->log(
                sprintf(
                    'Exporting table: %s (%d/%d, %.2f MB)',
                    $tableName,
                    $index + 1,
                    count($tables),
                    $tableData['size']
                ),
                'STEP'
            );

            $engine = $this->createEngine($tableName);

            // Export all batches for this table
            try {
                do {
                    $result = $engine->exportBatch();
                } while ($result['status'] !== DatabaseExportEngine::STATUS_COMPLETED);
            } catch (\RuntimeException $e) {
                $this->logger->log(
                    sprintf('Error exporting table %s: %s', $tableName, $e->getMessage()),
                    'ERROR'
                );
                $this->reset();
                throw $e;
            }

            $files[] = $result['output_file'];
            $totalRows += $result['rows_exported'];
            $totalQueries += max(1, $result['batch']);
            $totalSizeMb += $tableData['size'];

            $this->logger->log(
                sprintf(
                    'Table %s exported: %d rows',
                    $tableName,
                    $result['rows_exported']
                ),
                'SUCCESS'
            );
        }

        $elapsed = number_format(microtime(true) - $startTime, 4);
        $this->logger->log(sprintf('Full database export completed in %ss', $elapsed), 'SUCCESS');

        // Clear master state
        $this->stateStorage->clear();

        return [
            'status' => self::STATUS_COMPLETED,
            'files' => $files,
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
            'total_queries' => $totalQueries,
            'total_size_mb' => $totalSizeMb,
        ];
    }

    /**
     * Runs one batch of the export (batched mode).
     *
     * Call this repeatedly until the returned status is 'completed'.
     * State is persisted to disk between calls.
     *
     * @return array{status: string, files: array, current_table_index: int, total_tables: int, table_name: string}
     */
    public function exportBatch()
    {
        $masterState = $this->getOrInitMasterState();

        $tables = $masterState['tables'];
        $tableIndex = $masterState['current_table_index'];

        // All tables done
        if ($tableIndex >= count($tables)) {
            return $this->completeMasterExport($masterState);
        }

        $tableData = $tables[$tableIndex];
        $tableName = $tableData['name'];

        // Log on first batch of a table
        if (!isset($masterState['table_started']) || !$masterState['table_started']) {
            $this->logger->log(
                sprintf(
                    'Exporting table: %s (%d/%d, %.2f MB, %d rows)',
                    $tableName,
                    $tableIndex + 1,
                    count($tables),
                    $tableData['size'],
                    $tableData['rows']
                ),
                'STEP'
            );
            $masterState['table_started'] = true;
        }

        $engine = $this->createEngine($tableName);

        try {
            $result = $engine->exportBatch();
        } catch (\RuntimeException $e) {
            $this->logger->log(
                sprintf('Error exporting table %s: %s', $tableName, $e->getMessage()),
                'ERROR'
            );
            $this->reset();
            throw $e;
        }

        if ($result['status'] === DatabaseExportEngine::STATUS_COMPLETED) {
            // Table finished — record file and move to next
            if (!in_array($result['output_file'], $masterState['files'])) {
                $masterState['files'][] = $result['output_file'];
            }
            $masterState['total_rows'] += $result['rows_exported'];

            $this->logger->log(
                sprintf('Table %s exported: %d rows', $tableName, $result['rows_exported']),
                'SUCCESS'
            );

            $masterState['current_table_index']++;
            $masterState['table_started'] = false;

            // Check if all tables done
            if ($masterState['current_table_index'] >= count($tables)) {
                return $this->completeMasterExport($masterState);
            }
        }

        $this->stateStorage->save($masterState);

        return [
            'status' => self::STATUS_IN_PROGRESS,
            'files' => $masterState['files'],
            'current_table_index' => $masterState['current_table_index'],
            'total_tables' => count($tables),
            'total_rows' => $masterState['total_rows'],
            'table_name' => $tableName,
        ];
    }

    /**
     * Gets or initializes the master export state.
     *
     * On first call, discovers tables and caches the list in the state file.
     * On subsequent calls, loads from file (skipping re-discovery).
     *
     * @return array The master state.
     */
    private function getOrInitMasterState()
    {
        $stored = $this->stateStorage->load();
        if ($stored !== null) {
            return $stored;
        }

        $tables = $this->discoverTables();

        $this->logger->log(
            sprintf(
                'Scan found %d tables, estimated total size: %.2f MB',
                count($tables),
                array_sum(array_column($tables, 'size'))
            ),
            'INFO'
        );

        if ($this->debugEnabled){
            $this->logger->log(
                'Memory usage: ' . number_format(memory_get_usage() / 1024 / 1024, 2) . ' MB',
                'INFO'
            );
        }

        $state = [
            'status' => self::STATUS_IN_PROGRESS,
            'time_start' => microtime(true),
            'table_prefix' => $this->tablePrefix,
            'tables' => $tables,
            'current_table_index' => 0,
            'table_started' => false,
            'files' => [],
            'total_rows' => 0,
        ];

        $this->stateStorage->save($state);
        return $state;
    }

    /**
     * Discovers all tables to export, applying exclusion rules.
     *
     * Filters out:
     * - Tables in the user's exclusion list
     * - Tables belonging to staging sites
     *
     * @return array List of table data arrays: [{name, size, rows}, ...]
     */
    private function discoverTables()
    {
        $allTables = $this->repository->getTableNames();
        $excludedTables = $this->getExcludedTableNames();
        $stagingPrefixes = $this->getStagingTablePrefixes();

        $result = [];

        foreach ($allTables as $tableName) {
            // Check user exclusion list
            if ($this->isTableExcluded($tableName, $excludedTables)) {
                $this->logger->log(
                    sprintf('Excluding %s table from backup (due to exclusion rules).', $tableName),
                    'INFO'
                );
                continue;
            }

            // Check staging site tables
            if ($this->isTablePartOfStaging($tableName, $stagingPrefixes)) {
                $this->logger->log(
                    sprintf('Excluding %s table from backup (staging site table).', $tableName),
                    'INFO'
                );
                continue;
            }

            // Get table info
            $info = $this->repository->getTableInfo($tableName, DB_NAME);
            if ($info === null) {
                $this->logger->log(
                    sprintf('Could not get info about: %s', $tableName),
                    'WARN'
                );
                continue;
            }

            $result[] = [
                'name' => $tableName,
                'size' => $info['size'],
                'rows' => $info['rows'],
            ];
        }

        return $result;
    }

    /**
     * Gets the list of user-excluded table names.
     *
     * @return array The excluded table names, or empty array if exclusion is disabled.
     */
    private function getExcludedTableNames()
    {
        $shouldExclude = Dashboard\bmi_get_config('BACKUP:DATABASE:EXCLUDE');
        if ($shouldExclude !== 'true') {
            return [];
        }

        $list = Dashboard\bmi_get_config('BACKUP:DATABASE:EXCLUDE:LIST');
        return is_array($list) ? $list : [];
    }

    /**
     * Checks if a table is in the exclusion list.
     *
     * @param string $tableName The table name.
     * @param array $excludedTables The excluded table names.
     * @return bool True if excluded.
     */
    private function isTableExcluded($tableName, $excludedTables)
    {
        return !empty($excludedTables) && in_array($tableName, $excludedTables);
    }

    /**
     * Gets database prefixes of staging site tables.
     *
     * @return array List of staging table prefixes.
     */
    private function getStagingTablePrefixes()
    {
        global $table_prefix;

        try {
            require_once BMI_INCLUDES . '/staging/controller.php';
            $staging = new Staging('..ajax..');
            $stagingSites = $staging->getStagingSites(true);
        } catch (\Exception $e) {
            return [];
        }

        $prefixes = [];
        foreach ($stagingSites as $name => $data) {
            if (!isset($data['db_prefix']) || empty($data['db_prefix'])) {
                continue;
            }

            $prefix = trim(strtolower($data['db_prefix']));
            if ($prefix === '' || $prefix === trim(strtolower($table_prefix))) {
                continue;
            }

            $prefixes[] = $data['db_prefix'];
        }

        return $prefixes;
    }

    /**
     * Checks if a table belongs to a staging site by its prefix.
     *
     * @param string $tableName The table name.
     * @param array $stagingPrefixes List of staging prefixes.
     * @return bool True if the table is part of a staging site.
     */
    private function isTablePartOfStaging($tableName, $stagingPrefixes)
    {
        foreach ($stagingPrefixes as $prefix) {
            if (substr($tableName, 0, strlen($prefix)) === $prefix) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a DatabaseExportEngine instance for a table.
     *
     * @param string $tableName The table name.
     * @return DatabaseExportEngine The engine instance.
     */
    protected function createEngine($tableName)
    {
        if ($this->engineFactory !== null) {
            return call_user_func($this->engineFactory, $tableName);
        }

        return new DatabaseExportEngine(
            $tableName,
            $this->outputDir,
            $this->tablePrefix,
            $this->repository,
            new FileProgressStorage(null, 'db-export-' . preg_replace('/[^A-Za-z0-9_-]/', '', $tableName) . '.json')
        );
    }

    /**
     * Completes the master export and clears state.
     *
     * @param array $masterState The master state.
     * @return array The final result.
     */
    private function completeMasterExport(&$masterState)
    {
        $elapsed = number_format(microtime(true) - $masterState['time_start'], 4);
        $this->logger->log(sprintf('Database export completed in %ss', $elapsed), 'SUCCESS');

        if ($this->debugEnabled){
            $this->logger->log(
                'Memory usage at completion: ' . number_format(memory_get_usage() / 1024 / 1024, 2) . ' MB',
                'INFO'
            );
        }

        $this->stateStorage->clear();

        return [
            'status' => self::STATUS_COMPLETED,
            'files' => $masterState['files'],
            'total_tables' => count($masterState['tables']),
            'total_rows' => $masterState['total_rows'],
            'current_table_index' => $masterState['current_table_index'],
            'table_name' => '',
        ];
    }

    /**
     * Resets all export state (master + per-table).
     *
     * Useful when starting a fresh export or after an error.
     */
    public function reset()
    {
        $stored = $this->stateStorage->load();
        if ($stored !== null && isset($stored['tables'])) {
            foreach ($stored['tables'] as $tableData) {
                $tableName = $tableData['name'];
                $perTableStorage = new FileProgressStorage(
                    null,
                    'db-export-' . preg_replace('/[^A-Za-z0-9_-]/', '', $tableName) . '.json'
                );
                $perTableStorage->clear();
            }
        }
        $this->stateStorage->clear();
    }

    /**
     * Gets the time-based table prefix.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Gets the shared repository instance.
     *
     * @return DatabaseExportRepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * is debug mode enabled for export?
     * @return bool
     */    
    private function isDebugMode()
    {
        return defined('BMI_DEBUG') && BMI_DEBUG === true;
    }
}
