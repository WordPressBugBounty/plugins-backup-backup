<?php

namespace BMI\Plugin\Database\Export;

/**
 * Interface DatabaseExportRepositoryInterface
 *
 * Defines the contract for database interactions used in database export operations.
 * Implementations can use MySQLi or WordPress $wpdb with consistent behavior.
 */
interface DatabaseExportRepositoryInterface
{
    /**
     * Escapes a string for safe use in SQL queries.
     *
     * @param string $string The string to escape.
     * @return string The escaped string.
     */
    public function escape($string);

    /**
     * Escapes a SQL identifier (table/column name) with backticks.
     *
     * @param string $identifier The identifier to escape.
     * @return string The escaped identifier wrapped in backticks.
     */
    public function escapeIdentifier($identifier);

    /**
     * Retrieves the last error message from the database.
     *
     * @return string|null The last error message, or null if no error.
     */
    public function getLastError();

    /**
     * Retrieves all table names from the current database.
     *
     * @return array List of table names.
     */
    public function getTableNames();

    /**
     * Retrieves table size and row count information.
     *
     * @param string $table The table name.
     * @param string $dbName The database name.
     * @return array{size: float, rows: int}|null Table info or null on failure.
     */
    public function getTableInfo($table, $dbName);

    /**
     * Retrieves column information for a table.
     *
     * @param string $table The table name.
     * @return array{primary_key: string|false, auto_increment_primary_key: string|false, columns: array}
     */
    public function getTableColumnsInfo($table);

    /**
     * Gets the CREATE TABLE statement for a table.
     *
     * @param string $table The table name.
     * @return string|null The CREATE TABLE SQL or null on failure.
     */
    public function getCreateTableStatement($table);

    /**
     * Fetches rows from the database as numerically-indexed arrays.
     *
     * @param string $sql The SQL query to execute.
     * @param bool $useUnbuffered Whether to use unbuffered query mode.
     * @return \Generator Yields rows as numerically-indexed arrays.
     */
    public function fetchRows($sql, $useUnbuffered = false);

    /**
     * Gets the row count for a specific query/table.
     *
     * @param string $table The table name.
     * @param string $whereClause Optional WHERE clause.
     * @return int The row count.
     */
    public function getRowCount($table, $whereClause = '');

    /**
     * Executes a raw SQL query (e.g., SET foreign_key_checks).
     *
     * @param string $sql The SQL to execute.
     * @return bool True on success, false on failure.
     */
    public function execute($sql);
}
