<?php
/**
 * Logout Page
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';

Security::startSecureSession();

$userModel = new User();
$userModel->logout();

redirect('/pages/staff/login.php', 'You have been logged out successfully.', 'info');
