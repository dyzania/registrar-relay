<?php
/**
 * 403 Forbidden Page
 */

$pageTitle = 'Access Denied';
include __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="display-1 text-danger mb-4">403</div>
            <h1 class="h3 mb-3">Access Denied</h1>
            <p class="text-muted mb-4">
                You don't have permission to access this page. 
                Please contact an administrator if you believe this is an error.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="/" class="btn btn-primary">
                    <i class="bi bi-house me-2"></i>Go Home
                </a>
                <a href="/pages/staff/login.php" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Staff Login
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
