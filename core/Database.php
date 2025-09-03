<?php
namespace Core;

use PDO;

class Database {
    protected $pdo;

    public function __construct($container) {
        $config = $this->loadConfig();
        $dsn = $config['dsn'] ?? '';
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = $config['options'] ?? [];
        $this->pdo = new PDO($dsn, $username, $password, $options);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function loadConfig(): array {
        $path = __DIR__ . '/../config/database.php';
        if (!file_exists($path)) {
            throw new \Exception(
                'Database configuration not found. Copy config/database.example.php to config/database.php'
            );
        }
        $config = require $path;
        if (!is_array($config)) {
            throw new \Exception('Database configuration file must return an array.');
        }
        return $config;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}
