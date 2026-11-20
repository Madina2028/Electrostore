<?php
class Database {
    private $conn;

    public function connect() {
        $this->conn = null;
        $configFile = __DIR__ . '/config/db.local.php';
        if (!is_file($configFile)) {
            error_log('Database configuration missing: config/db.local.php');
            die('Database configuration is missing. Copy config/db.local.example.php to config/db.local.php and configure it.');
        }

        $config = require $configFile;
        foreach (['host', 'db_name', 'username', 'password'] as $key) {
            if (!is_array($config) || !array_key_exists($key, $config) || !is_string($config[$key])) {
                throw new RuntimeException('Invalid database configuration: missing or invalid ' . $key . '.');
            }
        }

        try {
            $this->conn = new PDO(
                "mysql:host={$config['host']};dbname={$config['db_name']};charset=utf8mb4",
                $config['username'],
                $config['password']
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }

        return $this->conn;
    }
}
?>