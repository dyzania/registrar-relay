<?php
/**
 * Analytics Page
 * Queue performance metrics and statistics
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../models/Feedback.php';

Security::startSecureSession();
Security::requireRole('staff');

$queueModel = new Queue();
$feedbackModel = new Feedback();

$stats = $queueModel->getTodayStats();
$hourlyData = $queueModel->getHourlyDistribution();
$transactionBreakdown = $queueModel->getTransactionBreakdown();
$feedbackSummary = $feedbackModel->getTodaySummary();
$recentFeedback = $feedbackModel->getRecent(5);

$pageTitle = 'Analytics';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Analytics Dashboard</h1>
            <p class="text-muted mb-0">Queue performance metrics for <?= date('F j, Y') ?></p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Today</h6>
                    <h2 class="text-primary"><?= $stats['total'] ?? 0 ?></h2>
                    <small class="text-muted">queue entries</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Completed</h6>
                    <h2 class="text-success"><?= $stats['completed'] ?? 0 ?></h2>
                    <small class="text-muted">transactions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Avg Wait Time</h6>
                    <h2 class="text-warning"><?= round($stats['avg_wait_time'] ?? 0) ?></h2>
                    <small class="text-muted">minutes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Avg Rating</h6>
                    <h2 class="text-info">
                        <?= $feedbackSummary['average_rating'] ? number_format($feedbackSummary['average_rating'], 1) : 'N/A' ?>
                        <?php if ($feedbackSummary['average_rating']): ?>
                            <i class="bi bi-star-fill text-warning fs-5"></i>
                        <?php endif; ?>
                    </h2>
                    <small class="text-muted"><?= $feedbackSummary['total_feedback'] ?? 0 ?> reviews</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Hourly Distribution Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Hourly Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Transaction Breakdown -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Transaction Types</h5>
                </div>
                <div class="card-body">
                    <canvas id="transactionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Feedback -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Recent Feedback</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentFeedback)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-chat-dots display-4"></i>
                            <p class="mt-2">No feedback received yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Queue #</th>
                                        <th>Transaction</th>
                                        <th>Rating</th>
                                        <th>Comment</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentFeedback as $fb): ?>
                                        <tr>
                                            <td><strong>#<?= str_pad($fb['queue_number'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                                            <td><?= htmlspecialchars(getTransactionLabel($fb['transaction_type'])) ?></td>
                                            <td>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $fb['rating'] ? '-fill text-warning' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td><?= $fb['comment'] ? htmlspecialchars($fb['comment']) : '<span class="text-muted">No comment</span>' ?></td>
                                            <td><?= formatDate($fb['created_at'], 'h:i A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Hourly Chart
const hourlyData = <?= json_encode($hourlyData) ?>;
const hours = Array.from({length: 24}, (_, i) => i);
const hourlyCounts = hours.map(h => {
    const found = hourlyData.find(d => d.hour === h);
    return found ? found.count : 0;
});

new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: hours.map(h => h.toString().padStart(2, '0') + ':00'),
        datasets: [{
            label: 'Queue Entries',
            data: hourlyCounts,
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Transaction Chart
const transactionData = <?= json_encode($transactionBreakdown) ?>;
const transactionLabels = <?= json_encode(TRANSACTION_TYPES) ?>;
const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#6b7280'];

new Chart(document.getElementById('transactionChart'), {
    type: 'doughnut',
    data: {
        labels: transactionData.map(d => transactionLabels[d.transaction_type] || d.transaction_type),
        datasets: [{
            data: transactionData.map(d => d.count),
            backgroundColor: colors.slice(0, transactionData.length)
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
