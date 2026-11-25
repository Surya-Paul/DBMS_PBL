<?php
/**
 * Shared Header Layout with Persistent Navigation
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/flash.php';

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '' ?>Campus Parking Management System (DBMS PBL)</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Application CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <span class="bg-primary text-white rounded p-1 me-2 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-p-square-fill fs-5"></i>
            </span>
            <span>Parking<span class="text-primary-emphasis fw-bold">DB</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#parkingNavbar" aria-controls="parkingNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="parkingNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'zones.php' ? 'active' : '' ?>" href="zones.php">
                        <i class="bi bi-geo-alt me-1"></i>Zones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'vehicles.php' ? 'active' : '' ?>" href="vehicles.php">
                        <i class="bi bi-car-front me-1"></i>Vehicles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'slots.php' ? 'active' : '' ?>" href="slots.php">
                        <i class="bi bi-grid-3x3-gap me-1"></i>Slots
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'records.php' ? 'active' : '' ?>" href="records.php">
                        <i class="bi bi-clock-history me-1"></i>Parking Records
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'payments.php' ? 'active' : '' ?>" href="payments.php">
                        <i class="bi bi-cash-stack me-1"></i>Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="reports.php">
                        <i class="bi bi-bar-chart-line-fill me-1"></i>Reports
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="setup.php" class="btn btn-sm btn-outline-light" title="Database status and seed assistant">
                    <i class="bi bi-database-check me-1"></i>DB Setup / Seed
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Main Container -->
<main class="container-fluid px-lg-4 py-4">
    <?php display_flash(); ?>

    <?php if (!$db_connected && $currentPage !== 'setup.php'): ?>
        <div class="alert alert-danger shadow-sm border-2 alert-permanent mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="alert-heading mb-1"><i class="bi bi-database-fill-x me-2"></i>Database Not Connected / Configured</h5>
                    <p class="mb-0 small text-danger-emphasis">
                        Unable to connect to MySQL database <strong>ParkingDB</strong>. 
                        Error details: <code><?= sanitize($db_error) ?></code>
                    </p>
                </div>
                <div>
                    <a href="setup.php" class="btn btn-danger btn-sm px-3">
                        <i class="bi bi-magic me-1"></i>Run Database Setup Assistant
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
