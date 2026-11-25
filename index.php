<?php
/**
 * Dashboard: Overview & Analytics
 * Campus Parking Management System
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$stats = [
    'zones' => 0,
    'vehicles' => 0,
    'slots' => 0,
    'records' => 0,
    'payments_count' => 0,
    'revenue_total' => 0.0,
    'avg_duration' => 0.0,
    'max_duration' => 0.0,
];

$zoneSummaries = [];
$stayBreakdown = [];
$paymentSummaries = [];
$recentRecords = [];
$recentPayments = [];

if ($db_connected && $pdo) {
    try {
        // Core metric totals
        $stats['zones'] = (int)$pdo->query("SELECT COUNT(*) FROM Parking_Zone")->fetchColumn();
        $stats['vehicles'] = (int)$pdo->query("SELECT COUNT(*) FROM Vehicle")->fetchColumn();
        $stats['slots'] = (int)$pdo->query("SELECT COUNT(*) FROM Parking_Slot")->fetchColumn();
        $stats['records'] = (int)$pdo->query("SELECT COUNT(*) FROM Parking_Record")->fetchColumn();
        
        $payStmt = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total FROM Parking_Payment");
        $payRow = $payStmt->fetch();
        $stats['payments_count'] = (int)($payRow['cnt'] ?? 0);
        $stats['revenue_total'] = (float)($payRow['total'] ?? 0);

        // Duration metrics
        $durStmt = $pdo->query("SELECT COALESCE(AVG(hours_parked), 0) AS avg_dur, COALESCE(MAX(hours_parked), 0) AS max_dur FROM Parking_Record");
        $durRow = $durStmt->fetch();
        $stats['avg_duration'] = (float)($durRow['avg_dur'] ?? 0);
        $stats['max_duration'] = (float)($durRow['max_dur'] ?? 0);

        // Summary 1: Zone Vehicle Counts
        $zoneStmt = $pdo->query("
            SELECT z.zone_id, z.zone_name, z.location, COUNT(v.vehicle_id) AS vehicle_count
            FROM Parking_Zone z
            LEFT JOIN Vehicle v ON z.zone_id = v.zone_id
            GROUP BY z.zone_id, z.zone_name, z.location
            ORDER BY vehicle_count DESC, z.zone_name ASC
        ");
        $zoneSummaries = $zoneStmt->fetchAll();

        // Summary 2: Parking Durations by Stay Category
        $stayStmt = $pdo->query("
            SELECT stay_category, COUNT(*) AS count, COALESCE(AVG(hours_parked), 0) AS avg_hours
            FROM Parking_Record
            GROUP BY stay_category
            ORDER BY FIELD(stay_category, 'Short', 'Medium', 'Long')
        ");
        $stayBreakdown = $stayStmt->fetchAll();

        // Summary 3: Revenue by Payment Status
        $payStatusStmt = $pdo->query("
            SELECT payment_status, COUNT(*) AS tx_count, COALESCE(SUM(amount), 0) AS total_amount
            FROM Parking_Payment
            GROUP BY payment_status
        ");
        $paymentSummaries = $payStatusStmt->fetchAll();

        // Recent 5 Parking Records with details
        $recStmt = $pdo->query("
            SELECT r.record_id, r.parking_date, r.hours_parked, r.stay_category,
                   v.vehicle_number, v.owner_name,
                   s.slot_id, s.slot_name, s.hourly_rate
            FROM Parking_Record r
            JOIN Vehicle v ON r.vehicle_id = v.vehicle_id
            JOIN Parking_Slot s ON r.slot_id = s.slot_id
            ORDER BY r.parking_date DESC, r.record_id DESC
            LIMIT 5
        ");
        $recentRecords = $recStmt->fetchAll();

        // Recent 5 Payments
        $pStmt = $pdo->query("
            SELECT p.payment_id, p.amount, p.payment_date, p.payment_status,
                   v.vehicle_number, v.owner_name
            FROM Parking_Payment p
            JOIN Vehicle v ON p.vehicle_id = v.vehicle_id
            ORDER BY p.payment_date DESC, p.payment_id DESC
            LIMIT 5
        ");
        $recentPayments = $pStmt->fetchAll();

    } catch (PDOException $e) {
        $queryError = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">Campus Parking Dashboard</h2>
        <p class="text-muted mb-0">Live operations, capacity utilization, and financial revenue tracking</p>
    </div>
    <div class="d-flex gap-2">
        <a href="records.php?action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>New Parking Record
        </a>
        <a href="payments.php?action=create" class="btn btn-success">
            <i class="bi bi-cash me-1"></i>Record Payment
        </a>
    </div>
</div>

<?php if (isset($queryError)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>Could not load all dashboard metrics: <?= sanitize($queryError) ?>
    </div>
<?php endif; ?>

<!-- 5 Main Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card border-0 h-100 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary-subtle text-primary me-3">
                    <i class="bi bi-car-front-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Vehicles</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stats['vehicles']) ?></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2">
                <a href="vehicles.php" class="text-decoration-none small text-primary fw-semibold">Manage &rarr;</a>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card border-0 h-100 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info-subtle text-info me-3">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Parking Zones</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stats['zones']) ?></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2">
                <a href="zones.php" class="text-decoration-none small text-info fw-semibold">Manage &rarr;</a>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card stat-card border-0 h-100 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-secondary-subtle text-secondary me-3">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Active Slots</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stats['slots']) ?></div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2">
                <a href="slots.php" class="text-decoration-none small text-secondary fw-semibold">Manage &rarr;</a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 col-sm-6">
        <div class="card stat-card border-0 h-100 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning-subtle text-warning me-3">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Parking Records</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($stats['records']) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Avg: <?= number_format($stats['avg_duration'], 2) ?>h | Max: <?= number_format($stats['max_duration'], 2) ?>h</div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2">
                <a href="records.php" class="text-decoration-none small text-warning fw-semibold">View logs &rarr;</a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 col-sm-12">
        <div class="card stat-card border-0 h-100 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success-subtle text-success me-3">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Revenue</div>
                    <div class="fs-4 fw-bold text-success"><?= format_currency($stats['revenue_total']) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= number_format($stats['payments_count']) ?> recorded transactions</div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2">
                <a href="payments.php" class="text-decoration-none small text-success fw-semibold">View ledger &rarr;</a>
            </div>
        </div>
    </div>
</div>

<!-- Summaries Section: Zone Counts, Duration Breakdown, Revenue Breakdown -->
<div class="row g-4 mb-4">
    <!-- Zone Vehicle Distribution -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold">
                    <i class="bi bi-pin-map-fill text-primary me-2"></i>Vehicles by Zone
                </h6>
                <a href="reports.php#rep-zones" class="btn btn-sm btn-link text-decoration-none p-0">All Zones</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($zoneSummaries)): ?>
                        <li class="list-group-item text-muted text-center py-3">No zones registered.</li>
                    <?php else: ?>
                        <?php foreach ($zoneSummaries as $zone): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                                <div>
                                    <div class="fw-semibold text-dark"><?= sanitize($zone['zone_name']) ?></div>
                                    <small class="text-muted"><?= sanitize($zone['location'] ?: 'Campus') ?></small>
                                </div>
                                <span class="badge <?= $zone['vehicle_count'] > 0 ? 'bg-primary' : 'bg-secondary' ?> rounded-pill">
                                    <?= $zone['vehicle_count'] ?> vehicles
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Parking Duration & Stay Classification -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold">
                    <i class="bi bi-hourglass-split text-warning me-2"></i>Duration Classification
                </h6>
                <span class="badge bg-light text-muted border">0h - 24h</span>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3 g-2">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Campus Average</div>
                            <div class="h5 fw-bold text-primary mb-0"><?= number_format($stats['avg_duration'], 2) ?> hrs</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Longest Stay</div>
                            <div class="h5 fw-bold text-danger mb-0"><?= number_format($stats['max_duration'], 2) ?> hrs</div>
                        </div>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    <?php if (empty($stayBreakdown)): ?>
                        <div class="text-muted text-center py-3">No parking records logged.</div>
                    <?php else: ?>
                        <?php foreach ($stayBreakdown as $sb): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <?= get_stay_badge($sb['stay_category']) ?>
                                    <span class="text-muted small ms-1">(avg <?= number_format($sb['avg_hours'], 1) ?>h)</span>
                                </div>
                                <span class="fw-bold"><?= $sb['count'] ?> records</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Status Breakdown -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold">
                    <i class="bi bi-pie-chart text-success me-2"></i>Recorded Payments
                </h6>
                <a href="payments.php" class="btn btn-sm btn-link text-decoration-none p-0">View All</a>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <div class="text-muted small">Gross Collection</div>
                        <div class="h4 fw-bold text-success mb-0"><?= format_currency($stats['revenue_total']) ?></div>
                    </div>
                    <div class="badge bg-success-subtle text-success p-2 fs-6">
                        <?= $stats['payments_count'] ?> Transactions
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    <?php if (empty($paymentSummaries)): ?>
                        <div class="text-muted text-center py-2">No payments recorded.</div>
                    <?php else: ?>
                        <?php foreach ($paymentSummaries as $ps): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <?= get_payment_badge($ps['payment_status']) ?>
                                    <span class="text-muted small ms-1"><?= $ps['tx_count'] ?> transactions</span>
                                </div>
                                <div class="fw-bold text-dark"><?= format_currency((float)$ps['total_amount']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Tables -->
<div class="row g-4">
    <!-- Recent Parking Stays -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold">
                    <i class="bi bi-clock-history text-primary me-2"></i>Recent Parking Records
                </h6>
                <a href="records.php" class="btn btn-sm btn-outline-primary">View All Records</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Slot</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentRecords)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No parking visits logged yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentRecords as $rec): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= sanitize($rec['vehicle_number']) ?></div>
                                        <div class="text-muted small"><?= sanitize($rec['owner_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace"><?= sanitize($rec['slot_id']) ?></span>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= sanitize($rec['slot_name']) ?></div>
                                    </td>
                                    <td><?= sanitize($rec['parking_date']) ?></td>
                                    <td>
                                        <strong><?= number_format($rec['hours_parked'], 2) ?> hrs</strong>
                                        <div class="text-muted small">Est: <?= format_currency(estimate_parking_charge($rec['hours_parked'], $rec['hourly_rate'])) ?></div>
                                    </td>
                                    <td><?= get_stay_badge($rec['stay_category']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold">
                    <i class="bi bi-receipt text-success me-2"></i>Recent Payments
                </h6>
                <a href="payments.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No payments logged yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= sanitize($p['vehicle_number']) ?></div>
                                        <div class="text-muted small"><?= sanitize($p['owner_name']) ?></div>
                                    </td>
                                    <td class="small"><?= sanitize($p['payment_date']) ?></td>
                                    <td class="fw-bold text-success"><?= format_currency((float)$p['amount']) ?></td>
                                    <td><?= get_payment_badge($p['payment_status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
