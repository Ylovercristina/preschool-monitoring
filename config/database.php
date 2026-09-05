<?php
/**
 * Database Connection & Initialization
 * Preschool Monitoring System
 * Supports SQLite (default zero-config) and MySQL (via PDO)
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Configuration options
$dbDriver = getenv('DB_DRIVER') ?: 'sqlite'; // 'sqlite' or 'mysql'

function getDB() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $baseDir = dirname(__DIR__);
    $dbDir = $baseDir . DIRECTORY_SEPARATOR . 'database';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    $dbFile = $dbDir . DIRECTORY_SEPARATOR . 'preschool.sqlite';
    $isFirstRun = !file_exists($dbFile) || filesize($dbFile) === 0;

    try {
        $pdo = new PDO("sqlite:" . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Enable foreign key constraints in SQLite
        $pdo->exec("PRAGMA foreign_keys = ON;");

        // If first run, auto-migrate and seed
        if ($isFirstRun) {
            require_once __DIR__ . '/migrate_seed.php';
            runMigrationAndSeed($pdo);
        } else {
            // Check if users table exists
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
            if (!$check) {
                require_once __DIR__ . '/migrate_seed.php';
                runMigrationAndSeed($pdo);
            }
        }

        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
    }
}
