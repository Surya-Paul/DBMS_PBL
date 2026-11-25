<?php
/**
 * Parking Zones Management (CRUD)
 * Campus Parking Management System
 */

$pageTitle = 'Parking Zones';
require_once __DIR__ . '/includes/header.php';

if (!$db_connected) {
    echo '<div class="alert alert-danger">Database connection required. Please visit <a href="setup.php">Setup</a>.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editZone = null;
$errors = [];

// Handle POST actions: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $zone_name = trim($_POST['zone_name'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($zone_name)) {
            $errors[] = "Zone Name is required.";
        }

        if (empty($errors)) {
            // Check unique zone_name
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Parking_Zone WHERE zone_name = :name");
            $checkStmt->execute([':name' => $zone_name]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "A zone with name '{$zone_name}' already exists.";
            } else {
                try {
                    $insertStmt = $pdo->prepare("INSERT INTO Parking_Zone (zone_name, location) VALUES (:name, :loc)");
                    $insertStmt->execute([':name' => $zone_name, ':loc' => $location ?: null]);
                    set_flash('success', "Zone '{$zone_name}' created successfully.");
                    header('Location: zones.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($postAction === 'update') {
        $zone_id = (int)($_POST['zone_id'] ?? 0);
        $zone_name = trim($_POST['zone_name'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($zone_name)) {
            $errors[] = "Zone Name is required.";
        }

        if (empty($errors)) {
            // Check unique excluding current
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Parking_Zone WHERE zone_name = :name AND zone_id != :id");
            $checkStmt->execute([':name' => $zone_name, ':id' => $zone_id]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "Another zone with name '{$zone_name}' already exists.";
            } else {
                try {
                    $updStmt = $pdo->prepare("UPDATE Parking_Zone SET zone_name = :name, location = :loc WHERE zone_id = :id");
                    $updStmt->execute([':name' => $zone_name, ':loc' => $location ?: null, ':id' => $zone_id]);
                    set_flash('success', "Zone #{$zone_id} updated successfully.");
                    header('Location: zones.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $zone_id = (int)($_POST['zone_id'] ?? 0);
        $depCheck = check_zone_dependencies($pdo, $zone_id);

        if (!$depCheck['can_delete']) {
            set_flash('danger', $depCheck['message']);
            header('Location: zones.php');
            exit;
        }

        try {
            $delStmt = $pdo->prepare("DELETE FROM Parking_Zone WHERE zone_id = :id");
            $delStmt->execute([':id' => $zone_id]);
            set_flash('success', "Zone deleted successfully.");
            header('Location: zones.php');
            exit;
        } catch (PDOException $e) {
            set_flash('danger', "Cannot delete zone: " . $e->getMessage());
            header('Location: zones.php');
            exit;
        }
    }
}

// If edit action, fetch target zone
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM Parking_Zone WHERE zone_id = :id");
    $stmt->execute([':id' => $editId]);
    $editZone = $stmt->fetch();
    if (!$editZone) {
        set_flash('warning', "Zone not found.");
        header('Location: zones.php');
        exit;
    }
}

// Fetch all zones with vehicle and slot counts (safe dependency insight)
$zonesQuery = $pdo->query("
    SELECT z.zone_id, z.zone_name, z.location,
           COUNT(DISTINCT v.vehicle_id) AS vehicle_count,
           COUNT(DISTINCT s.slot_id) AS slot_count
    FROM Parking_Zone z
    LEFT JOIN Vehicle v ON z.zone_id = v.zone_id
    LEFT JOIN Parking_Slot s ON z.zone_id = s.zone_id
    GROUP BY z.zone_id, z.zone_name, z.location
    ORDER BY z.zone_id ASC
");
$zones = $zonesQuery->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-geo-alt-fill text-primary me-2"></i>Parking Zones
        </h2>
        <p class="text-muted mb-0">Manage campus parking sectors, geographical locations, and assignments</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <?php if ($action !== 'create'): ?>
            <a href="zones.php?action=create" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add New Zone
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
                <?= $action === 'create' ? '<i class="bi bi-plus-circle text-primary me-2"></i>Add New Parking Zone' : '<i class="bi bi-pencil-square text-warning me-2"></i>Edit Zone #' . sanitize($editZone['zone_id']) ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="zones.php">
                <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="zone_id" value="<?= sanitize($editZone['zone_id']) ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="zone_name" class="form-label fw-semibold">Zone Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="zone_name" name="zone_name" required maxlength="50"
                               placeholder="e.g. Zone A - Main Gate"
                               value="<?= sanitize($editZone['zone_name'] ?? $_POST['zone_name'] ?? '') ?>">
                        <div class="form-text">Must be unique across the campus. Max 50 chars.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="location" class="form-label fw-semibold">Location / Description</label>
                        <input type="text" class="form-control" id="location" name="location" maxlength="50"
                               placeholder="e.g. North Campus Entrance Gate 1"
                               value="<?= sanitize($editZone['location'] ?? $_POST['location'] ?? '') ?>">
                        <div class="form-text">Physical campus location or landmark. Max 50 chars.</div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle me-1"></i><?= $action === 'create' ? 'Save Zone' : 'Update Zone' ?>
                    </button>
                    <a href="zones.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Zones List Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold">All Parking Zones</h5>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            <?= count($zones) ?> Zones Total
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Zone Name</th>
                    <th>Campus Location</th>
                    <th>Assigned Vehicles</th>
                    <th>Parking Slots</th>
                    <th style="width: 160px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($zones)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No parking zones found. Click "Add New Zone" above.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($zones as $z): ?>
                        <?php 
                            $depCount = $z['vehicle_count'] + $z['slot_count']; 
                            $canDelete = ($depCount === 0);
                        ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?= sanitize($z['zone_id']) ?></td>
                            <td>
                                <strong class="text-dark"><?= sanitize($z['zone_name']) ?></strong>
                            </td>
                            <td><?= sanitize($z['location'] ?: 'Not specified') ?></td>
                            <td>
                                <a href="vehicles.php?zone_id=<?= $z['zone_id'] ?>" class="text-decoration-none">
                                    <span class="badge <?= $z['vehicle_count'] > 0 ? 'bg-primary' : 'bg-light text-muted border' ?>">
                                        <i class="bi bi-car-front me-1"></i><?= $z['vehicle_count'] ?> vehicles
                                    </span>
                                </a>
                            </td>
                            <td>
                                <span class="badge <?= $z['slot_count'] > 0 ? 'bg-secondary' : 'bg-light text-muted border' ?>">
                                    <i class="bi bi-grid me-1"></i><?= $z['slot_count'] ?> slots
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="zones.php?action=edit&id=<?= $z['zone_id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="zones.php" class="d-inline"
                                      onsubmit="<?= !$canDelete ? "alert('Cannot delete: This zone still has {$depCount} linked vehicle(s) or slot(s). Please reassign or delete them first.'); return false;" : "return confirm('Are you sure you want to delete zone \"".addslashes($z['zone_name']). "\"?');" ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="zone_id" value="<?= $z['zone_id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $canDelete ? 'btn-outline-danger' : 'btn-outline-secondary opacity-50' ?>" 
                                            title="<?= $canDelete ? 'Delete zone' : 'Cannot delete (has linked records)' ?>">
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
