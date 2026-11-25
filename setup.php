<?php
/**
 * Database Setup & Seed Utility
 * Provides one-click installation and seed data population for ParkingDB.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configFile = __DIR__ . '/config/config.php';
$config = file_exists($configFile) ? require $configFile : [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'ParkingDB',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$setupError = null;
$setupSuccess = null;
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'install') {
    try {
        // Step 1: Connect to MySQL server without database
        $serverDsn = sprintf("mysql:host=%s;port=%d;charset=%s", $config['host'], $config['port'], $config['charset'] ?? 'utf8mb4');
        $serverPdo = new PDO($serverDsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $logs[] = "Connected to MySQL server at {$config['host']}:{$config['port']}.";

        // Step 2: Create Database
        $dbName = $config['database'];
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $logs[] = "Database `{$dbName}` created or verified.";

        // Step 3: Switch to database
        $serverPdo->exec("USE `{$dbName}`;");

        // Step 4: Execute Schema
        $schemaSql = file_get_contents(__DIR__ . '/sql/schema.sql');
        if ($schemaSql === false) {
            throw new Exception("Unable to read schema file at /sql/schema.sql");
        }
        $serverPdo->exec($schemaSql);
        $logs[] = "Schema executed successfully. All tables (Parking_Zone, Vehicle, Parking_Slot, Parking_Record, Parking_Payment) created.";

        // Step 5: Execute Seed data
        if (!empty($_POST['include_seed'])) {
            $seedSql = file_get_contents(__DIR__ . '/sql/seed.sql');
            if ($seedSql === false) {
                throw new Exception("Unable to read seed file at /sql/seed.sql");
            }
            $serverPdo->exec($seedSql);
            $logs[] = "Sample seed data inserted successfully (Zones, Vehicles, Slots, Records, Payments).";
        }

        $setupSuccess = "Database initialization completed successfully!";
    } catch (Exception $e) {
        $setupError = "Setup failed: " . $e->getMessage();
    }
}

// Check current status
$canConnectServer = false;
$dbExists = false;
$tableStats = [];

try {
    $serverDsn = sprintf("mysql:host=%s;port=%d;charset=%s", $config['host'], $config['port'], $config['charset'] ?? 'utf8mb4');
    $testPdo = new PDO($serverDsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $canConnectServer = true;

    // Check if database exists
    $stmt = $testPdo->query("SHOW DATABASES LIKE '{$config['database']}'");
    if ($stmt->fetch()) {
        $dbExists = true;
        $testPdo->exec("USE `{$config['database']}`;");
        
        $tables = ['Parking_Zone', 'Vehicle', 'Parking_Slot', 'Parking_Record', 'Parking_Payment'];
        foreach ($tables as $tbl) {
            try {
                $countStmt = $testPdo->query("SELECT COUNT(*) FROM `{$tbl}`");
                $tableStats[$tbl] = (int)$countStmt->fetchColumn();
            } catch (Exception) {
                $tableStats[$tbl] = 'Missing';
            }
        }
    }
} catch (Exception $e) {
    $canConnectServer = false;
    $connErrMsg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Initializer | Campus Parking Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3 shadow" style="width: 64px; height: 64px;">
                        <i class="bi bi-database-fill-gear fs-2"></i>
                    </div>
                    <h2 class="fw-bold text-dark">ParkingDB Setup & Seed Assistant</h2>
                    <p class="text-muted">Easily configure, create, and populate your local MySQL Parking Database</p>
                </div>

                <?php if ($setupSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Success!</h5>
                        <p class="mb-2"><?= htmlspecialchars($setupSuccess) ?></p>
                        <hr>
                        <ul class="mb-0 small">
                            <?php foreach ($logs as $log): ?>
                                <li><?= htmlspecialchars($log) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-success"><i class="bi bi-speedometer2 me-1"></i>Go to Dashboard</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($setupError): ?>
                    <div class="alert alert-danger shadow-sm" role="alert">
                        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error during setup</h5>
                        <p class="mb-0"><?= htmlspecialchars($setupError) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Server & Config Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-secondary"><i class="bi bi-gear-wide-connected me-2"></i>Local Connection Settings</span>
                            <?php if ($canConnectServer): ?>
                                <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check2 me-1"></i>MySQL Server Online</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger"><i class="bi bi-x-circle me-1"></i>Cannot Connect to MySQL</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 small">
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Host / Port</label>
                                <span class="fw-bold"><?= htmlspecialchars($config['host']) ?>:<?= htmlspecialchars((string)$config['port']) ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Target Database</label>
                                <span class="fw-bold text-primary"><?= htmlspecialchars($config['database']) ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Username</label>
                                <span class="fw-bold"><?= htmlspecialchars($config['username']) ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Password Status</label>
                                <span class="fw-bold"><?= empty($config['password']) ? '<span class="text-secondary">(empty / default)</span>' : '<span class="text-success">(configured)</span>' ?></span>
                            </div>
                        </div>

                        <?php if (!$canConnectServer): ?>
                            <div class="alert alert-warning mt-3 mb-0 small">
                                <strong>Connection notice:</strong> <?= htmlspecialchars($connErrMsg ?? 'Unable to connect.') ?><br>
                                Please verify your local MySQL server (XAMPP, MAMP, Homebrew, or Docker) is running, and edit <code>config/config.php</code> if using custom credentials.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Database Status & Installer -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="bi bi-table me-2 text-primary"></i>Database & Table Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($dbExists && !empty($tableStats)): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Table Name</th>
                                            <th>Status</th>
                                            <th>Row Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tableStats as $tbl => $cnt): ?>
                                            <tr>
                                                <td class="font-monospace fw-semibold"><?= htmlspecialchars($tbl) ?></td>
                                                <td>
                                                    <?php if ($cnt === 'Missing'): ?>
                                                        <span class="badge bg-danger">Not Created</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Ready</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= is_int($cnt) ? number_format($cnt) . ' rows' : 'N/A' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">
                                Database <code><?= htmlspecialchars($config['database']) ?></code> is not yet created or contains no tables.
                            </p>
                        <?php endif; ?>

                        <form method="POST" action="setup.php" onsubmit="return confirm('Are you sure you want to initialize the database? Existing tables in ParkingDB will be recreated.')">
                            <input type="hidden" name="action" value="install">
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="include_seed" name="include_seed" value="1" checked>
                                <label class="form-check-label fw-semibold" for="include_seed">
                                    Include Sample Seed Data (Zones A/B/C/D, 6 Vehicles, 5 Slots, 9 Parking Records, 6 Payments)
                                </label>
                                <div class="form-text">Populates realistic campus parking records with Short, Medium, and Long stays and Paid/Partial payment statuses.</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4" <?= !$canConnectServer ? 'disabled' : '' ?>>
                                    <i class="bi bi-play-circle-fill me-1"></i><?= $dbExists ? 'Re-Initialize / Reset Database' : 'Create & Seed Database' ?>
                                </button>
                                <?php if ($dbExists): ?>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-1"></i>Return to Dashboard
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body small text-muted">
                        <div class="fw-bold text-dark mb-1"><i class="bi bi-terminal me-1"></i>Manual Setup via CLI (Alternative)</div>
                        You can also import the database via terminal or MySQL Workbench:
                        <pre class="bg-dark text-white p-2 rounded mt-2 mb-0">mysql -u root -p < sql/schema.sql
mysql -u root -p < sql/seed.sql</pre>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
