<?php

namespace BMI\Plugin\Database\Export;

require_once __DIR__ . '/interface-database-export-repository.php';

/**
 * Class DatabaseExportRepository
 *
 * Handles database interactions for the database export functionality.
 * Supports both direct MySQLi connection (preferred) and fallback to global $wpdb.
 *
 * MySQLi is preferred because:
 * - Direct connection avoids $wpdb overhead
 * - real_escape_string is faster than esc_sql for bulk operations
 * - fetch_array(MYSQLI_NUM) yields numerically-indexed rows with minimal memory
 * - Unbuffered queries can stream results directly to files
 */
class DatabaseExportRepository implements DatabaseExportRepositoryInterface
{
    /**
     * @var \mysqli|null The MySQLi connection instance.
     */
    private $mysqli = null;

    /**
     * @var bool Whether to use the MySQLi connection.
     */
    private $useMysqli = false;

    /**
     * @var string|null The last error message.
     */
    private $lastError = null;

    /**
     * Constructor.
     *
     * Initializes the database connection, preferring MySQLi.
     */
    public function __construct()
    {
        $this->connect();
    }

    /**
     * Establishes a connection to the database.
     *
     * Tries MySQLi first; falls back to $wpdb if unavailable or connection fails.
     */
    private function connect()
    {
        if (!$this->canUseMysqli()) {
            return;
        }

        $connectionParams = $this->parseHostString(DB_HOST);

        try {
            $this->mysqli = new \mysqli(
                $connectionParams['host'],
                DB_USER,
                DB_PASSWORD,
                DB_NAME,
                $connectionParams['port'],
                $connectionParams['socket']
            );

            if ($this->mysqli->connect_errno) {
                $this->useMysqli = false;
                $this->mysqli = null;
                return;
            }

            $this->useMysqli = true;
            $this->setCharset();
        } catch (\Exception $e) {
            $this->useMysqli = false;
            $this->mysqli = null;
        }
    }

    /**
     * Checks if MySQLi extension can be used.
     *
     * @return bool True if MySQLi is available and required constants are defined.
     */
    private function canUseMysqli()
    {
        return extension_loaded('mysqli')
            && defined('DB_HOST')
            && defined('DB_USER')
            && defined('DB_PASSWORD')
            && defined('DB_NAME');
    }

    /**
     * Parses the host string to extract host, port, and socket.
     *
     * Handles formats like: "localhost", "localhost:3306", "localhost:/tmp/mysql.sock"
     *
     * @param string $hostString The host string from DB_HOST.
     * @return array{host: string, port: int|null, socket: string|null}
     */
    private function parseHostString($hostString)
    {
        $host = $hostString;
        $port = null;
        $socket = null;

        if (strpos($hostString, ':') !== false) {
            $parts = explode(':', $hostString, 2);
            $host = $parts[0];
            $portOrSocket = $parts[1];

            if (is_numeric($portOrSocket)) {
                $port = (int) $portOrSocket;
            } else {
                $socket = $portOrSocket;
            }
        }

        return [
            'host' => $host,
            'port' => $port,
            'socket' => $socket
        ];
    }

