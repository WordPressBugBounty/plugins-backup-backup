<?php

namespace BMI\Plugin\Database\Export;

require_once __DIR__ . '/interface-database-export-repository.php';
require_once __DIR__ . '/class-database-export-repository.php';
require_once BMI_INCLUDES . '/progress/class-file-progress-storage.php';

use BMI\Plugin\Backup_Migration_Plugin as BMP;
use BMI\Plugin\Progress\ProgressStorageInterface;
use BMI\Plugin\Progress\FileProgressStorage;
/**
 * Class DatabaseExportEngine
 *
 * Handles the export of a single database table to a SQL file.
 * Supports resumable batch processing with key-based or offset-based pagination.
 *
 */
class DatabaseExportEngine
{
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const PAGINATION_KEY_BASED = 'key_based';
    const PAGINATION_OFFSET_BASED = 'offset_based';

    /**
     * Default number of rows to fetch per batch.
     */
    const DEFAULT_PAGE_SIZE = 5000;

    /**
     * Default max bytes per SQL file chunk before flushing.
     */
    const DEFAULT_MAX_QUERY_SIZE = 1048576; // 1 MB

    /**
     * @var string The table name to export.
     */
    private $table;

    /**
     * @var DatabaseExportRepositoryInterface The database repository.
     */
    private $repository;

    /**
     * @var ProgressStorageInterface The state storage handler.
     */
    private $stateStorage;

    /**
     * @var int The number of rows to fetch per batch.
     */
    private $pageSize;

    /**
     * @var int The max query/insert size in bytes before writing to disk.
     */
    private $maxQuerySize;

    /**
     * @var string The storage directory for SQL output files.
     */
    private $outputDir;

    /**
     * @var string The time-based prefix for temporary table names.
     */
    private $tablePrefix;

    /**
     * Constructor.
     *
     * @param string $table The table name to export.
     * @param string $outputDir The directory to write SQL files to.
     * @param string $tablePrefix The time-based prefix for temp table names.
     * @param DatabaseExportRepositoryInterface|null $repository Optional repository instance.
     * @param ProgressStorageInterface|null $stateStorage Optional state storage instance.
     * @param int|null $pageSize Optional rows per batch.
     * @param int|null $maxQuerySize Optional max query size in bytes.
     */
    public function __construct(
        $table,
        $outputDir,
        $tablePrefix,
        $repository = null,
        $stateStorage = null,
        $pageSize = null,
        $maxQuerySize = null
    ) {
        $this->table = $table;
        $this->outputDir = rtrim($outputDir, DIRECTORY_SEPARATOR);
        $this->tablePrefix = $tablePrefix;
        $this->repository = $repository !== null ? $repository : new DatabaseExportRepository();
        $this->stateStorage = $stateStorage !== null ? $stateStorage : new FileProgressStorage(
            null,
            'db-export-' . preg_replace('/[^A-Za-z0-9_-]/', '', $table) . '.json'
        );
        $this->pageSize = $pageSize !== null ? $pageSize : (defined('BMI_DB_MAX_ROWS_PER_QUERY') ? BMI_DB_MAX_ROWS_PER_QUERY : self::DEFAULT_PAGE_SIZE);
        $this->maxQuerySize = $maxQuerySize !== null ? $maxQuerySize : self::DEFAULT_MAX_QUERY_SIZE;
    }

    /**
     * Returns the default structure for the export state.
     *
     * @param array $tableInfo Column info from the repository.
     * @param int $rowCount Total row count.
     * @return array The default state array.
     */
    private function getDefaultState($tableInfo, $rowCount)
    {
        return [
            'status' => self::STATUS_IN_PROGRESS,
            'table' => $this->table,
            'time' => [
                'start' => microtime(true),
                'end' => 0
            ],
            'columns' => $tableInfo['columns'],
            'primary_key' => $tableInfo['primary_key'],
            'auto_increment_primary_key' => $tableInfo['auto_increment_primary_key'],
            'pagination_type' => $tableInfo['auto_increment_primary_key'] !== false
                ? self::PAGINATION_KEY_BASED
                : self::PAGINATION_OFFSET_BASED,
            'key_based' => [
                'last_processed_key' => 0,
            ],
            'offset_based' => [
                'offset' => 0,
            ],
            'rows_count' => $rowCount,
            'rows_exported' => 0,
            'total_batches' => (int) ceil($rowCount / $this->pageSize),
            'current_batch' => 0,
            'page_size' => $this->pageSize,
            'recipe_written' => false,
            'output_file' => $this->buildOutputFilePath(),
        ];
    }

