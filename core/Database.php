<?php
/**
 * 資料庫連線單例
 * core/Database.php
 */

class Database
{
    private static ?Database $instance = null;
    private ?mysqli $connection = null;
    private ?mysqli $moodleConnection = null;
    
    private string $host;
    private string $user;
    private string $pass;
    private string $dbname;
    
    private function __construct()
    {
        // 從 config 載入設定
        $this->host = $GLOBALS['db_host'] ?? 'localhost';
        $this->user = $GLOBALS['db_user'] ?? 'root';
        $this->pass = $GLOBALS['db_pass'] ?? '';
        $this->dbname = $GLOBALS['db_name'] ?? 'portal';
    }
    
    /**
     * 取得單例實例
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 取得 Portal 資料庫連線
     */
    public function getConnection(): mysqli
    {
        if ($this->connection === null) {
            $this->connection = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
            $this->connection->set_charset('utf8mb4');
            
            if ($this->connection->connect_error) {
                throw new Exception('Database connection failed: ' . $this->connection->connect_error);
            }
        }
        return $this->connection;
    }
    
    /**
     * 取得 Moodle 資料庫連線
     */
    public function getMoodleConnection(): mysqli
    {
        if ($this->moodleConnection === null) {
            $this->moodleConnection = new mysqli($this->host, $this->user, $this->pass, 'moodle');
            $this->moodleConnection->set_charset('utf8mb4');
            
            if ($this->moodleConnection->connect_error) {
                throw new Exception('Moodle database connection failed: ' . $this->moodleConnection->connect_error);
            }
        }
        return $this->moodleConnection;
    }
    
    /**
     * 執行 Portal 查詢
     */
    public function query(string $sql): mysqli_result|bool
    {
        return $this->getConnection()->query($sql);
    }
    
    /**
     * 準備語句
     */
    public function prepare(string $sql): mysqli_stmt|false
    {
        return $this->getConnection()->prepare($sql);
    }
    
    /**
     * 取得最後插入的 ID
     */
    public function lastInsertId(): int
    {
        return $this->getConnection()->insert_id;
    }
    
    /**
     * 關閉連線
     */
    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
        if ($this->moodleConnection !== null) {
            $this->moodleConnection->close();
            $this->moodleConnection = null;
        }
    }
    
    // 防止複製
    private function __clone() {}
    
    // 防止反序列化
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
