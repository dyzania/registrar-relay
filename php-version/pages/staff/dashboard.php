<?php
/**
 * Staff Dashboard
 * Queue management interface for staff
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../models/Window.php';

Security::startSecureSession();
Security::requireRole('staff');

$queueModel = new Queue();
$windowModel = new Window();

$windows = $windowModel->getAll();
$waitingQueue = $queueModel->getWaitingQueue();
$inProgressQueue = $queueModel->getInProgressQueue();
$stats = $queueModel->getTodayStats();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        redirect('/pages/staff/dashboard.php', 'Invalid request.', 'error');
    }
    
    $action = $_POST['action'] ?? '';
    $windowId = (int) ($_POST['window_id'] ?? 0);
    $queueId = $_POST['queue_id'] ?? '';
    
    switch ($action) {
        case 'call_next':
            $transactionType = $_POST['transaction_type'] ?? null;
            $result = $queueModel->callNext($windowId, $transactionType ?: null);
            if ($result) {
                redirect('/pages/staff/dashboard.php', 'Called #' . str_pad($result['queue_number'], 3, '0', STR_PAD_LEFT), 'success');
            } else {
                redirect('/pages/staff/dashboard.php', 'No one in queue to call.', 'warning');
            }
            break;
            
        case 'complete':
            if ($queueModel->complete($queueId)) {
                redirect('/pages/staff/dashboard.php', 'Transaction completed.', 'success');
            } else {
                redirect('/pages/staff/dashboard.php', 'Failed to complete transaction.', 'error');
            }
            break;
            
        case 'toggle_window':
            $windowModel->toggleActive($windowId);
            redirect('/pages/staff/dashboard.php', 'Window status updated.', 'success');
            break;
    }
}

$pageTitle = 'Staff Dashboard';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    
    <?= renderFlashMessage() ?>
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Staff Dashboard</h1>
            <p class="text-muted mb-0">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></p>
        </div>
        <div>
            <a href="analytics.php" class="btn btn-outline-primary me-2">
                <i class="bi bi-graph-up me-1"></i>Analytics
            </a>
            <a href="logout.php" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Waiting</h6>
                    <h2 class="mb-0"><?= $stats['waiting'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="text-dark-50">In Progress</h6>
                    <h2 class="mb-0"><?= $stats['in_progress'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Completed</h6>
                    <h2 class="mb-0"><?= $stats['completed'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Avg Wait Time</h6>
                    <h2 class="mb-0"><?= round($stats['avg_wait_time'] ?? 0) ?> min</h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Windows Section -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-window me-2"></i>Service Windows</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($windows as $window): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card <?= $window['is_active'] ? 'border-primary' : 'border-secondary bg-light' ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>
                                            <strong>Window <?= $window['window_number'] ?></strong>
                                            <?php if (!$window['is_active']): ?>
                                                <span class="badge bg-secondary ms-2">Inactive</span>
                                            <?php endif; ?>
                                        </span>
                                        <form method="POST" action="" class="d-inline">
                                            <?= Security::csrfField() ?>
                                            <input type="hidden" name="action" value="toggle_window">
                                            <input type="hidden" name="window_id" value="<?= $window['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($window['current_queue_id']): ?>
                                            <div class="text-center mb-3">
                                                <p class="text-muted mb-1">Now Serving</p>
                                                <h2 class="text-primary"><?= str_pad($window['queue_number'], 3, '0', STR_PAD_LEFT) ?></h2>
                                                <span class="badge bg-secondary"><?= getTransactionLabel($window['transaction_type']) ?></span>
                                                <p class="mt-2 mb-0"><?= htmlspecialchars($window['student_name']) ?></p>
                                            </div>
                                            <form method="POST" action="">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="queue_id" value="<?= $window['current_queue_id'] ?>">
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="bi bi-check-lg me-1"></i>Complete
                                                </button>
                                            </form>
                                        <?php elseif ($window['is_active']): ?>
                                            <div class="text-center text-muted mb-3">
                                                <i class="bi bi-hourglass display-4"></i>
                                                <p class="mt-2">Available</p>
                                            </div>
                                            <form method="POST" action="">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="action" value="call_next">
                                                <input type="hidden" name="window_id" value="<?= $window['id'] ?>">
                                                <div class="mb-2">
                                                    <select name="transaction_type" class="form-select form-select-sm">
                                                        <option value="">Any transaction</option>
                                                        <?php foreach (TRANSACTION_TYPES as $value => $label): ?>
                                                            <option value="<?= $value ?>"><?= $label ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="bi bi-megaphone me-1"></i>Call Next
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="text-center text-muted">
                                                <i class="bi bi-pause-circle display-4"></i>
                                                <p class="mt-2">Window is inactive</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Waiting Queue -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>Waiting Queue
                        <span class="badge bg-primary ms-2"><?= count($waitingQueue) ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($waitingQueue)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">Queue is empty</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($waitingQueue as $item): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-primary">#<?= str_pad($item['queue_number'], 3, '0', STR_PAD_LEFT) ?></strong>
                                        <span class="badge bg-light text-dark ms-2"><?= getTransactionAbbreviation($item['transaction_type']) ?></span>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($item['student_name']) ?></small>
                                    </div>
                                    <small class="text-muted"><?= formatDate($item['created_at'], 'h:i A') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
