<?php
/**
 * Parking Slot Management (CRUD)
 * Campus Parking Management System
 */

$pageTitle = 'Parking Slots';
require_once __DIR__ . '/includes/header.php';

if (!$db_connected) {
    echo '<div class="alert alert-danger">Database connection required. Please visit <a href="setup.php">Setup</a>.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$action = $_GET['action'] ?? 'list';
$editId = trim($_GET['id'] ?? '');
$editSlot = null;
$errors = [];

// Fetch zones for slot assignment dropdown
$zonesStmt = $pdo->query("SELECT zone_id, zone_name FROM Parking_Zone ORDER BY zone_name ASC");
$allZones = $zonesStmt->fetchAll();

// Handle POST actions: Create, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $slot_id = strtoupper(trim($_POST['slot_id'] ?? ''));
        $slot_name = trim($_POST['slot_name'] ?? '');
        $hourly_rate = isset($_POST['hourly_rate']) ? (int)$_POST['hourly_rate'] : 20;
        $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;

        if (empty($slot_id)) {
            $errors[] = "Slot Identifier (e.g. S-A01) is required.";
        }
        if (strlen($slot_id) > 10) {
            $errors[] = "Slot ID cannot exceed 10 characters.";
        }
        if (empty($slot_name)) {
            $errors[] = "Slot Name is required.";
        }
        if ($hourly_rate < 0) {
            $errors[] = "Hourly Rate must be a non-negative number.";
        }

        if (empty($errors)) {
            // Check unique slot_id
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Parking_Slot WHERE slot_id = :id");
            $checkStmt->execute([':id' => $slot_id]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "A slot with ID '{$slot_id}' already exists.";
            } else {
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO Parking_Slot (slot_id, slot_name, hourly_rate, zone_id)
                        VALUES (:id, :name, :rate, :zid)
                    ");
                    $insertStmt->execute([
                        ':id' => $slot_id,
                        ':name' => $slot_name,
                        ':rate' => $hourly_rate,
                        ':zid' => $zone_id
                    ]);
                    set_flash('success', "Parking Slot '{$slot_id}' added successfully.");
                    header('Location: slots.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($postAction === 'update') {
        $slot_id = strtoupper(trim($_POST['slot_id'] ?? ''));
        $slot_name = trim($_POST['slot_name'] ?? '');
        $hourly_rate = isset($_POST['hourly_rate']) ? (int)$_POST['hourly_rate'] : 20;
        $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;

        if (empty($slot_name)) {
            $errors[] = "Slot Name is required.";
        }
        if ($hourly_rate < 0) {
            $errors[] = "Hourly Rate must be a non-negative number.";
        }

        if (empty($errors)) {
            try {
                $updStmt = $pdo->prepare("
                    UPDATE Parking_Slot 
                    SET slot_name = :name, hourly_rate = :rate, zone_id = :zid
                    WHERE slot_id = :id
                ");
                $updStmt->execute([
                    ':name' => $slot_name,
                    ':rate' => $hourly_rate,
                    ':zid' => $zone_id,
                    ':id' => $slot_id
                ]);
                set_flash('success', "Parking Slot '{$slot_id}' updated successfully.");
                header('Location: slots.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($postAction === 'delete') {
        $slot_id = trim($_POST['slot_id'] ?? '');
        $depCheck = check_slot_dependencies($pdo, $slot_id);

        if (!$depCheck['can_delete']) {
            set_flash('danger', $depCheck['message']);
            header('Location: slots.php');
            exit;
        }

        try {
            $delStmt = $pdo->prepare("DELETE FROM Parking_Slot WHERE slot_id = :id");
            $delStmt->execute([':id' => $slot_id]);
            set_flash('success', "Parking Slot '{$slot_id}' deleted successfully.");
            header('Location: slots.php');
            exit;
        } catch (PDOException $e) {
            set_flash('danger', "Cannot delete slot: " . $e->getMessage());
            header('Location: slots.php');
            exit;
        }
    }
}

// Edit view
if ($action === 'edit' && $editId !== '') {
    $stmt = $pdo->prepare("SELECT * FROM Parking_Slot WHERE slot_id = :id");
    $stmt->execute([':id' => $editId]);
    $editSlot = $stmt->fetch();
    if (!$editSlot) {
        set_flash('warning', "Parking Slot not found.");
        header('Location: slots.php');
        exit;
    }
}

// Fetch all slots with zone and record usage count
$slotsStmt = $pdo->query("
    SELECT s.slot_id, s.slot_name, s.hourly_rate, s.zone_id,
           z.zone_name, z.location AS zone_location,
           COUNT(r.record_id) AS usage_count
    FROM Parking_Slot s
    LEFT JOIN Parking_Zone z ON s.zone_id = z.zone_id
    LEFT JOIN Parking_Record r ON s.slot_id = r.slot_id
    GROUP BY s.slot_id, s.slot_name, s.hourly_rate, s.zone_id, z.zone_name, z.location
    ORDER BY s.slot_id ASC
");
$slots = $slotsStmt->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Parking Slots
        </h2>
        <p class="text-muted mb-0">Configure physical parking bays, assign zones, and maintain hourly billing tariffs</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <?php if ($action !== 'create'): ?>
            <a href="slots.php?action=create" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add New Slot
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
    <!-- Form Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fw-bold">
                <?= $action === 'create' ? '<i class="bi bi-plus-circle text-primary me-2"></i>Add New Parking Slot' : '<i class="bi bi-pencil-square text-warning me-2"></i>Edit Slot: ' . sanitize($editSlot['slot_id']) ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="slots.php">
                <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="slot_id" class="form-label fw-semibold">Slot Identifier (Code) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase font-monospace" id="slot_id" name="slot_id" required maxlength="10"
                               placeholder="e.g. S-A01"
                               value="<?= sanitize($editSlot['slot_id'] ?? $_POST['slot_id'] ?? '') ?>"
                               <?= $action === 'edit' ? 'readonly' : '' ?>>
                        <div class="form-text"><?= $action === 'edit' ? 'Primary key cannot be altered.' : 'Unique identifier (max 10 chars).' ?></div>
                    </div>

                    <div class="col-md-5">
                        <label for="slot_name" class="form-label fw-semibold">Slot Name / Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="slot_name" name="slot_name" required maxlength="100"
                               placeholder="e.g. North Gate Premium Car Bay"
                               value="<?= sanitize($editSlot['slot_name'] ?? $_POST['slot_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-2">
                        <label for="hourly_rate" class="form-label fw-semibold">Hourly Rate (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" required min="0" step="1"
                                   placeholder="20"
                                   value="<?= sanitize((string)($editSlot['hourly_rate'] ?? $_POST['hourly_rate'] ?? 20)) ?>">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label for="zone_id" class="form-label fw-semibold">Assigned Zone</label>
                        <select class="form-select" id="zone_id" name="zone_id">
                            <option value="">-- No Zone --</option>
                            <?php 
                                $currZone = (int)($editSlot['zone_id'] ?? $_POST['zone_id'] ?? 0);
                                foreach ($allZones as $z): 
                            ?>
                                <option value="<?= $z['zone_id'] ?>" <?= $currZone === (int)$z['zone_id'] ? 'selected' : '' ?>>
                                    <?= sanitize($z['zone_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle me-1"></i><?= $action === 'create' ? 'Save Slot' : 'Save Changes' ?>
                    </button>
                    <a href="slots.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Slots List Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold">All Parking Slots</h5>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            <?= count($slots) ?> Active Slots
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 110px;">Slot ID</th>
                    <th>Slot Name</th>
                    <th>Hourly Tariff</th>
                    <th>Campus Zone</th>
                    <th>Historical Uses</th>
                    <th style="width: 140px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($slots)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No parking slots found. Click "Add New Slot" above.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($slots as $s): ?>
                        <?php $canDelete = ($s['usage_count'] === 0); ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary text-white font-monospace fs-6">
                                    <?= sanitize($s['slot_id']) ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark"><?= sanitize($s['slot_name']) ?></strong>
                            </td>
                            <td>
                                <span class="fw-bold text-success">₹<?= number_format($s['hourly_rate']) ?>/hr</span>
                            </td>
                            <td>
                                <?php if ($s['zone_name']): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-geo-alt me-1"></i><?= sanitize($s['zone_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-clock-history me-1"></i><?= $s['usage_count'] ?> visits
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="slots.php?action=edit&id=<?= urlencode($s['slot_id']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="slots.php" class="d-inline"
                                      onsubmit="<?= !$canDelete ? "alert('Cannot delete slot \"".addslashes($s['slot_id'])."\": It has {$s['usage_count']} historical parking record(s) linked to it. Please delete those records first.'); return false;" : "return confirm('Are you sure you want to delete parking slot \"".addslashes($s['slot_id']). "\"?');" ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slot_id" value="<?= sanitize($s['slot_id']) ?>">
                                    <button type="submit" class="btn btn-sm <?= $canDelete ? 'btn-outline-danger' : 'btn-outline-secondary opacity-50' ?>"
                                            title="<?= $canDelete ? 'Delete slot' : 'Cannot delete (has historical records)' ?>">
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
