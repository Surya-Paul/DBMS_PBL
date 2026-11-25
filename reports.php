<?php
/**
 * Reports Center: Advanced DBMS Queries & Analytics
 * Campus Parking Management System
 */

$pageTitle = 'Reports Center';
require_once __DIR__ . '/includes/header.php';

if (!$db_connected) {
    echo '<div class="alert alert-danger">Database connection required. Please visit <a href="setup.php">Setup</a>.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$activeReport = $_GET['tab'] ?? 'rep1';

// Report 1: Vehicles and their zones (JOIN)
$rep1Sql = "
    SELECT v.vehicle_id, v.vehicle_number, v.owner_name, v.vehicle_type, v.registration_year,
           z.zone_id, z.zone_name, z.location
    FROM Vehicle v
    LEFT JOIN Parking_Zone z ON v.zone_id = z.zone_id
    ORDER BY z.zone_name ASC, v.vehicle_number ASC
";
$rep1 = $pdo->query($rep1Sql)->fetchAll();

// Report 2: Parking visits longer than four hours (Filter & Join)
$rep2Sql = "
    SELECT r.record_id, r.parking_date, r.hours_parked, r.stay_category,
           v.vehicle_number, v.owner_name, v.vehicle_type,
           s.slot_id, s.slot_name, s.hourly_rate,
           (r.hours_parked * s.hourly_rate) AS estimated_charge
    FROM Parking_Record r
    JOIN Vehicle v ON r.vehicle_id = v.vehicle_id
    JOIN Parking_Slot s ON r.slot_id = s.slot_id
    WHERE r.hours_parked > 4.00
    ORDER BY r.hours_parked DESC
";
$rep2 = $pdo->query($rep2Sql)->fetchAll();

// Report 3: Average parking duration per slot (GROUP BY & AVG)
$rep3Sql = "
    SELECT s.slot_id, s.slot_name, s.hourly_rate, z.zone_name,
           COUNT(r.record_id) AS total_visits,
           COALESCE(AVG(r.hours_parked), 0) AS avg_duration,
           COALESCE(MIN(r.hours_parked), 0) AS min_duration,
           COALESCE(MAX(r.hours_parked), 0) AS max_duration
    FROM Parking_Slot s
    LEFT JOIN Parking_Zone z ON s.zone_id = z.zone_id
    LEFT JOIN Parking_Record r ON s.slot_id = r.slot_id
    GROUP BY s.slot_id, s.slot_name, s.hourly_rate, z.zone_name
    ORDER BY avg_duration DESC
";
$rep3 = $pdo->query($rep3Sql)->fetchAll();

// Report 4: Longest recorded stay (Subquery with MAX)
$rep4Sql = "
    SELECT r.record_id, r.parking_date, r.hours_parked, r.stay_category,
           v.vehicle_id, v.vehicle_number, v.owner_name, v.vehicle_type,
           s.slot_id, s.slot_name, s.hourly_rate,
           (r.hours_parked * s.hourly_rate) AS total_charge
    FROM Parking_Record r
    JOIN Vehicle v ON r.vehicle_id = v.vehicle_id
    JOIN Parking_Slot s ON r.slot_id = s.slot_id
    WHERE r.hours_parked = (SELECT MAX(hours_parked) FROM Parking_Record)
";
$rep4 = $pdo->query($rep4Sql)->fetchAll();

// Report 5: Vehicle counts by zone, including zones with no vehicles (LEFT JOIN & COUNT)
$rep5Sql = "
    SELECT z.zone_id, z.zone_name, z.location,
           COUNT(v.vehicle_id) AS total_vehicles
    FROM Parking_Zone z
    LEFT JOIN Vehicle v ON z.zone_id = v.zone_id
    GROUP BY z.zone_id, z.zone_name, z.location
    ORDER BY total_vehicles DESC, z.zone_name ASC
";
$rep5 = $pdo->query($rep5Sql)->fetchAll();

// Report 6: Owner, slot, duration, and stay category (Multi-table Relational Projection)
$rep6Sql = "
    SELECT v.owner_name, v.vehicle_number,
           s.slot_id, s.slot_name,
           r.parking_date, r.hours_parked, r.stay_category
    FROM Parking_Record r
    JOIN Vehicle v ON r.vehicle_id = v.vehicle_id
    JOIN Parking_Slot s ON r.slot_id = s.slot_id
    ORDER BY r.parking_date DESC, r.record_id DESC
";
$rep6 = $pdo->query($rep6Sql)->fetchAll();

// Report 7: Records above the overall average duration (Subquery)
$overallAvgStmt = $pdo->query("SELECT COALESCE(AVG(hours_parked), 0) FROM Parking_Record");
$overallAvg = (float)$overallAvgStmt->fetchColumn();

$rep7Sql = "
    SELECT r.record_id, r.parking_date, r.hours_parked, r.stay_category,
           v.vehicle_number, v.owner_name,
           s.slot_id, s.slot_name,
           (SELECT ROUND(AVG(hours_parked), 2) FROM Parking_Record) AS overall_avg
    FROM Parking_Record r
    JOIN Vehicle v ON r.vehicle_id = v.vehicle_id
    JOIN Parking_Slot s ON r.slot_id = s.slot_id
    WHERE r.hours_parked > (SELECT AVG(hours_parked) FROM Parking_Record)
    ORDER BY r.hours_parked DESC
";
$rep7 = $pdo->query($rep7Sql)->fetchAll();

// Report 8: Payment details and total recorded payments (Aggregates & Details)
$rep8SummarySql = "
    SELECT COUNT(*) AS total_tx,
           COALESCE(SUM(amount), 0) AS total_revenue,
           COALESCE(AVG(amount), 0) AS avg_payment,
           COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN amount ELSE 0 END), 0) AS paid_revenue,
           COALESCE(SUM(CASE WHEN payment_status = 'Partial' THEN amount ELSE 0 END), 0) AS partial_revenue
    FROM Parking_Payment
