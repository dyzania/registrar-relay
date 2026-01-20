<?php
/**
 * Application Constants
 * Queue Management System
 */

// Transaction Types
define('TRANSACTION_TYPES', [
    'grade_request' => 'Grade Request',
    'enrollment' => 'Enrollment',
    'document_request' => 'Document Request',
    'payment' => 'Payment',
    'clearance' => 'Clearance',
    'other' => 'Other'
]);

// Queue Status
define('QUEUE_STATUS', [
    'waiting' => 'Waiting',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
]);

// User Roles
define('USER_ROLES', [
    'admin' => 'Administrator',
    'staff' => 'Staff',
    'student' => 'Student'
]);

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'QUEUE_SESSION');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Time Zone
date_default_timezone_set('Asia/Manila');