    /**
     * Retrieves the latest state or initializes a new one.
     *
     * @return array The state array.
     */
    private function getOrInitState()
    {
        $stored = $this->stateStorage->load();
        if ($stored !== null) {
            return $stored;
        }

        $tableInfo = $this->repository->getTableColumnsInfo($this->table);
        $rowCount = $this->getFilteredRowCount();

        return $this->getDefaultState($tableInfo, $rowCount);
    }

    /**
     * Gets the row count, applying exclusion filters if active.
     *
     * Delegates to the 'bmip_smart_exclusion_post_revisions_condition' filter hook
     * to allow the pro plugin or other extensions to add WHERE conditions.
     *
     * @return int The row count.
     */
    private function getFilteredRowCount()
    {
        $conditions = $this->getExclusionConditions();

        if (!empty($conditions)) {
            return $this->repository->getRowCount($this->table, implode(' AND ', $conditions));
        }

        return $this->repository->getRowCount($this->table);
    }

    /**
     * Gets exclusion conditions from WordPress filters.
     *
     * Delegates to the 'bmip_smart_exclusion_post_revisions_condition' filter hook,
     * allowing the pro plugin or other extensions to add WHERE conditions
     * for specific tables (e.g., excluding post revisions).
     *
     * @return array The WHERE conditions array.
     */
    private function getExclusionConditions()
    {
        $conditions = apply_filters('bmip_smart_exclusion_post_revisions_condition', [], $this->table);

        return is_array($conditions) ? $conditions : [];
    }

    /**
     * Exports one batch of the table.
     *
     * Call this method repeatedly until the returned status is 'completed'.
     * Each call processes one page of rows and streams them directly to the output file.
     *
     * @return array{status: string, table: string, batch: int, total_batches: int, rows_exported: int, rows_count: int, output_file: string}
     */
    public function exportBatch()
    {
        $state = $this->getOrInitState();

        // Write CREATE TABLE recipe on first batch
        if (!$state['recipe_written']) {
            $this->writeRecipe($state);
            $state['recipe_written'] = true;
        }

        // Empty table — mark completed immediately
        if ($state['rows_count'] === 0) {
            return $this->completeExport($state);
        }

        // Fetch and stream one batch
        $sql = $this->buildFetchQuery($state);
        $this->repository->execute('SET foreign_key_checks = 0');

        try {
            $batchResult = $this->streamBatchToFile($sql, $state);
        } finally {
            $this->repository->execute('SET foreign_key_checks = 1');
        }

        // Update state from batch result
        $state['rows_exported'] += $batchResult['rows_written'];
        $state['current_batch']++;

        // Update pagination cursor
        if ($state['pagination_type'] === self::PAGINATION_KEY_BASED && $batchResult['last_key'] !== null) {
            $state['key_based']['last_processed_key'] = $batchResult['last_key'];
        } elseif ($state['pagination_type'] === self::PAGINATION_OFFSET_BASED) {
            $state['offset_based']['offset'] += $batchResult['rows_written'];
        }

        // Check if export is complete
        if ($batchResult['rows_written'] < $this->pageSize || $state['rows_exported'] >= $state['rows_count']) {
            return $this->completeExport($state);
        }

        // Save progress and return in-progress
        $this->stateStorage->save($state);
        return $this->buildResult(self::STATUS_IN_PROGRESS, $state);
    }

    /**
     * Writes the CREATE TABLE recipe to the output file.
     *
     * @param array $state The current state.
     */
    private function writeRecipe(&$state)
    {
        $createSql = $this->repository->getCreateTableStatement($this->table);
        if ($createSql === null) {
            return;
        }

        $prefixedTable = $this->tablePrefix . '_' . $this->table;
        $escapedOriginal = $this->repository->escapeIdentifier($this->table);
        $escapedPrefixed = $this->repository->escapeIdentifier($prefixedTable);

        // Replace table name in CREATE statement
        $createSql = str_replace($escapedOriginal, $escapedPrefixed, $createSql);

        // Build recipe header
        $recipe = "/* CUSTOM VARS START */\n";
        $recipe .= "/* REAL_TABLE_NAME: " . $escapedOriginal . "; */\n";
        $recipe .= "/* PRE_TABLE_NAME: " . $escapedPrefixed . "; */\n";
        $recipe .= "/* CUSTOM VARS END */\n\n";

        // Make it IF NOT EXISTS and clean up formatting
        $createStatement = 'CREATE TABLE IF NOT EXISTS ' . substr($createSql, 13);
        $createStatement = str_replace("\n ", '', $createStatement);
        $createStatement = str_replace("\n", '', $createStatement);

        $recipe .= $createStatement . ";\n";

        $file = fopen($state['output_file'], 'w');
        if ($file === false) {
            throw new \RuntimeException(
                sprintf('Failed to open output file for recipe: %s', $state['output_file'])
            );
        }
        fwrite($file, $recipe);
        fclose($file);
    }