";
$rep8Summary = $pdo->query($rep8SummarySql)->fetch();

$rep8Sql = "
    SELECT p.payment_id, p.amount, p.payment_date, p.payment_status,
           v.vehicle_id, v.vehicle_number, v.owner_name, v.vehicle_type,
           z.zone_name
    FROM Parking_Payment p
    JOIN Vehicle v ON p.vehicle_id = v.vehicle_id
    LEFT JOIN Parking_Zone z ON v.zone_id = z.zone_id
    ORDER BY p.payment_date DESC, p.payment_id DESC
";
$rep8 = $pdo->query($rep8Sql)->fetchAll();

// Report 9: Vehicles with Paid status (Join & Filter)
$rep9Sql = "
    SELECT DISTINCT v.vehicle_id, v.vehicle_number, v.owner_name, v.vehicle_type,
           p.payment_id, p.amount, p.payment_date, p.payment_status,
           z.zone_name
    FROM Vehicle v
    JOIN Parking_Payment p ON v.vehicle_id = p.vehicle_id
    LEFT JOIN Parking_Zone z ON v.zone_id = z.zone_id
    WHERE p.payment_status = 'Paid'
    ORDER BY p.payment_date DESC
";
$rep9 = $pdo->query($rep9Sql)->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Campus Parking Reports Suite
        </h2>
        <p class="text-muted mb-0">Advanced SQL queries demonstrating Relational Joins, Grouping, Aggregates, and Subqueries</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Report View
        </button>
    </div>
</div>

