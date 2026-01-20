<?php
/**
 * Helper Functions
 * Queue Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Get database connection
 */
function db(): PDO {
    return Database::getInstance()->getConnection();
}

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'M d, Y h:i A'): string {
    return date($format, strtotime($date));
}

/**
 * Calculate wait time in minutes
 */
function calculateWaitTime(string $createdAt, ?string $calledAt): int {
    $start = new DateTime($createdAt);
    $end = $calledAt ? new DateTime($calledAt) : new DateTime();
    $diff = $start->diff($end);
    return ($diff->h * 60) + $diff->i;
}

/**
 * Get transaction type label
 */
function getTransactionLabel(string $type): string {
    return TRANSACTION_TYPES[$type] ?? 'Unknown';
}

/**
 * Get status label with color class
 */
function getStatusBadge(string $status): array {
    $badges = [
        'waiting' => ['label' => 'Waiting', 'class' => 'badge-warning'],
        'in_progress' => ['label' => 'In Progress', 'class' => 'badge-primary'],
        'completed' => ['label' => 'Completed', 'class' => 'badge-success'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-danger']
    ];
    return $badges[$status] ?? ['label' => 'Unknown', 'class' => 'badge-secondary'];
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect with flash message
 */
function redirect(string $url, string $message = '', string $type = 'info'): void {
    if (!empty($message)) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header("Location: $url");
    exit;
}

/**
 * Get and clear flash message
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message HTML
 */
function renderFlashMessage(): string {
    $flash = getFlashMessage();
    if (!$flash) return '';
    
    $typeClasses = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = $typeClasses[$flash['type']] ?? 'alert-info';
    
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">'
         . htmlspecialchars($flash['message'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
         . '</div>';
}

/**
 * Mask student ID for privacy (show only last 4 digits)
 */
function maskStudentId(?string $studentId): string {
    if (empty($studentId)) return 'N/A';
    return '****-' . substr($studentId, -5);
}

/**
 * Get abbreviated transaction type
 */
function getTransactionAbbreviation(string $type): string {
    $abbreviations = [
        'grade_request' => 'GR',
        'enrollment' => 'EN',
        'document_request' => 'DR',
        'payment' => 'PY',
        'clearance' => 'CL',
        'other' => 'OT'
    ];
    return $abbreviations[$type] ?? 'UN';
}
