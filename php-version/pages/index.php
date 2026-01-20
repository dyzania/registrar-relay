<?php
/**
 * Queue Display Board (Public View)
 * Shows current queue status for all windows
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Queue.php';
require_once __DIR__ . '/../models/Window.php';

Security::startSecureSession();

$windowModel = new Window();
$queueModel = new Queue();

$windows = $windowModel->getActive();
$waitingQueue = $queueModel->getWaitingQueue();

$pageTitle = 'Queue Board - University Registrar';
include __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold text-primary">University of Example</h1>
        <p class="lead text-muted">Registrar's Office Queue Management System</p>
    </div>

    <!-- Now Serving Section -->
    <div class="row mb-5">
        <div class="col-12">
            <h2 class="h4 mb-4">
                <i class="bi bi-display me-2"></i>Now Serving
            </h2>
        </div>
        
        <?php foreach ($windows as $index => $window): ?>
            <?php 
            $colors = ['primary', 'success', 'info', 'warning'];
            $colorClass = $colors[$index % count($colors)];
            ?>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 border-<?= $colorClass ?> shadow-sm">
                    <div class="card-header bg-<?= $colorClass ?> text-white">
                        <h5 class="mb-0">Window <?= htmlspecialchars($window['window_number']) ?></h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if ($window['current_queue_id']): ?>
                            <div class="display-1 fw-bold text-<?= $colorClass ?> mb-2">
                                <?= str_pad($window['queue_number'], 3, '0', STR_PAD_LEFT) ?>
                            </div>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars(getTransactionLabel($window['transaction_type'])) ?>
                            </span>
                            <p class="mt-2 mb-0 text-muted">
                                <?= htmlspecialchars($window['student_name']) ?>
                            </p>
                        <?php else: ?>
                            <div class="display-4 text-muted mb-2">---</div>
                            <span class="badge bg-success">Available</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Upcoming Queue Section -->
    <div class="row">
        <div class="col-12">
            <h2 class="h4 mb-4">
                <i class="bi bi-people me-2"></i>Upcoming Queue
            </h2>
        </div>
        
        <?php if (empty($waitingQueue)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-inbox me-2"></i>No one in queue. The queue is empty.
                </div>
            </div>
        <?php else: ?>
            <?php foreach (array_slice($waitingQueue, 0, 5) as $index => $item): ?>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card <?= $index === 0 ? 'border-primary bg-primary bg-opacity-10' : '' ?>">
                        <div class="card-body text-center py-3">
                            <div class="h3 mb-1 <?= $index === 0 ? 'text-primary' : '' ?>">
                                <?= str_pad($item['queue_number'], 3, '0', STR_PAD_LEFT) ?>
                            </div>
                            <small class="text-muted">
                                <?= htmlspecialchars(getTransactionAbbreviation($item['transaction_type'])) ?>
                            </small>
                            <?php if ($index === 0): ?>
                                <div class="mt-2">
                                    <span class="badge bg-primary">Next</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="text-center mt-5 text-muted">
        <p><i class="bi bi-info-circle me-2"></i>Please wait for your number to be called and proceed to the designated window.</p>
        <small>Last updated: <?= date('h:i:s A') ?></small>
    </div>
</div>

<script>
// Auto-refresh every 10 seconds
setTimeout(function() {
    location.reload();
}, 10000);
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
