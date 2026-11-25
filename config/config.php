<?php
/**
 * Local Database Configuration
 * Customize your database credentials below if using XAMPP, MAMP, or custom MySQL.
 */

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306,
    'database' => getenv('DB_NAME') ?: 'ParkingDB',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '', // XAMPP default is empty, MAMP is 'root'
    'charset' => 'utf8mb4'
];
