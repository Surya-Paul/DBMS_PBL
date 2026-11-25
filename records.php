<?php
/**
 * Parking Records Management (CRUD)
 * Campus Parking Management System
 */

$pageTitle = 'Parking Records';
require_once __DIR__ . '/includes/header.php';

if (!$db_connected) {
    echo '<div class="alert alert-danger">Database connection required. Please visit <a href="setup.php">Setup</a>.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editRecord = null;
$errors = [];

// Fetch Vehicles for dropdown
$vStmt = $pdo->query("SELECT vehicle_id, vehicle_number, owner_name, vehicle_type FROM Vehicle ORDER BY vehicle_number ASC");
$allVehicles = $vStmt->fetchAll();

// Fetch Slots for dropdown
$sStmt = $pdo->query("SELECT slot_id, slot_name, hourly_rate FROM Parking_Slot ORDER BY slot_id ASC");
$allSlots = $sStmt->fetchAll();

// Handle POST: Create, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $slot_id = trim($_POST['slot_id'] ?? '');
        $parking_date = trim($_POST['parking_date'] ?? '');
        $hours_parked = isset($_POST['hours_parked']) ? (float)$_POST['hours_parked'] : -1;

        if ($vehicle_id <= 0) {
            $errors[] = "Please select a registered vehicle.";
        }
        if (empty($slot_id)) {
            $errors[] = "Please select a parking slot.";
        }
        if (empty($parking_date) || !strtotime($parking_date)) {
            $errors[] = "A valid parking date is required.";
        }
        if ($hours_parked < 0.0 || $hours_parked > 24.0) {
            $errors[] = "Hours parked must be between 0.00 and 24.00 hours.";
        }

        // Automatic category determination based on requirement:
        // Short: < 4 hours | Medium: 4 to < 8 hours | Long: >= 8 hours
        $stay_category = calculate_stay_category($hours_parked);

        if (empty($errors)) {
            // Check unique constraint: (vehicle_id, slot_id, parking_date)
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM Parking_Record 
                WHERE vehicle_id = :vid AND slot_id = :sid AND parking_date = :pdate
            ");
            $checkStmt->execute([
                ':vid' => $vehicle_id,
                ':sid' => $slot_id,
                ':pdate' => $parking_date
            ]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "Duplicate record: A parking record for this vehicle in slot '{$slot_id}' on date {$parking_date} already exists.";
            } else {
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO Parking_Record (vehicle_id, slot_id, parking_date, hours_parked, stay_category)
                        VALUES (:vid, :sid, :pdate, :hours, :category)
                    ");
                    $insertStmt->execute([
                        ':vid' => $vehicle_id,
                        ':sid' => $slot_id,
                        ':pdate' => $parking_date,
                        ':hours' => $hours_parked,
                        ':category' => $stay_category
                    ]);
                    set_flash('success', "Parking record created successfully with '{$stay_category}' stay classification.");
                    header('Location: records.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($postAction === 'update') {
        $record_id = (int)($_POST['record_id'] ?? 0);
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $slot_id = trim($_POST['slot_id'] ?? '');
        $parking_date = trim($_POST['parking_date'] ?? '');
        $hours_parked = isset($_POST['hours_parked']) ? (float)$_POST['hours_parked'] : -1;

        if ($vehicle_id <= 0) {
            $errors[] = "Please select a registered vehicle.";
        }
        if (empty($slot_id)) {
            $errors[] = "Please select a parking slot.";
        }
        if (empty($parking_date) || !strtotime($parking_date)) {
            $errors[] = "A valid parking date is required.";
        }
        if ($hours_parked < 0.0 || $hours_parked > 24.0) {
            $errors[] = "Hours parked must be between 0.00 and 24.00 hours.";
        }

        $stay_category = calculate_stay_category($hours_parked);

        if (empty($errors)) {
            // Check unique constraint excluding this record
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM Parking_Record 
                WHERE vehicle_id = :vid AND slot_id = :sid AND parking_date = :pdate AND record_id != :rid
            ");
            $checkStmt->execute([
                ':vid' => $vehicle_id,
                ':sid' => $slot_id,
                ':pdate' => $parking_date,
                ':rid' => $record_id
            ]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "Duplicate record: Another parking entry for this vehicle in slot '{$slot_id}' on date {$parking_date} already exists.";
            } else {
                try {
                    $updStmt = $pdo->prepare("
                        UPDATE Parking_Record 
                        SET vehicle_id = :vid, slot_id = :sid, parking_date = :pdate, 
                            hours_parked = :hours, stay_category = :category
                        WHERE record_id = :rid
                    ");
                    $updStmt->execute([
                        ':vid' => $vehicle_id,
                        ':sid' => $slot_id,
                        ':pdate' => $parking_date,
                        ':hours' => $hours_parked,
                        ':category' => $stay_category,
                        ':rid' => $record_id
                    ]);
                    set_flash('success', "Parking record #{$record_id} updated successfully.");
                    header('Location: records.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $record_id = (int)($_POST['record_id'] ?? 0);
        try {
            $delStmt = $pdo->prepare("DELETE FROM Parking_Record WHERE record_id = :rid");
            $delStmt->execute([':rid' => $record_id]);
            set_flash('success', "Parking record deleted successfully.");
            header('Location: records.php');
            exit;
        } catch (PDOException $e) {
            set_flash('danger', "Error deleting record: " . $e->getMessage());
            header('Location: records.php');
            exit;
        }
    }
}

// Edit view
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM Parking_Record WHERE record_id = :rid");
    $stmt->execute([':rid' => $editId]);
    $editRecord = $stmt->fetch();
    if (!$editRecord) {
        set_flash('warning', "Parking record not found.");
        header('Location: records.php');
        exit;
    }
}

// Filter parameters
$filterCategory = trim($_GET['category'] ?? '');
$filterDate = trim($_GET['date'] ?? '');
$filterVehicle = trim($_GET['vehicle_id'] ?? '');

$where = [];
$params = [];

if ($filterCategory !== '') {
    $where[] = "r.stay_category = :cat";
    $params[':cat'] = $filterCategory;
}
if ($filterDate !== '') {
    $where[] = "r.parking_date = :pdate";
    $params[':pdate'] = $filterDate;
}
if ($filterVehicle !== '') {
    $where[] = "r.vehicle_id = :fvid";
    $params[':fvid'] = (int)$filterVehicle;
}

$recordsSql = "
    SELECT r.record_id, r.vehicle_id, r.slot_id, r.parking_date, r.hours_parked, r.stay_category,
           v.vehicle_number, v.owner_name, v.vehicle_type,
           s.slot_name, s.hourly_rate,
           z.zone_name
    FROM Parking_Record r
    JOIN Vehicle v ON r.vehicle_id = v.vehicle_id
    JOIN Parking_Slot s ON r.slot_id = s.slot_id
    LEFT JOIN Parking_Zone z ON s.zone_id = z.zone_id
";

if (!empty($where)) {
    $recordsSql .= " WHERE " . implode(" AND ", $where);
}

$recordsSql .= " ORDER BY r.parking_date DESC, r.record_id DESC";

$recordsStmt = $pdo->prepare($recordsSql);
$recordsStmt->execute($params);
$records = $recordsStmt->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-clock-history text-primary me-2"></i>Parking Records
        </h2>
        <p class="text-muted mb-0">Record vehicle parking sessions, calculate duration categories, and estimate charges</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <?php if ($action !== 'create'): ?>
            <a href="records.php?action=create" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New Parking Entry
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
    <!-- Record Form Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fw-bold">
                <?= $action === 'create' ? '<i class="bi bi-plus-circle text-primary me-2"></i>Log Parking Session' : '<i class="bi bi-pencil-square text-warning me-2"></i>Edit Parking Record #' . sanitize((string)$editRecord['record_id']) ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="records.php">
                <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="record_id" value="<?= sanitize((string)$editRecord['record_id']) ?>">
                <?php endif; ?>
                <input type="hidden" id="stay_category" name="stay_category" value="<?= sanitize($editRecord['stay_category'] ?? 'Short') ?>">

                <div class="row g-3">
                    <!-- Vehicle Select -->
                    <div class="col-md-6">
                        <label for="vehicle_id" class="form-label fw-semibold">Select Vehicle <span class="text-danger">*</span></label>
                        <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                            <option value="">-- Choose Registered Vehicle --</option>
                            <?php 
                                $currVid = (int)($editRecord['vehicle_id'] ?? $_POST['vehicle_id'] ?? 0);
                                foreach ($allVehicles as $v):
                            ?>
                                <option value="<?= $v['vehicle_id'] ?>" <?= $currVid === (int)$v['vehicle_id'] ? 'selected' : '' ?>>
                                    <?= sanitize($v['vehicle_number']) ?> &mdash; <?= sanitize($v['owner_name']) ?> (<?= sanitize($v['vehicle_type'] ?: 'Vehicle') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Don't see your vehicle? <a href="vehicles.php?action=create" target="_blank">Register vehicle first</a>.</div>
                    </div>

                    <!-- Slot Select with data-rate -->
                    <div class="col-md-6">
                        <label for="slot_id" class="form-label fw-semibold">Select Parking Slot <span class="text-danger">*</span></label>
                        <select class="form-select" id="slot_id" name="slot_id" required>
                            <option value="" data-rate="0">-- Choose Parking Slot --</option>
                            <?php 
                                $currSlot = $editRecord['slot_id'] ?? $_POST['slot_id'] ?? '';
                                foreach ($allSlots as $s):
                            ?>
                                <option value="<?= sanitize($s['slot_id']) ?>" data-rate="<?= (float)$s['hourly_rate'] ?>" <?= $currSlot === $s['slot_id'] ? 'selected' : '' ?>>
                                    <?= sanitize($s['slot_id']) ?> &mdash; <?= sanitize($s['slot_name']) ?> (₹<?= number_format($s['hourly_rate']) ?>/hr)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Parking Date -->
                    <div class="col-md-4">
                        <label for="parking_date" class="form-label fw-semibold">Parking Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="parking_date" name="parking_date" required
                               value="<?= sanitize($editRecord['parking_date'] ?? $_POST['parking_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <!-- Hours Parked (0 to 24) -->
                    <div class="col-md-4">
                        <label for="hours_parked" class="form-label fw-semibold">Hours Parked (0 - 24) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="hours_parked" name="hours_parked" required
                                   min="0" max="24" step="0.25" placeholder="e.g. 2.50"
                                   value="<?= sanitize((string)($editRecord['hours_parked'] ?? $_POST['hours_parked'] ?? '1.00')) ?>">
                            <span class="input-group-text">hours</span>
                        </div>
                        <div class="form-text">Constrained to 0.00 through 24.00 hours.</div>
                    </div>

                    <!-- Dynamic Live Calculation Box -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted">Automatic Calculation</label>
                        <div class="calc-preview-box p-3 rounded">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted">Stay Classification:</span>
                                <span id="stay_category_display">
                                    <span class="badge bg-info text-dark"><i class="bi bi-clock me-1"></i>Short Stay</span>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted">Estimated Tariff:</span>
                                <span id="estimated_charge_display" class="text-success fs-6">₹0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle me-1"></i><?= $action === 'create' ? 'Save Parking Record' : 'Update Record' ?>
                    </button>
                    <a href="records.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Filtering Bar -->
<div class="card border-0 shadow-sm mb-4 bg-white">
    <div class="card-body">
        <form method="GET" action="records.php" class="row g-2 align-items-end">
            <div class="col-md-3 col-sm-6">
                <label for="category" class="form-label small fw-semibold text-muted mb-1">Stay Category</label>
                <select class="form-select form-select-sm" id="category" name="category">
                    <option value="">All Categories</option>
                    <option value="Short" <?= $filterCategory === 'Short' ? 'selected' : '' ?>>Short (&lt;4h)</option>
                    <option value="Medium" <?= $filterCategory === 'Medium' ? 'selected' : '' ?>>Medium (4-8h)</option>
                    <option value="Long" <?= $filterCategory === 'Long' ? 'selected' : '' ?>>Long (&ge;8h)</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label for="date" class="form-label small fw-semibold text-muted mb-1">Date</label>
                <input type="date" class="form-control form-control-sm" id="date" name="date" value="<?= sanitize($filterDate) ?>">
            </div>
            <div class="col-md-4 col-sm-6">
                <label for="filter_vehicle" class="form-label small fw-semibold text-muted mb-1">Vehicle</label>
                <select class="form-select form-select-sm" id="filter_vehicle" name="vehicle_id">
                    <option value="">All Vehicles</option>
                    <?php foreach ($allVehicles as $v): ?>
                        <option value="<?= $v['vehicle_id'] ?>" <?= $filterVehicle === (string)$v['vehicle_id'] ? 'selected' : '' ?>>
                            <?= sanitize($v['vehicle_number']) ?> (<?= sanitize($v['owner_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                <?php if ($filterCategory !== '' || $filterDate !== '' || $filterVehicle !== ''): ?>
                    <a href="records.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Parking Records Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold">Parking Sessions Log</h5>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            <?= count($records) ?> Records
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Vehicle</th>
                    <th>Slot & Zone</th>
                    <th>Parking Date</th>
                    <th>Duration</th>
                    <th>Category</th>
                    <th>Est. Charge</th>
                    <th style="width: 130px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No parking records found matching the criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <?php $estCharge = estimate_parking_charge((float)$r['hours_parked'], (float)$r['hourly_rate']); ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?= sanitize((string)$r['record_id']) ?></td>
                            <td>
                                <strong class="text-dark font-monospace"><?= sanitize($r['vehicle_number']) ?></strong>
                                <div class="text-muted small"><?= sanitize($r['owner_name']) ?> &bull; <?= sanitize($r['vehicle_type'] ?: 'Car') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= sanitize($r['slot_id']) ?></span>
                                <div class="text-muted small"><?= sanitize($r['slot_name']) ?></div>
                                <?php if ($r['zone_name']): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><i class="bi bi-geo-alt"></i> <?= sanitize($r['zone_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($r['parking_date']) ?></td>
                            <td>
                                <strong><?= number_format($r['hours_parked'], 2) ?> hrs</strong>
                            </td>
                            <td><?= get_stay_badge($r['stay_category']) ?></td>
                            <td>
                                <span class="fw-bold text-success"><?= format_currency($estCharge) ?></span>
                                <div class="text-muted" style="font-size: 0.72rem;">@ ₹<?= number_format($r['hourly_rate']) ?>/hr</div>
                            </td>
                            <td class="text-end">
                                <a href="records.php?action=edit&id=<?= $r['record_id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="records.php" class="d-inline"
                                      onsubmit="return confirm('Are you sure you want to delete parking record #<?= $r['record_id'] ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete record">
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