<!-- Nav Pills for the 9 Reports -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2">
        <ul class="nav nav-pills flex-column flex-md-row gap-1" id="reportTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep1' ? 'active' : '' ?>" href="reports.php?tab=rep1">
                    1. Vehicles &amp; Zones
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep2' ? 'active' : '' ?>" href="reports.php?tab=rep2">
                    2. Visits &gt; 4 Hours
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep3' ? 'active' : '' ?>" href="reports.php?tab=rep3">
                    3. Avg Duration / Slot
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep4' ? 'active' : '' ?>" href="reports.php?tab=rep4">
                    4. Longest Stay
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep5' ? 'active' : '' ?>" href="reports.php?tab=rep5">
                    5. Zone Counts (All)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep6' ? 'active' : '' ?>" href="reports.php?tab=rep6">
                    6. Owner &amp; Category
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep7' ? 'active' : '' ?>" href="reports.php?tab=rep7">
                    7. Above Avg Duration
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep8' ? 'active' : '' ?>" href="reports.php?tab=rep8">
                    8. Payment Totals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeReport === 'rep9' ? 'active' : '' ?>" href="reports.php?tab=rep9">
                    9. Paid Vehicles
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- ========================================== -->
<!-- REPORT 1: Vehicles and their zones -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep1'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-primary me-2">Report 1</span>Vehicles and Their Assigned Parking Zones
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Foreign Key Relational JOIN (<code>Vehicle</code> &amp; <code>Parking_Zone</code>)</span>
            </div>
            <span class="badge bg-primary-subtle text-primary border px-3 py-2"><?= count($rep1) ?> Vehicles</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep1Sql)) ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Vehicle ID</th>
                        <th>Registration No.</th>
                        <th>Owner Name</th>
                        <th>Vehicle Type</th>
                        <th>Registration Year</th>
                        <th>Zone Name</th>
                        <th>Zone Campus Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep1)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No vehicles found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep1 as $row): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= sanitize((string)$row['vehicle_id']) ?></td>
                                <td><span class="badge bg-dark-subtle text-dark border font-monospace"><?= sanitize($row['vehicle_number']) ?></span></td>
                                <td><strong><?= sanitize($row['owner_name']) ?></strong></td>
                                <td><?= sanitize($row['vehicle_type'] ?: '-') ?></td>
                                <td><?= sanitize((string)($row['registration_year'] ?: '-')) ?></td>
                                <td>
                                    <?php if ($row['zone_name']): ?>
                                        <span class="badge bg-primary-subtle text-primary border"><?= sanitize($row['zone_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">None Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= sanitize($row['location'] ?: 'Campus') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 2: Visits longer than 4 hours -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep2'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-warning text-dark me-2">Report 2</span>Parking Visits Longer Than Four Hours
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Filtering condition <code>WHERE hours_parked &gt; 4.00</code> with multi-table JOIN</span>
            </div>
            <span class="badge bg-warning-subtle text-warning border px-3 py-2"><?= count($rep2) ?> Qualifying Sessions</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep2Sql)) ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Vehicle</th>
                        <th>Owner</th>
                        <th>Slot</th>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>Category</th>
                        <th>Calculated Charge</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep2)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No parking visits exceeding 4 hours found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep2 as $row): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= sanitize((string)$row['record_id']) ?></td>
                                <td><span class="font-monospace fw-bold"><?= sanitize($row['vehicle_number']) ?></span></td>
                                <td><?= sanitize($row['owner_name']) ?></td>
                                <td><?= sanitize($row['slot_id']) ?> &mdash; <small class="text-muted"><?= sanitize($row['slot_name']) ?></small></td>
                                <td><?= sanitize($row['parking_date']) ?></td>
                                <td><strong class="text-danger"><?= number_format($row['hours_parked'], 2) ?> hrs</strong></td>
                                <td><?= get_stay_badge($row['stay_category']) ?></td>
                                <td class="fw-bold text-success"><?= format_currency((float)$row['estimated_charge']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 3: Average parking duration per slot -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep3'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-info text-dark me-2">Report 3</span>Average Parking Duration per Slot
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> SQL Aggregations (<code>AVG()</code>, <code>MIN()</code>, <code>MAX()</code>, <code>COUNT()</code>) with <code>GROUP BY</code></span>
            </div>
            <span class="badge bg-info-subtle text-info border px-3 py-2"><?= count($rep3) ?> Slots Analyzed</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep3Sql)) ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Slot ID</th>
                        <th>Slot Description</th>
                        <th>Zone</th>
                        <th>Tariff</th>
                        <th>Total Visits</th>
                        <th>Average Stay</th>
                        <th>Min Stay</th>
                        <th>Max Stay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep3)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No slot data available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep3 as $row): ?>
                            <tr>
                                <td><span class="badge bg-secondary font-monospace"><?= sanitize($row['slot_id']) ?></span></td>
                                <td><strong><?= sanitize($row['slot_name']) ?></strong></td>
                                <td><?= sanitize($row['zone_name'] ?: 'N/A') ?></td>
                                <td>₹<?= number_format($row['hourly_rate']) ?>/hr</td>
                                <td><span class="badge bg-light text-dark border"><?= $row['total_visits'] ?> visits</span></td>
                                <td><strong class="text-primary fs-6"><?= number_format($row['avg_duration'], 2) ?> hrs</strong></td>
                                <td class="text-muted"><?= number_format($row['min_duration'], 2) ?> hrs</td>
                                <td class="text-danger"><?= number_format($row['max_duration'], 2) ?> hrs</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 4: Longest recorded stay -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep4'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-danger text-white me-2">Report 4</span>Longest Recorded Stay in Campus History
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Subquery with Aggregate <code>WHERE hours_parked = (SELECT MAX(hours_parked) FROM Parking_Record)</code></span>
            </div>
            <span class="badge bg-danger-subtle text-danger border px-3 py-2">Campus Record</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep4Sql)) ?></pre>
            </details>
        </div>
        <div class="card-body">
            <?php if (empty($rep4)): ?>
                <div class="text-muted text-center py-4">No parking records logged yet.</div>
            <?php else: ?>
                <?php foreach ($rep4 as $row): ?>
                    <div class="p-4 rounded border bg-light mb-3">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center border-end">
                                <div class="display-4 fw-bold text-danger"><?= number_format($row['hours_parked'], 2) ?></div>
                                <div class="text-muted fw-semibold">HOURS PARKED</div>
                                <div class="mt-2"><?= get_stay_badge($row['stay_category']) ?></div>
                            </div>
                            <div class="col-md-9 ps-md-4 mt-3 mt-md-0">
                                <h4 class="fw-bold text-dark mb-1"><?= sanitize($row['vehicle_number']) ?> &bull; <?= sanitize($row['owner_name']) ?></h4>
                                <div class="text-muted mb-3"><?= sanitize($row['vehicle_type'] ?: 'Campus Vehicle') ?> &bull; Record ID #<?= sanitize((string)$row['record_id']) ?></div>
                                
                                <div class="row g-2 small">
                                    <div class="col-sm-4">
                                        <strong class="d-block text-muted">Parking Slot:</strong>
                                        <span class="badge bg-secondary font-monospace"><?= sanitize($row['slot_id']) ?></span> <?= sanitize($row['slot_name']) ?>
                                    </div>
                                    <div class="col-sm-4">
                                        <strong class="d-block text-muted">Date of Visit:</strong>
                                        <i class="bi bi-calendar3 me-1"></i><?= sanitize($row['parking_date']) ?>
                                    </div>
                                    <div class="col-sm-4">
                                        <strong class="d-block text-muted">Total Estimated Tariff:</strong>
                                        <span class="text-success fw-bold fs-6"><?= format_currency((float)$row['total_charge']) ?></span> (₹<?= number_format($row['hourly_rate']) ?>/hr)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 5: Vehicle counts by zone (all zones) -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep5'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-primary me-2">Report 5</span>Vehicle Counts by Zone (Including Zero-Vehicle Zones)
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Outer Join <code>LEFT JOIN</code> with <code>COUNT(v.vehicle_id)</code> to preserve empty sets</span>
            </div>
            <span class="badge bg-primary-subtle text-primary border px-3 py-2"><?= count($rep5) ?> Zones</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep5Sql)) ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Zone ID</th>
                        <th>Zone Name</th>
                        <th>Location</th>
                        <th>Assigned Vehicles</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep5)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No zones found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep5 as $row): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= sanitize((string)$row['zone_id']) ?></td>
                                <td><strong><?= sanitize($row['zone_name']) ?></strong></td>
                                <td><?= sanitize($row['location'] ?: 'Campus') ?></td>
                                <td>
                                    <span class="badge <?= $row['total_vehicles'] > 0 ? 'bg-primary' : 'bg-secondary' ?> fs-6">
                                        <?= $row['total_vehicles'] ?> vehicles
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['total_vehicles'] == 0): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning">
                                            <i class="bi bi-exclamation-circle me-1"></i>No Vehicles Assigned (0)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success">
                                            <i class="bi bi-check2 me-1"></i>Active Zone
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 6: Owner, slot, duration, stay category -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep6'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-secondary text-white me-2">Report 6</span>Owner, Slot, Duration, and Stay Category Summary
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Multi-entity Relational Projection across <code>Vehicle</code>, <code>Parking_Slot</code>, and <code>Parking_Record</code></span>
            </div>
            <span class="badge bg-secondary-subtle text-secondary border px-3 py-2"><?= count($rep6) ?> Entries</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep6Sql)) ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Owner Name</th>
                        <th>Vehicle Plate</th>
                        <th>Slot ID</th>
                        <th>Slot Name</th>
                        <th>Parking Date</th>
                        <th>Hours Parked</th>
                        <th>Stay Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep6)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep6 as $row): ?>
                            <tr>
                                <td><strong><?= sanitize($row['owner_name']) ?></strong></td>
                                <td><span class="badge bg-dark-subtle text-dark border font-monospace"><?= sanitize($row['vehicle_number']) ?></span></td>
                                <td><span class="badge bg-secondary font-monospace"><?= sanitize($row['slot_id']) ?></span></td>
                                <td><?= sanitize($row['slot_name']) ?></td>
                                <td><?= sanitize($row['parking_date']) ?></td>
                                <td><strong><?= number_format($row['hours_parked'], 2) ?> hrs</strong></td>
                                <td><?= get_stay_badge($row['stay_category']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 7: Records above overall average duration -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep7'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-dark text-white me-2">Report 7</span>Parking Visits Above the Overall Campus Average Duration
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Subquery Comparison <code>WHERE hours_parked &gt; (SELECT AVG(hours_parked) FROM Parking_Record)</code></span>
            </div>
            <div>
                <span class="badge bg-info-subtle text-info border me-2">Campus Avg: <?= number_format($overallAvg, 2) ?>h</span>
                <span class="badge bg-dark px-3 py-2"><?= count($rep7) ?> Above Average</span>
            </div>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep7Sql)) ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Vehicle</th>
                        <th>Owner</th>
                        <th>Slot</th>
                        <th>Parking Date</th>
                        <th>Hours Parked</th>
                        <th>Campus Benchmark</th>
                        <th>Classification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep7)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No records exceed the average duration.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep7 as $row): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= sanitize((string)$row['record_id']) ?></td>
                                <td><span class="font-monospace fw-bold"><?= sanitize($row['vehicle_number']) ?></span></td>
                                <td><?= sanitize($row['owner_name']) ?></td>
                                <td><?= sanitize($row['slot_id']) ?> &mdash; <small class="text-muted"><?= sanitize($row['slot_name']) ?></small></td>
                                <td><?= sanitize($row['parking_date']) ?></td>
                                <td><strong class="text-primary fs-6"><?= number_format($row['hours_parked'], 2) ?> hrs</strong></td>
                                <td>
                                    <span class="text-success small fw-semibold">
                                        +<?= number_format($row['hours_parked'] - $row['overall_avg'], 2) ?>h above avg (<?= number_format($row['overall_avg'], 2) ?>h)
                                    </span>
                                </td>
                                <td><?= get_stay_badge($row['stay_category']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 8: Payment details and total recorded payments -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep8'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-success me-2">Report 8</span>Payment Details &amp; Total Recorded Revenue
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Financial aggregation with <code>SUM(amount)</code> and conditional status aggregates</span>
            </div>
            <span class="badge bg-success-subtle text-success border px-3 py-2"><?= count($rep8) ?> Transactions</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep8Sql)) ?></pre>
            </details>
        </div>
        <div class="card-body border-bottom">
            <div class="row g-3 text-center">
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-white shadow-sm">
                        <div class="text-muted small">Total Recorded Revenue</div>
                        <div class="h3 fw-bold text-success mb-0"><?= format_currency((float)$rep8Summary['total_revenue']) ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-white shadow-sm">
                        <div class="text-muted small">Paid Status Total</div>
                        <div class="h4 fw-bold text-dark mb-0"><?= format_currency((float)$rep8Summary['paid_revenue']) ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-white shadow-sm">
                        <div class="text-muted small">Partial Balance Logged</div>
                        <div class="h4 fw-bold text-warning mb-0"><?= format_currency((float)$rep8Summary['partial_revenue']) ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 border rounded bg-white shadow-sm">
                        <div class="text-muted small">Average Transaction</div>
                        <div class="h4 fw-bold text-primary mb-0"><?= format_currency((float)$rep8Summary['avg_payment']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Vehicle</th>
                        <th>Owner</th>
                        <th>Assigned Zone</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep8)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No payments recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep8 as $row): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= sanitize((string)$row['payment_id']) ?></td>
                                <td><span class="font-monospace fw-bold"><?= sanitize($row['vehicle_number']) ?></span></td>
                                <td><?= sanitize($row['owner_name']) ?></td>
                                <td><?= sanitize($row['zone_name'] ?: '-') ?></td>
                                <td><?= sanitize($row['payment_date']) ?></td>
                                <td><strong class="text-success fs-6"><?= format_currency((float)$row['amount']) ?></strong></td>
                                <td><?= get_payment_badge($row['payment_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- REPORT 9: Vehicles with Paid status -->
<!-- ========================================== -->
<?php if ($activeReport === 'rep9'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <span class="badge bg-success me-2">Report 9</span>Vehicles with 'Paid' Payment Status
                </h5>
                <span class="text-muted small"><strong>DBMS Concept:</strong> Filtered Join <code>WHERE payment_status = 'Paid'</code> linking Vehicle and Payments</span>
            </div>
            <span class="badge bg-success px-3 py-2"><?= count($rep9) ?> Settled Entries</span>
        </div>
        <div class="card-body border-bottom bg-light py-2">
            <details>
                <summary class="small text-muted fw-semibold" style="cursor: pointer;"><i class="bi bi-code-slash me-1"></i>View Executed SQL Query</summary>
                <pre class="bg-dark text-white p-2 rounded small mt-2 mb-0"><?= trim(sanitize($rep9Sql)) ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Vehicle ID</th>
                        <th>Plate No.</th>
                        <th>Owner Name</th>
                        <th>Vehicle Type</th>
                        <th>Campus Zone</th>
                        <th>Payment Receipt</th>
                        <th>Amount Paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rep9)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No vehicles with Paid status recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rep9 as $row): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= sanitize((string)$row['vehicle_id']) ?></td>
                                <td><span class="badge bg-dark-subtle text-dark border font-monospace fs-6"><?= sanitize($row['vehicle_number']) ?></span></td>
                                <td><strong><?= sanitize($row['owner_name']) ?></strong></td>
                                <td><?= sanitize($row['vehicle_type'] ?: '-') ?></td>
                                <td><?= sanitize($row['zone_name'] ?: 'Campus') ?></td>
                                <td>#<?= sanitize((string)$row['payment_id']) ?> &bull; <small class="text-muted"><?= sanitize($row['payment_date']) ?></small></td>
                                <td><strong class="text-success fs-6"><?= format_currency((float)$row['amount']) ?></strong></td>
                                <td><?= get_payment_badge($row['payment_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