    /**
     * Sets the character set for the MySQLi connection based on WordPress config.
     */
    private function setCharset()
    {
        global $wpdb;
        if (!empty($wpdb->charset) && $this->mysqli !== null) {
            $this->mysqli->set_charset($wpdb->charset);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function escape($string)
    {
        if ($string === null) {
            return '';
        }

        if ($this->useMysqli && $this->mysqli !== null) {
            return $this->mysqli->real_escape_string($string);
        }

        return esc_sql($string);
    }

    /**
     * {@inheritdoc}
     */
    public function escapeIdentifier($identifier)
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * {@inheritdoc}
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableNames()
    {
        $result = $this->query('SHOW TABLES');

        if (!is_array($result)) {
            return [];
        }

        $tables = [];
        foreach ($result as $row) {
            // SHOW TABLES returns rows with a single column
            $values = array_values($row);
            if (!empty($values)) {
                $tables[] = $values[0];
            }
        }

        return $tables;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableInfo($table, $dbName)
    {
        $escapedTable = $this->escape($table);
        $escapedDb = $this->escape($dbName);

        $sql = "SELECT table_name AS `table`, "
             . "ROUND(((data_length + index_length) / 1024 / 1024), 2) AS `size` "
             . "FROM information_schema.TABLES "
             . "WHERE table_schema = '" . $escapedDb . "' AND table_name = '" . $escapedTable . "'";

        $result = $this->query($sql);

        if (empty($result) || !isset($result[0])) {
            return null;
        }

        $row = $result[0];

        // Get actual row count with COUNT(*)
        $countSql = 'SELECT COUNT(*) AS cnt FROM ' . $this->escapeIdentifier($table);
        $countResult = $this->query($countSql);
        $rowCount = 0;
        if (!empty($countResult) && isset($countResult[0]['cnt'])) {
            $rowCount = (int) $countResult[0]['cnt'];
        }

        return [
            'size' => floatval($row['size']),
            'rows' => $rowCount
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTableColumnsInfo($table)
    {
        $fields = $this->query('DESCRIBE ' . $this->escapeIdentifier($table));

        $primaryKey = false;
        $autoIncrementPrimaryKey = false;
        $columns = [];

        if (!is_array($fields)) {
            return [
                'primary_key' => $primaryKey,
                'auto_increment_primary_key' => $autoIncrementPrimaryKey,
                'columns' => $columns
            ];
        }

        foreach ($fields as $fieldData) {
            $field = $fieldData['Field'];
            $key = $fieldData['Key'];
            $extra = $fieldData['Extra'];
            $type = strtolower($fieldData['Type']);

            $isPrimary = ($key === 'PRI');
            $isAutoIncrement = ($isPrimary && strpos($extra, 'auto_increment') !== false);

            if ($isPrimary && $primaryKey === false) {
                $primaryKey = $field;
            }

            if ($isAutoIncrement && $autoIncrementPrimaryKey === false) {
                $autoIncrementPrimaryKey = $field;
            }

            $columns[] = [
                'name' => $field,
                'type' => $type,
                'is_numeric' => $this->isNumericType($type),
            ];
        }

        return [
            'primary_key' => $primaryKey,
            'auto_increment_primary_key' => $autoIncrementPrimaryKey,
            'columns' => $columns
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateTableStatement($table)
    {
        $result = $this->query('SHOW CREATE TABLE ' . $this->escapeIdentifier($table));

        if (empty($result) || !isset($result[0])) {
            return null;
        }

        $row = $result[0];

        // SHOW CREATE TABLE returns 'Create Table' column
        if (isset($row['Create Table'])) {
            return $row['Create Table'];
        }

        // Fallback: try second column (some drivers return different key names)
        $values = array_values($row);
        return isset($values[1]) ? $values[1] : null;
    }

    /**
     * {@inheritdoc}
     *
     * Uses MySQLi unbuffered queries when available to stream results
     * with minimal memory footprint. Falls back to $wpdb buffered results.
     */
    public function fetchRows($sql, $useUnbuffered = true)
    {
        $this->lastError = null;

        if ($this->useMysqli && $this->mysqli !== null) {
            return $this->fetchMysqli($sql, $useUnbuffered);
        }

        return $this->fetchWpdb($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getRowCount($table, $whereClause = '')
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->escapeIdentifier($table);
        if (!empty($whereClause)) {
            $sql .= ' WHERE ' . $whereClause;
        }

        $result = $this->query($sql);

        if (!empty($result) && isset($result[0]['cnt'])) {
            return (int) $result[0]['cnt'];
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($sql)
    {
        $this->lastError = null;

        if ($this->useMysqli && $this->mysqli !== null) {
            $result = $this->mysqli->query($sql);
            if ($result === false) {
                $this->lastError = $this->mysqli->error;
                return false;
            }
            return true;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Callers control the SQL
        $result = $wpdb->query($sql);
        if ($wpdb->last_error !== '') {
            $this->lastError = $wpdb->last_error;
            return false;
        }

        return $result !== false;
    }

    /**
     * Fetches rows via MySQLi as numerically-indexed arrays using a generator.
     *
     * Uses buffered query to be safe with multiple queries, but yields rows
     * one at a time to avoid keeping everything in memory.
     *
     * @param string $sql The SQL query.
     * @return \Generator Yields rows as numerically-indexed arrays.
     */
    private function fetchMysqli($sql, $useUnbuffered = true)
    {
        $resultModeFlag = $useUnbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT;
        $result = $this->mysqli->query($sql, $resultModeFlag);

        if ($result === false) {
            $this->lastError = $this->mysqli->error;
            return;
        }

        if ($result === true) {
            return;
        }

        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            yield $row;
        }

        // Detect errors that occurred mid-stream (e.g., connection timeout)
        if ($this->mysqli->errno) {
            $this->lastError = $this->mysqli->error;
        }

        $result->free();
    }

    /**
     * Fetches rows via $wpdb as numerically-indexed arrays using a generator.
     *
     * @param string $sql The SQL query.
     * @return \Generator Yields rows as numerically-indexed arrays.
     */
    private function fetchWpdb($sql)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is built by callers
        $results = $wpdb->get_results($sql, ARRAY_N);

        if ($wpdb->last_error !== '') {
            $this->lastError = $wpdb->last_error;
            return;
        }

        if (!is_array($results)) {
            return;
        }

        foreach ($results as $row) {
            yield $row;
        }
    }

    /**
     * Determines if a MySQL column type is numeric.
     *
     * @param string $type The lowercase column type string.
     * @return bool True if the type is numeric.
     */
    private function isNumericType($type)
    {
        $numericTypes = ['int', 'decimal', 'float', 'double', 'real', 'bit', 'numeric'];
        foreach ($numericTypes as $numType) {
            if (strpos($type, $numType) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Executes a SQL query using the active connection.
     *
     * @param string $sql The SQL query to execute.
     * @return array|bool The query result as array of associative arrays, or true/empty array.
     */
    private function query($sql)
    {
        $this->lastError = null;

        if ($this->useMysqli && $this->mysqli !== null) {
            return $this->executeMysqliQuery($sql);
        }

        return $this->executeWpdbQuery($sql);
    }

    /**
     * Executes query using MySQLi.
     *
     * @param string $sql The SQL query.
     * @return array|bool The result.
     */
    private function executeMysqliQuery($sql)
    {
        $result = $this->mysqli->query($sql);

        if ($result === false) {
            $this->lastError = $this->mysqli->error;
            return [];
        }

        if ($result === true) {
            return true;
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();

        return $data;
    }

    /**
     * Executes query using $wpdb.
     *
     * @param string $sql The SQL query.
     * @return array|null The result.
     */
    private function executeWpdbQuery($sql)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is built by callers
        $result = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error !== '') {
            $this->lastError = $wpdb->last_error;
        }

        return $result;
    }

    /**
     * Whether the repository is using MySQLi.
     *
     * @return bool
     */
    public function isMysqli()
    {
        return $this->useMysqli;
    }

    /**
     * Closes the MySQLi connection if open.
     */
    public function __destruct()
    {
        if ($this->mysqli !== null && $this->useMysqli) {
            $this->mysqli->close();
        }
    }
}
