<?php
/**
 * 404 Not Found Page
 */

$pageTitle = 'Page Not Found';
include __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="display-1 text-muted mb-4">404</div>
            <h1 class="h3 mb-3">Page Not Found</h1>
            <p class="text-muted mb-4">
                The page you're looking for doesn't exist or has been moved.
            </p>
            <a href="/" class="btn btn-primary">
                <i class="bi bi-house me-2"></i>Go Home
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
