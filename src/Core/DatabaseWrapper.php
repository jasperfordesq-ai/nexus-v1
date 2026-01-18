<?php

namespace Nexus\Core;

/**
 * DatabaseWrapper (The "Tenant Leak" Fix)
 * 
 * This class wraps the standard database connection and AUTOMATICALLY enforces
 * tenant isolation. It prevents the "Missing WHERE Clause" vulnerability by
 * ensuring every query is scoped to the current tenant ID.
 */
class DatabaseWrapper
{
    /**
     * Execute a query with automatic Tenant ID injection.
     * 
     * @param string $sql The raw SQL query
     * @param array $params Optional parameters
     * @param string|null $tableAlias Optional alias (e.g. 'l') to fix ambiguous columns
     * @return \PDOStatement
     */
    public static function query($sql, $params = [], $tableAlias = null)
    {
        // 1. Get the current Tenant ID
        $tenantId = TenantContext::getId();

        if (!$tenantId) {
            die("CRITICAL SECURITY ERROR: No Tenant Context found for database query.");
        }

        // 2. Smart Injection Logic
        $trimmedSql = trim($sql);

        $isSelect = stripos($trimmedSql, 'SELECT') === 0;
        $isUpdate = stripos($trimmedSql, 'UPDATE') === 0;
        $isDelete = stripos($trimmedSql, 'DELETE') === 0;

        if ($isSelect || $isUpdate || $isDelete) {
            // Determine column name
            $col = $tableAlias ? "{$tableAlias}.tenant_id" : "tenant_id";

            // FIX: Split by ORDER BY/GROUP BY/LIMIT to insert WHERE in the right spot
            $pattern = '/\s+(ORDER BY|GROUP BY|LIMIT)\s+/i';
            $parts = preg_split($pattern, $sql, 2, PREG_SPLIT_DELIM_CAPTURE);

            $baseQuery = $parts[0];
            $suffix = isset($parts[1]) ? " " . $parts[1] . " " . ($parts[2] ?? '') : '';

            // Detect Parameter Style (Positional vs Named)
            $usesPositional = strpos($sql, '?') !== false;

            // Append the constraint to the Base Query (Part 0) only
            // If positional, use ? and push to array.
            // If named, use :name and add key.

            $paramPlaceholder = $usesPositional ? "?" : ":_auto_tenant_id";

            if (stripos($baseQuery, 'WHERE') !== false) {
                $baseQuery .= " AND {$col} = {$paramPlaceholder}";
            } else {
                $baseQuery .= " WHERE {$col} = {$paramPlaceholder}";
            }

            // Reassemble
            $sql = $baseQuery . $suffix;

            // Add the parameter
            if ($usesPositional) {
                $params[] = $tenantId;
            } else {
                $params['_auto_tenant_id'] = $tenantId;
            }
        }

        // 3. Execute via standard Database class
        return Database::query($sql, $params);
    }

    /**
     * A helper to insert data with the tenant_id automatically included.
     */
    public static function insert($table, $data)
    {
        $data['tenant_id'] = TenantContext::getId();

        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        return Database::query($sql, $data);
    }
}
