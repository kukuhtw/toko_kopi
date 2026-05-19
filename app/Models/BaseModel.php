<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;
use PDOStatement;

abstract class BaseModel
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function find(int $id): array|false
    {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1",
            [$id]
        )->fetch();
    }

    public function findAll(string $where = '', array $params = [], string $order = ''): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($where) $sql .= " WHERE {$where}";
        if ($order) $sql .= " ORDER BY {$order}";
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->query(
            "INSERT INTO {$this->table} ({$cols}) VALUES ({$placeholders})",
            array_values($data)
        );
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        $stmt = $this->query(
            "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?",
            $values
        );
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        return $this->query(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        )->rowCount() > 0;
    }

    public function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where) $sql .= " WHERE {$where}";
        return (int) $this->query($sql, $params)->fetchColumn();
    }
}