    /**
     * Builds the SQL query for fetching a batch of rows.
     *
     * @param array $state The current state.
     * @return string The SQL query.
     */
    private function buildFetchQuery($state)
    {
        $table = $this->repository->escapeIdentifier($this->table);
        $sql = 'SELECT * FROM ' . $table;

        $conditions = [];

        // Pagination WHERE clause
        if ($state['pagination_type'] === self::PAGINATION_KEY_BASED) {
            $lastKey = (int) $state['key_based']['last_processed_key'];
            $pk = $state['auto_increment_primary_key'];
            $conditions[] = sprintf('`%s` > %d', str_replace('`', '``', $pk), $lastKey);
        }

        // Exclusion conditions (e.g., post revisions via filters)
        $conditions = array_merge($conditions, $this->getExclusionConditions());

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        // ORDER BY for key-based pagination
        if ($state['pagination_type'] === self::PAGINATION_KEY_BASED) {
            $pk = $state['auto_increment_primary_key'];
            $sql .= sprintf(' ORDER BY `%s` ASC', str_replace('`', '``', $pk));
        }

        // LIMIT clause
        if ($state['pagination_type'] === self::PAGINATION_KEY_BASED) {
            $sql .= sprintf(' LIMIT %d', $this->pageSize);
        } else {
            $offset = (int) $state['offset_based']['offset'];
            $sql .= sprintf(' LIMIT %d, %d', $offset, $this->pageSize);
        }

        return $sql;
    }

    /**
     * Streams query results directly into the SQL output file.
     *
     * Instead of buffering all rows in memory (array_merge), this method
     * writes INSERT statements to disk as rows are fetched from the DB.
     * Uses strlen(serialize()) for accurate size tracking.
     *
     * @param string $sql The SELECT query.
     * @param array $state The current state.
     * @return array{rows_written: int, last_key: mixed, bytes_written: int}
     */
    private function streamBatchToFile($sql, &$state)
    {
        $file = fopen($state['output_file'], 'a+');
        if ($file === false) {
            throw new \RuntimeException(
                sprintf('Failed to open output file for writing: %s', $state['output_file'])
            );
        }

        $columns = $state['columns'];
        $prefixedTable = $this->tablePrefix . '_' . $this->table;
        $insertPrefix = 'INSERT INTO ' . $this->repository->escapeIdentifier($prefixedTable) . ' ';

        $rowsWritten = 0;
        $bytesWritten = 0;
        $lastKey = null;
        $currentInsertSize = 0;
        $insertOpen = false;
        $columnHeaderWritten = false;
        $columnHeader = '';
        $pkIndex = $this->findPrimaryKeyIndex($state);

        // Build column header once
        $columnNames = [];
        foreach ($columns as $col) {
            $columnNames[] = $this->repository->escapeIdentifier($col['name']);
        }
        $columnHeader = '(' . implode(', ', $columnNames) . ')';

        // Stream rows directly from DB to file via generator
        $rows = $this->repository->fetchRows($sql, true);

        // Warning: when useUnbuffered is true, the returned $rows is a generator that yields one row at a time. 
        // mysqli will not allow any other queries to run until the generator is fully consumed or destroyed. 
        // This means we cannot run any additional queries until this loop finishes. 
        // This is fine for our use case since we are only streaming data to file.
        foreach ($rows as $row) {
            $rowsWritten++;

            // Track the last primary key value for key-based pagination
            if ($pkIndex !== null && isset($row[$pkIndex])) {
                $lastKey = $row[$pkIndex];
            }

            // Build the values tuple for this row
            $values = $this->buildValuesTuple($row, $columns);
            $rowSize = strlen(serialize($values));

            // Decide whether to start a new INSERT or append to current one
            if (!$insertOpen) {
                // Start new INSERT statement
                $stmt = $insertPrefix . $columnHeader . " VALUES (" . $values . ")";
                $insertOpen = true;
                $currentInsertSize = strlen($stmt);
            } elseif (($currentInsertSize + $rowSize + 3) > $this->maxQuerySize) {
                // Current INSERT too large, close it and start new one
                $written = fwrite($file, ";\n");
                $bytesWritten += $written;

                $stmt = $insertPrefix . $columnHeader . " VALUES (" . $values . ")";
                $currentInsertSize = strlen($stmt);
            } else {
                // Append to current INSERT
                $stmt = ",(" . $values . ")";
                $currentInsertSize += strlen($stmt);
            }

            $written = fwrite($file, $stmt);
            $bytesWritten += $written;
        }

        // Close the final INSERT statement
        if ($insertOpen) {
            $written = fwrite($file, ";\n");
            $bytesWritten += $written;
        }

        fclose($file);

        return [
            'rows_written' => $rowsWritten,
            'last_key' => $lastKey,
            'bytes_written' => $bytesWritten,
        ];
    }

