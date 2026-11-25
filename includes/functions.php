<?php
/**
 * Core Application Functions and Helper Utilities
 * DBMS PBL - Campus Parking Management System
 */

/**
 * Classify parking stays consistently:
 * - Short: less than 4 hours
 * - Medium: 4 hours up to (but not including) 8 hours
 * - Long: 8 hours or more
 */
function calculate_stay_category(float $hours): string {
    if ($hours < 4.0) {
        return 'Short';
    } elseif ($hours < 8.0) {
        return 'Medium';
    } else {
        return 'Long';
    }
}

/**
 * Returns HTML badge for stay category
 */
function get_stay_badge(string $category): string {
    return match (strtolower(trim($category))) {
        'short' => '<span class="badge bg-info text-dark"><i class="bi bi-clock me-1"></i>Short (&lt;4h)</span>',
        'medium' => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Medium (4-8h)</span>',
        'long' => '<span class="badge bg-danger text-white"><i class="bi bi-alarm-fill me-1"></i>Long (&ge;8h)</span>',
        default => '<span class="badge bg-secondary">' . htmlspecialchars($category) . '</span>',
    };
}

/**
 * Returns HTML badge for payment status
 */
function get_payment_badge(string $status): string {
    return match (strtolower(trim($status))) {
        'paid' => '<span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Paid</span>',
        'partial' => '<span class="badge bg-warning text-dark"><i class="bi bi-pie-chart-fill me-1"></i>Partial</span>',
        'pending' => '<span class="badge bg-secondary"><i class="bi bi-hourglass me-1"></i>Pending</span>',
        default => '<span class="badge bg-dark">' . htmlspecialchars($status) . '</span>',
    };
}

/**
 * Format currency amount
 */
function format_currency(float $amount): string {
    return '₹' . number_format($amount, 2);
}

/**
 * Estimate charge: hours_parked * hourly_rate
 */
function estimate_parking_charge(float $hours, float $rate): float {
    return round($hours * $rate, 2);
}

/**
 * Sanitize output
 */
function sanitize(mixed $data): string {
    return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Safe Dependency Check: Parking_Zone
 * Checks if zone has linked vehicles or slots before deletion.
 */
function check_zone_dependencies(PDO $pdo, int $zone_id): array {
    $stmtVehicles = $pdo->prepare("SELECT COUNT(*) AS total FROM Vehicle WHERE zone_id = :id");
    $stmtVehicles->execute([':id' => $zone_id]);
    $vehicleCount = (int)$stmtVehicles->fetchColumn();

    $stmtSlots = $pdo->prepare("SELECT COUNT(*) AS total FROM Parking_Slot WHERE zone_id = :id");
    $stmtSlots->execute([':id' => $zone_id]);
    $slotCount = (int)$stmtSlots->fetchColumn();

    $canDelete = ($vehicleCount === 0 && $slotCount === 0);
    $reasons = [];
    if ($vehicleCount > 0) {
        $reasons[] = "{$vehicleCount} registered vehicle(s)";
    }
    if ($slotCount > 0) {
        $reasons[] = "{$slotCount} parking slot(s)";
    }

    $message = $canDelete 
        ? "Safe to delete." 
        : "Cannot delete zone: it is still referenced by " . implode(' and ', $reasons) . ". Reassign or remove these dependent records first.";

    return [
        'can_delete' => $canDelete,
        'vehicles' => $vehicleCount,
        'slots' => $slotCount,
        'message' => $message
    ];
}

/**
 * Safe Dependency Check: Parking_Slot
 * Checks if slot has linked parking records.
 */
function check_slot_dependencies(PDO $pdo, string $slot_id): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM Parking_Record WHERE slot_id = :id");
    $stmt->execute([':id' => $slot_id]);
    $recordCount = (int)$stmt->fetchColumn();

    $canDelete = ($recordCount === 0);
    $message = $canDelete 
        ? "Safe to delete." 
        : "Cannot delete slot: {$recordCount} historical parking record(s) are linked to this slot. Delete or reassign the records first.";

    return [
        'can_delete' => $canDelete,
        'records' => $recordCount,
        'message' => $message
    ];
}

/**
 * Safe Dependency Check: Vehicle
 * Checks if vehicle has linked parking records or payments.
 */
function check_vehicle_dependencies(PDO $pdo, int $vehicle_id): array {
    $stmtRecords = $pdo->prepare("SELECT COUNT(*) AS total FROM Parking_Record WHERE vehicle_id = :id");
    $stmtRecords->execute([':id' => $vehicle_id]);
    $recordCount = (int)$stmtRecords->fetchColumn();

    $stmtPayments = $pdo->prepare("SELECT COUNT(*) AS total FROM Parking_Payment WHERE vehicle_id = :id");
    $stmtPayments->execute([':id' => $vehicle_id]);
    $paymentCount = (int)$stmtPayments->fetchColumn();

    $canDelete = ($recordCount === 0 && $paymentCount === 0);
    $reasons = [];
    if ($recordCount > 0) {
        $reasons[] = "{$recordCount} parking visit record(s)";
    }
    if ($paymentCount > 0) {
        $reasons[] = "{$paymentCount} payment transaction(s)";
    }

    $message = $canDelete 
        ? "Safe to delete." 
        : "Cannot delete vehicle: it has " . implode(' and ', $reasons) . " associated with it. Delete the dependent records first to maintain referential integrity.";

    return [
        'can_delete' => $canDelete,
        'records' => $recordCount,
        'payments' => $paymentCount,
        'message' => $message
    ];
}
