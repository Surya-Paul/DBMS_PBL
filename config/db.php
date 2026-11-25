<?php
/**
 * Database Connection Provider using PDO
 * Handles connection exceptions gracefully and guides user to setup.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    $config = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'ParkingDB',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ];
} else {
    $config = require $configFile;
}

$db_connected = false;
$db_error = null;
$pdo = null;

try {
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset'] ?? 'utf8mb4'
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

/**
 * Returns active PDO instance or null
 */
function get_db_connection(): ?PDO {
    global $pdo;
    return $pdo;
}