    /**
     * Builds the SQL values tuple string for a single row.
     *
     * Uses esc_sql/real_escape_string for string values.
     * Handles NULL, numeric, and string types based on column metadata.
     *
     * @param array $row Numerically-indexed row data.
     * @param array $columns Column metadata from getTableColumnsInfo().
     * @return string The comma-separated values string (without outer parentheses).
     */
    private function buildValuesTuple($row, $columns)
    {
        $parts = [];
        $columnCount = count($columns);

        for ($i = 0; $i < $columnCount; $i++) {
            $value = isset($row[$i]) ? $row[$i] : null;

            if ($value === null) {
                $parts[] = 'NULL';
            } elseif ($columns[$i]['is_numeric'] && is_numeric($value)) {
                if (strpos($value, '.') !== false || stripos($value, 'e') !== false) {
                    // Float value
                    $floatVal = floatval($value);
                    $parts[] = is_infinite($floatVal) ? (string) PHP_FLOAT_MAX : (string) $floatVal;
                } else {
                    // Integer value
                    $intVal = intval($value);
                    $parts[] = (string) $intVal;
                }
            } else {
                // String value — escape and quote
                $parts[] = "'" . $this->repository->escape($value) . "'";
            }
        }

        return implode(',', $parts);
    }

    /**
     * Finds the index of the primary key column in the columns array.
     *
     * @param array $state The current state.
     * @return int|null The index, or null if no auto-increment PK.
     */
    private function findPrimaryKeyIndex($state)
    {
        if ($state['auto_increment_primary_key'] === false) {
            return null;
        }

        foreach ($state['columns'] as $index => $col) {
            if ($col['name'] === $state['auto_increment_primary_key']) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Marks the export as completed and clears state storage.
     *
     * @param array $state The current state.
     * @return array The result array with completed status.
     */
    private function completeExport(&$state)
    {
        $state['status'] = self::STATUS_COMPLETED;
        $state['time']['end'] = microtime(true);
        $this->stateStorage->clear();

        return $this->buildResult(self::STATUS_COMPLETED, $state);
    }

    /**
     * Builds the result array returned from exportBatch().
     *
     * @param string $status The export status.
     * @param array $state The current state.
     * @return array The result array.
     */
    private function buildResult($status, $state)
    {
        return [
            'status' => $status,
            'table' => $state['table'],
            'batch' => $state['current_batch'],
            'total_batches' => $state['total_batches'],
            'rows_exported' => $state['rows_exported'],
            'rows_count' => $state['rows_count'],
            'output_file' => $state['output_file'],
        ];
    }

    /**
     * Builds the output file path for the SQL dump.
     *
     * @return string The file path.
     */
    private function buildOutputFilePath()
    {
        $friendlyName = preg_replace('/[^A-Za-z0-9_-]/', '', $this->table);
        return $this->outputDir . DIRECTORY_SEPARATOR . $friendlyName . '.sql';
    }

    /**
     * Gets the output file path.
     *
     * @return string The output file path.
     */
    public function getOutputFilePath()
    {
        return $this->buildOutputFilePath();
    }

    /**
     * Resets any stored state for this table (for clean re-export).
     */
    public function reset()
    {
        $this->stateStorage->clear();
    }
}
