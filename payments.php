<?php
/**
 * Parking Payments Management (CRUD)
 * Campus Parking Management System
 */

$pageTitle = 'Payments';
require_once __DIR__ . '/includes/header.php';

if (!$db_connected) {
    echo '<div class="alert alert-danger">Database connection required. Please visit <a href="setup.php">Setup</a>.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editPayment = null;
$errors = [];

// Fetch vehicles for dropdown
$vStmt = $pdo->query("SELECT vehicle_id, vehicle_number, owner_name FROM Vehicle ORDER BY vehicle_number ASC");
$allVehicles = $vStmt->fetchAll();

// Handle POST: Create, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : -1;
        $payment_date = trim($_POST['payment_date'] ?? '');
        $payment_status = trim($_POST['payment_status'] ?? 'Paid');

        if ($vehicle_id <= 0) {
            $errors[] = "Please select a registered vehicle.";
        }
        if ($amount <= 0) {
            $errors[] = "Payment amount must be greater than 0.00.";
        }
        if (empty($payment_date) || !strtotime($payment_date)) {
            $errors[] = "A valid payment date is required.";
        }
        if (empty($payment_status)) {
            $errors[] = "Payment status is required.";
        }

        if (empty($errors)) {
            try {
                $insertStmt = $pdo->prepare("
                    INSERT INTO Parking_Payment (vehicle_id, amount, payment_date, payment_status)
                    VALUES (:vid, :amt, :pdate, :status)
                ");
                $insertStmt->execute([
                    ':vid' => $vehicle_id,
                    ':amt' => $amount,
                    ':pdate' => $payment_date,
                    ':status' => $payment_status
                ]);
                set_flash('success', "Payment of " . format_currency($amount) . " recorded successfully with status '{$payment_status}'.");
                header('Location: payments.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($postAction === 'update') {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : -1;
        $payment_date = trim($_POST['payment_date'] ?? '');
        $payment_status = trim($_POST['payment_status'] ?? 'Paid');

        if ($vehicle_id <= 0) {
            $errors[] = "Please select a registered vehicle.";
        }
        if ($amount <= 0) {
            $errors[] = "Payment amount must be greater than 0.00.";
        }
        if (empty($payment_date) || !strtotime($payment_date)) {
            $errors[] = "A valid payment date is required.";
        }
        if (empty($payment_status)) {
            $errors[] = "Payment status is required.";
        }

        if (empty($errors)) {
            try {
                $updStmt = $pdo->prepare("
                    UPDATE Parking_Payment
                    SET vehicle_id = :vid, amount = :amt, payment_date = :pdate, payment_status = :status
                    WHERE payment_id = :pid
                ");
                $updStmt->execute([
                    ':vid' => $vehicle_id,
                    ':amt' => $amount,
                    ':pdate' => $payment_date,
                    ':status' => $payment_status,
                    ':pid' => $payment_id
                ]);
                set_flash('success', "Payment #{$payment_id} updated successfully.");
                header('Location: payments.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($postAction === 'delete') {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        try {
            $delStmt = $pdo->prepare("DELETE FROM Parking_Payment WHERE payment_id = :pid");
            $delStmt->execute([':pid' => $payment_id]);
            set_flash('success', "Payment record deleted successfully.");
            header('Location: payments.php');
            exit;
        } catch (PDOException $e) {
            set_flash('danger', "Error deleting payment: " . $e->getMessage());
            header('Location: payments.php');
            exit;
        }
    }
}

// Edit view
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM Parking_Payment WHERE payment_id = :pid");
    $stmt->execute([':pid' => $editId]);
    $editPayment = $stmt->fetch();
    if (!$editPayment) {
        set_flash('warning', "Payment record not found.");
        header('Location: payments.php');
        exit;
    }
}

// Filtering
$filterStatus = trim($_GET['status'] ?? '');
$filterVehicle = trim($_GET['vehicle_id'] ?? '');

$where = [];
$params = [];

if ($filterStatus !== '') {
    $where[] = "p.payment_status = :pstatus";
    $params[':pstatus'] = $filterStatus;
}
if ($filterVehicle !== '') {
    $where[] = "p.vehicle_id = :pvid";
    $params[':pvid'] = (int)$filterVehicle;
}

$paymentsSql = "
    SELECT p.payment_id, p.vehicle_id, p.amount, p.payment_date, p.payment_status,
           v.vehicle_number, v.owner_name, v.vehicle_type,
           z.zone_name
    FROM Parking_Payment p
    JOIN Vehicle v ON p.vehicle_id = v.vehicle_id
    LEFT JOIN Parking_Zone z ON v.zone_id = z.zone_id
";

if (!empty($where)) {
    $paymentsSql .= " WHERE " . implode(" AND ", $where);
}

$paymentsSql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

$paymentsStmt = $pdo->prepare($paymentsSql);
$paymentsStmt->execute($params);
$payments = $paymentsStmt->fetchAll();

// Calculate total for displayed list
$totalListAmount = 0.0;
foreach ($payments as $p) {
    $totalListAmount += (float)$p['amount'];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-cash-stack text-success me-2"></i>Parking Payments
        </h2>
        <p class="text-muted mb-0">Record manual parking charges, track settlements, and manage Paid & Partial statuses</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <?php if ($action !== 'create'): ?>
            <a href="payments.php?action=create" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i>Record Manual Payment
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= sanitize($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- Payment Form Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fw-bold">
                <?= $action === 'create' ? '<i class="bi bi-plus-circle text-success me-2"></i>Record New Manual Payment' : '<i class="bi bi-pencil-square text-warning me-2"></i>Edit Payment Transaction #' . sanitize((string)$editPayment['payment_id']) ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="payments.php">
                <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="payment_id" value="<?= sanitize((string)$editPayment['payment_id']) ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="vehicle_id" class="form-label fw-semibold">Payer / Vehicle <span class="text-danger">*</span></label>
                        <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                            <option value="">-- Select Registered Vehicle --</option>
                            <?php 
                                $currVid = (int)($editPayment['vehicle_id'] ?? $_POST['vehicle_id'] ?? 0);
                                foreach ($allVehicles as $v):
                            ?>
                                <option value="<?= $v['vehicle_id'] ?>" <?= $currVid === (int)$v['vehicle_id'] ? 'selected' : '' ?>>
                                    <?= sanitize($v['vehicle_number']) ?> &mdash; <?= sanitize($v['owner_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="amount" class="form-label fw-semibold">Payment Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="amount" name="amount" required
                                   min="0.01" step="0.01" placeholder="100.00"
                                   value="<?= sanitize((string)($editPayment['amount'] ?? $_POST['amount'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="payment_date" class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" required
                               value="<?= sanitize($editPayment['payment_date'] ?? $_POST['payment_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="payment_status" class="form-label fw-semibold">Settlement Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <?php 
                                $statuses = ['Paid', 'Partial', 'Pending'];
                                $currStatus = $editPayment['payment_status'] ?? $_POST['payment_status'] ?? 'Paid';
                                foreach ($statuses as $st):
                            ?>
                                <option value="<?= $st ?>" <?= $currStatus === $st ? 'selected' : '' ?>>
                                    <?= $st ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Manual campus receipt status (offline/cash/voucher settlement).</div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check2-circle me-1"></i><?= $action === 'create' ? 'Record Payment' : 'Update Payment' ?>
                    </button>
                    <a href="payments.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Filtering Bar -->
<div class="card border-0 shadow-sm mb-4 bg-white">
    <div class="card-body">
        <form method="GET" action="payments.php" class="row g-2 align-items-end">
            <div class="col-md-4 col-sm-6">
                <label for="filter_status" class="form-label small fw-semibold text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" id="filter_status" name="status">
                    <option value="">All Statuses</option>
                    <option value="Paid" <?= $filterStatus === 'Paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="Partial" <?= $filterStatus === 'Partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-5 col-sm-6">
                <label for="filter_p_vehicle" class="form-label small fw-semibold text-muted mb-1">Vehicle</label>
                <select class="form-select form-select-sm" id="filter_p_vehicle" name="vehicle_id">
                    <option value="">All Vehicles</option>
                    <?php foreach ($allVehicles as $v): ?>
                        <option value="<?= $v['vehicle_id'] ?>" <?= $filterVehicle === (string)$v['vehicle_id'] ? 'selected' : '' ?>>
                            <?= sanitize($v['vehicle_number']) ?> (<?= sanitize($v['owner_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-12 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                <?php if ($filterStatus !== '' || $filterVehicle !== ''): ?>
                    <a href="payments.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Payments Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 fw-bold">Payments Ledger</h5>
            <small class="text-muted">Filtered Total: <strong class="text-success"><?= format_currency($totalListAmount) ?></strong></small>
        </div>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
            <?= count($payments) ?> Transactions
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 80px;">Receipt #</th>
                    <th>Vehicle</th>
                    <th>Owner</th>
                    <th>Payment Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th style="width: 130px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No payment entries found matching the criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?= sanitize((string)$p['payment_id']) ?></td>
                            <td>
                                <strong class="text-dark font-monospace"><?= sanitize($p['vehicle_number']) ?></strong>
                                <span class="badge bg-light text-muted border ms-1"><?= sanitize($p['vehicle_type'] ?: 'Vehicle') ?></span>
                            </td>
                            <td><?= sanitize($p['owner_name']) ?></td>
                            <td><?= sanitize($p['payment_date']) ?></td>
                            <td>
                                <strong class="text-success fs-6"><?= format_currency((float)$p['amount']) ?></strong>
                            </td>
                            <td><?= get_payment_badge($p['payment_status']) ?></td>
                            <td class="text-end">
                                <a href="payments.php?action=edit&id=<?= $p['payment_id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="payments.php" class="d-inline"
                                      onsubmit="return confirm('Are you sure you want to delete payment record #<?= $p['payment_id'] ?> of <?= format_currency((float)$p['amount']) ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete payment">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
