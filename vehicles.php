<?php
/**
 * Vehicle Management & Search (CRUD)
 * Campus Parking Management System
 */

$pageTitle = 'Vehicles';
require_once __DIR__ . '/includes/header.php';

if (!$db_connected) {
    echo '<div class="alert alert-danger">Database connection required. Please visit <a href="setup.php">Setup</a>.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editVehicle = null;
$errors = [];

// Fetch zones for dropdowns
$zonesStmt = $pdo->query("SELECT zone_id, zone_name FROM Parking_Zone ORDER BY zone_name ASC");
$allZones = $zonesStmt->fetchAll();

// Handle POST: Create, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $owner_name = trim($_POST['owner_name'] ?? '');
        $vehicle_number = strtoupper(trim($_POST['vehicle_number'] ?? ''));
        $vehicle_type = trim($_POST['vehicle_type'] ?? '');
        $registration_year = !empty($_POST['registration_year']) ? (int)$_POST['registration_year'] : null;
        $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;

        if (empty($owner_name)) {
            $errors[] = "Owner Name is required.";
        }
        if (empty($vehicle_number)) {
            $errors[] = "Vehicle Registration Number is required.";
        }

        if (empty($errors)) {
            // Check unique vehicle_number
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Vehicle WHERE vehicle_number = :num");
            $checkStmt->execute([':num' => $vehicle_number]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "A vehicle with number '{$vehicle_number}' is already registered.";
            } else {
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO Vehicle (owner_name, vehicle_number, vehicle_type, registration_year, zone_id)
                        VALUES (:owner, :num, :vtype, :yr, :zid)
                    ");
                    $insertStmt->execute([
                        ':owner' => $owner_name,
                        ':num' => $vehicle_number,
                        ':vtype' => $vehicle_type ?: null,
                        ':yr' => $registration_year,
                        ':zid' => $zone_id
                    ]);
                    set_flash('success', "Vehicle '{$vehicle_number}' registered successfully.");
                    header('Location: vehicles.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($postAction === 'update') {
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $owner_name = trim($_POST['owner_name'] ?? '');
        $vehicle_number = strtoupper(trim($_POST['vehicle_number'] ?? ''));
        $vehicle_type = trim($_POST['vehicle_type'] ?? '');
        $registration_year = !empty($_POST['registration_year']) ? (int)$_POST['registration_year'] : null;
        $zone_id = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;

        if (empty($owner_name)) {
            $errors[] = "Owner Name is required.";
        }
        if (empty($vehicle_number)) {
            $errors[] = "Vehicle Registration Number is required.";
        }

        if (empty($errors)) {
            // Check unique excluding current
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Vehicle WHERE vehicle_number = :num AND vehicle_id != :id");
            $checkStmt->execute([':num' => $vehicle_number, ':id' => $vehicle_id]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "Another vehicle with number '{$vehicle_number}' already exists.";
            } else {
                try {
                    $updStmt = $pdo->prepare("
                        UPDATE Vehicle 
                        SET owner_name = :owner, vehicle_number = :num, vehicle_type = :vtype, 
                            registration_year = :yr, zone_id = :zid
                        WHERE vehicle_id = :id
                    ");
                    $updStmt->execute([
                        ':owner' => $owner_name,
                        ':num' => $vehicle_number,
                        ':vtype' => $vehicle_type ?: null,
                        ':yr' => $registration_year,
                        ':zid' => $zone_id,
                        ':id' => $vehicle_id
                    ]);
                    set_flash('success', "Vehicle #{$vehicle_id} updated successfully.");
                    header('Location: vehicles.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
        $depCheck = check_vehicle_dependencies($pdo, $vehicle_id);

        if (!$depCheck['can_delete']) {
            set_flash('danger', $depCheck['message']);
            header('Location: vehicles.php');
            exit;
        }

        try {
            $delStmt = $pdo->prepare("DELETE FROM Vehicle WHERE vehicle_id = :id");
            $delStmt->execute([':id' => $vehicle_id]);
            set_flash('success', "Vehicle deleted successfully.");
            header('Location: vehicles.php');
            exit;
        } catch (PDOException $e) {
            set_flash('danger', "Cannot delete vehicle: " . $e->getMessage());
            header('Location: vehicles.php');
            exit;
        }
    }
}

// Edit view
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM Vehicle WHERE vehicle_id = :id");
    $stmt->execute([':id' => $editId]);
    $editVehicle = $stmt->fetch();
    if (!$editVehicle) {
        set_flash('warning', "Vehicle not found.");
        header('Location: vehicles.php');
        exit;
    }
}

// Search and Filter parameters
$searchId = trim($_GET['search_id'] ?? '');
$searchReg = trim($_GET['search_reg'] ?? '');
$searchOwner = trim($_GET['search_owner'] ?? '');
$filterZone = trim($_GET['zone_id'] ?? '');

$whereClauses = [];
$params = [];

if ($searchId !== '') {
    $whereClauses[] = "v.vehicle_id = :sid";
    $params[':sid'] = (int)$searchId;
}
if ($searchReg !== '') {
    $whereClauses[] = "v.vehicle_number LIKE :sreg";
    $params[':sreg'] = "%{$searchReg}%";
}
if ($searchOwner !== '') {
    $whereClauses[] = "v.owner_name LIKE :sowner";
    $params[':sowner'] = "%{$searchOwner}%";
}
if ($filterZone !== '') {
    $whereClauses[] = "v.zone_id = :fzone";
    $params[':fzone'] = (int)$filterZone;
}

$sql = "
    SELECT v.vehicle_id, v.owner_name, v.vehicle_number, v.vehicle_type, v.registration_year, v.zone_id,
           z.zone_name, z.location AS zone_location,
           COUNT(DISTINCT r.record_id) AS record_count,
           COUNT(DISTINCT p.payment_id) AS payment_count
    FROM Vehicle v
    LEFT JOIN Parking_Zone z ON v.zone_id = z.zone_id
    LEFT JOIN Parking_Record r ON v.vehicle_id = r.vehicle_id
    LEFT JOIN Parking_Payment p ON v.vehicle_id = p.vehicle_id
";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " GROUP BY v.vehicle_id, v.owner_name, v.vehicle_number, v.vehicle_type, v.registration_year, v.zone_id, z.zone_name, z.location ORDER BY v.vehicle_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-car-front-fill text-primary me-2"></i>Vehicles Directory
        </h2>
        <p class="text-muted mb-0">Manage registered vehicles, assigned zones, and search campus parking permits</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <?php if ($action !== 'create'): ?>
            <a href="vehicles.php?action=create" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Register New Vehicle
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
                <?= $action === 'create' ? '<i class="bi bi-plus-circle text-primary me-2"></i>Register New Vehicle' : '<i class="bi bi-pencil-square text-warning me-2"></i>Edit Vehicle #' . sanitize($editVehicle['vehicle_id']) ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="vehicles.php">
                <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="vehicle_id" value="<?= sanitize($editVehicle['vehicle_id']) ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="owner_name" class="form-label fw-semibold">Owner Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="owner_name" name="owner_name" required maxlength="100"
                               placeholder="e.g. Rahul Sharma or Dr. Amit Kumar"
                               value="<?= sanitize($editVehicle['owner_name'] ?? $_POST['owner_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="vehicle_number" class="form-label fw-semibold">Vehicle Registration Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase font-monospace" id="vehicle_number" name="vehicle_number" required maxlength="20"
                               placeholder="e.g. MH-12-AB-1001"
                               value="<?= sanitize($editVehicle['vehicle_number'] ?? $_POST['vehicle_number'] ?? '') ?>">
                        <div class="form-text">Must be unique across the campus.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="vehicle_type" class="form-label fw-semibold">Vehicle Type</label>
                        <select class="form-select" id="vehicle_type" name="vehicle_type">
                            <?php 
                                $vtypes = ['Car (Sedan)', 'Car (Hatchback)', 'SUV', 'Two-Wheeler', 'Electric Car', 'Van / Bus', 'Other'];
                                $currType = $editVehicle['vehicle_type'] ?? $_POST['vehicle_type'] ?? '';
                            ?>
                            <option value="">-- Select Type --</option>
                            <?php foreach ($vtypes as $vt): ?>
                                <option value="<?= sanitize($vt) ?>" <?= $currType === $vt ? 'selected' : '' ?>>
                                    <?= sanitize($vt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="registration_year" class="form-label fw-semibold">Registration Year</label>
                        <input type="number" class="form-control" id="registration_year" name="registration_year"
                               min="1990" max="<?= date('Y') + 1 ?>" placeholder="e.g. <?= date('Y') ?>"
                               value="<?= sanitize($editVehicle['registration_year'] ?? $_POST['registration_year'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="zone_id" class="form-label fw-semibold">Designated Parking Zone</label>
                        <select class="form-select" id="zone_id" name="zone_id">
                            <option value="">-- No Zone Assigned --</option>
                            <?php 
                                $currZone = (int)($editVehicle['zone_id'] ?? $_POST['zone_id'] ?? 0);
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
                        <i class="bi bi-check2-circle me-1"></i><?= $action === 'create' ? 'Register Vehicle' : 'Save Changes' ?>
                    </button>
                    <a href="vehicles.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Search & Filtering Card -->
<div class="card border-0 shadow-sm mb-4 bg-white">
    <div class="card-body">
        <form method="GET" action="vehicles.php" class="row g-2 align-items-end">
            <div class="col-lg-2 col-md-3 col-sm-6">
                <label for="search_id" class="form-label small fw-semibold text-muted mb-1">Vehicle ID</label>
                <input type="number" class="form-control form-control-sm" id="search_id" name="search_id" 
                       placeholder="e.g. 1" value="<?= sanitize($searchId) ?>">
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6">
                <label for="search_reg" class="form-label small fw-semibold text-muted mb-1">Plate / Registration</label>
                <input type="text" class="form-control form-control-sm text-uppercase" id="search_reg" name="search_reg" 
                       placeholder="e.g. MH-12" value="<?= sanitize($searchReg) ?>">
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6">
                <label for="search_owner" class="form-label small fw-semibold text-muted mb-1">Owner Name</label>
                <input type="text" class="form-control form-control-sm" id="search_owner" name="search_owner" 
                       placeholder="e.g. Sharma" value="<?= sanitize($searchOwner) ?>">
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6">
                <label for="filter_zone" class="form-label small fw-semibold text-muted mb-1">Parking Zone</label>
                <select class="form-select form-select-sm" id="filter_zone" name="zone_id">
                    <option value="">All Zones</option>
                    <?php foreach ($allZones as $z): ?>
                        <option value="<?= $z['zone_id'] ?>" <?= $filterZone === (string)$z['zone_id'] ? 'selected' : '' ?>>
                            <?= sanitize($z['zone_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-12 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <?php if ($searchId !== '' || $searchReg !== '' || $searchOwner !== '' || $filterZone !== ''): ?>
                    <a href="vehicles.php" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Vehicles List Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold">Vehicles List</h5>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            <?= count($vehicles) ?> Found
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Registration No.</th>
                    <th>Owner</th>
                    <th>Type</th>
                    <th>Year</th>
                    <th>Assigned Zone</th>
                    <th>History</th>
                    <th style="width: 140px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehicles)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No vehicles matched your search criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vehicles as $v): ?>
                        <?php 
                            $depCount = $v['record_count'] + $v['payment_count']; 
                            $canDelete = ($depCount === 0);
                        ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?= sanitize($v['vehicle_id']) ?></td>
                            <td>
                                <span class="badge bg-dark-subtle text-dark border font-monospace fs-6">
                                    <?= sanitize($v['vehicle_number']) ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark"><?= sanitize($v['owner_name']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= sanitize($v['vehicle_type'] ?: 'Unspecified') ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= sanitize($v['registration_year'] ?: '-') ?></td>
                            <td>
                                <?php if ($v['zone_name']): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-geo-alt me-1"></i><?= sanitize($v['zone_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <span class="badge bg-info-subtle text-info border border-info-subtle me-1" title="Parking Records">
                                    <?= $v['record_count'] ?> visits
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" title="Payments">
                                    <?= $v['payment_count'] ?> payments
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="vehicles.php?action=edit&id=<?= $v['vehicle_id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="vehicles.php" class="d-inline"
                                      onsubmit="<?= !$canDelete ? "alert('Cannot delete: This vehicle has {$v['record_count']} parking record(s) and {$v['payment_count']} payment(s) linked to it. Please delete those records first to maintain data integrity.'); return false;" : "return confirm('Are you sure you want to delete vehicle \"".addslashes($v['vehicle_number']). "\"?');" ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="vehicle_id" value="<?= $v['vehicle_id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $canDelete ? 'btn-outline-danger' : 'btn-outline-secondary opacity-50' ?>"
                                            title="<?= $canDelete ? 'Delete vehicle' : 'Cannot delete (has linked visits or payments)' ?>">
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
