<?php

declare(strict_types=1);

namespace OpenRest\Core;

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    public static function findAll(?string $where = null, ?array $params = null, ?string $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $db = Database::connection();
        $sql = 'SELECT * FROM ' . static::$table;

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit) {
            $sql .= ' LIMIT ?';
            $params = $params ?? [];
            $params[] = $limit;
        }

        if ($offset !== null) {
            $sql .= ' OFFSET ?';
            $params[] = $offset;
        }

        return $db->query($sql, $params ?? []);
    }

    public static function findById(int $id): ?array
    {
        $db = Database::connection();
        return $db->queryOne(
            'SELECT * FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = ?',
            [$id]
        );
    }

    public static function findOne(string $where, array $params = []): ?array
    {
        $db = Database::connection();
        return $db->queryOne(
            'SELECT * FROM ' . static::$table . ' WHERE ' . $where,
            $params
        );
    }

    public static function count(?string $where = null, ?array $params = null): int
    {
        $db = Database::connection();
        $sql = 'SELECT COUNT(*) as total FROM ' . static::$table;

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        $result = $db->queryOne($sql, $params ?? []);
        return (int) ($result['total'] ?? 0);
    }

    public static function create(array $data): int
    {
        $db = Database::connection();
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $db->execute(
            'INSERT INTO ' . static::$table . ' (' . $fields . ') VALUES (' . $placeholders . ')',
            array_values($data)
        );

        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $db = Database::connection();
        $fields = implode(' = ?, ', array_keys($data)) . ' = ?';

        return $db->execute(
            'UPDATE ' . static::$table . ' SET ' . $fields . ' WHERE ' . static::$primaryKey . ' = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): int
    {
        $db = Database::connection();
        return $db->execute(
            'DELETE FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = ?',
            [$id]
        );
    }

    public static function exists(int $id): bool
    {
        return static::findById($id) !== null;
    }
}
