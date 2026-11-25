<?php
/**
 * Session Flash Messaging Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'text' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

function display_flash(): void {
    $flash = get_flash();
    if ($flash) {
        $type = htmlspecialchars($flash['type']);
        $text = htmlspecialchars($flash['text']);
        $icon = match ($flash['type']) {
            'success' => '<i class="bi bi-check-circle-fill me-2"></i>',
            'danger' => '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
            'warning' => '<i class="bi bi-exclamation-circle-fill me-2"></i>',
            default => '<i class="bi bi-info-circle-fill me-2"></i>',
        };
        echo "<div class=\"alert alert-{$type} alert-dismissible fade show shadow-sm\" role=\"alert\">
                {$icon}{$text}
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
              </div>";
    }
}
