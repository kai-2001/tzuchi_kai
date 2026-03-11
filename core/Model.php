<?php
/**
 * 基礎模型
 * core/Model.php
 */

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * 取得資料庫連線
     */
    protected function getConnection(): mysqli
    {
        return $this->db->getConnection();
    }
    
    /**
     * 根據 ID 查詢單筆記錄
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $table = static::$table;
        $pk = static::$primaryKey;
        
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$pk} = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ?: null;
    }
    
    /**
     * 取得所有記錄
     */
    public static function all(): array
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $result = $db->query("SELECT * FROM {$table}");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    /**
     * 根據條件查詢
     */
    public static function where(string $column, $value): array
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$column} = ?");
        
        if (is_int($value)) {
            $stmt->bind_param('i', $value);
        } else {
            $stmt->bind_param('s', $value);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $rows;
    }
    
    /**
     * 插入記錄
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $stmt = $db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        
        $types = '';
        $values = [];
        foreach ($data as $value) {
            $types .= is_int($value) ? 'i' : 's';
            $values[] = $value;
        }
        
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $insertId = $db->lastInsertId();
        $stmt->close();
        
        return $insertId;
    }
    
    /**
     * 更新記錄
     */
    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $table = static::$table;
        $pk = static::$primaryKey;
        
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "{$col} = ?";
        }
        $setString = implode(', ', $sets);
        
        $stmt = $db->prepare("UPDATE {$table} SET {$setString} WHERE {$pk} = ?");
        
        $types = '';
        $values = [];
        foreach ($data as $value) {
            $types .= is_int($value) ? 'i' : 's';
            $values[] = $value;
        }
        $types .= 'i';
        $values[] = $id;
        
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * 刪除記錄
     */
    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        $table = static::$table;
        $pk = static::$primaryKey;
        
        $stmt = $db->prepare("DELETE FROM {$table} WHERE {$pk} = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * 計算總數
     */
    public static function count(): int
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $result = $db->query("SELECT COUNT(*) as cnt FROM {$table}");
        $row = $result->fetch_assoc();
        
        return (int) ($row['cnt'] ?? 0);
    }
}
