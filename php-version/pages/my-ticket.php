<?php
/**
 * My Ticket Page
 * Allows students to track their queue status
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Queue.php';
require_once __DIR__ . '/../models/Feedback.php';

Security::startSecureSession();

$queueModel = new Queue();
$feedbackModel = new Feedback();

$ticket = null;
$position = null;
$error = '';
$feedbackSubmitted = false;

// Search for ticket
$queueNumber = isset($_GET['number']) ? (int) $_GET['number'] : null;

if ($queueNumber) {
    $ticket = $queueModel->getByQueueNumber($queueNumber);
    if ($ticket) {
        if ($ticket['status'] === 'waiting') {
            $position = $queueModel->getQueuePosition($ticket['id']);
        }
    } else {
        $error = 'Ticket not found. Please check the queue number.';
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = Security::sanitize($_POST['comment'] ?? '');
        $queueId = $_POST['queue_id'] ?? '';
        
        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a rating between 1 and 5.';
        } else {
            $result = $feedbackModel->create([
                'queue_id' => $queueId,
                'rating' => $rating,
                'comment' => $comment ?: null
            ]);
            
            if ($result) {
                $feedbackSubmitted = true;
            } else {
                $error = 'Failed to submit feedback. Please try again.';
            }
        }
    }
}

// Handle ticket cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket'])) {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $queueId = $_POST['queue_id'] ?? '';
        if ($queueModel->cancel($queueId)) {
            $ticket = $queueModel->getByQueueNumber($queueNumber);
        } else {
            $error = 'Failed to cancel ticket. It may have already been called.';
        }
    }
}

$pageTitle = 'Track My Ticket';
include __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <!-- Search Form -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-search me-2"></i>Find My Ticket</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" name="number" 
                                   placeholder="Enter queue number" min="1"
                                   value="<?= $queueNumber ? htmlspecialchars($queueNumber) : '' ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($ticket): ?>
                <!-- Ticket Status -->
                <div class="card shadow">
                    <div class="card-body text-center py-4">
                        <p class="text-muted mb-2">Queue Number</p>
                        <div class="display-2 fw-bold text-primary mb-3">
                            <?= str_pad($ticket['queue_number'], 3, '0', STR_PAD_LEFT) ?>
                        </div>
                        
                        <?php 
                        $statusBadge = getStatusBadge($ticket['status']);
                        $statusColors = [
                            'waiting' => 'warning',
                            'in_progress' => 'primary',
                            'completed' => 'success',
                            'cancelled' => 'danger'
                        ];
                        $statusColor = $statusColors[$ticket['status']] ?? 'secondary';
                        ?>
                        
                        <div class="mb-4">
                            <span class="badge bg-<?= $statusColor ?> fs-5 px-4 py-2">
                                <?= htmlspecialchars($statusBadge['label']) ?>
                            </span>
                        </div>
                        
                        <?php if ($ticket['status'] === 'waiting' && $position): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-people me-2"></i>
                                You are <strong>#<?= $position ?></strong> in the queue
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($ticket['status'] === 'in_progress' && $ticket['window_number']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-arrow-right-circle me-2"></i>
                                Please proceed to <strong>Window <?= htmlspecialchars($ticket['window_number']) ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="row text-start">
                            <div class="col-6 mb-3">
                                <small class="text-muted">Name</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($ticket['student_name']) ?></p>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted">Transaction</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars(getTransactionLabel($ticket['transaction_type'])) ?></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Created</small>
                                <p class="mb-0"><?= formatDate($ticket['created_at'], 'h:i A') ?></p>
                            </div>
                            <?php if ($ticket['called_at']): ?>
                                <div class="col-6">
                                    <small class="text-muted">Called</small>
                                    <p class="mb-0"><?= formatDate($ticket['called_at'], 'h:i A') ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($ticket['status'] === 'waiting'): ?>
                            <hr class="my-4">
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel your ticket?');">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="queue_id" value="<?= htmlspecialchars($ticket['id']) ?>">
                                <button type="submit" name="cancel_ticket" class="btn btn-outline-danger">
                                    <i class="bi bi-x-circle me-2"></i>Cancel Ticket
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($ticket['status'] === 'completed' && !$feedbackSubmitted): ?>
                            <!-- Feedback Form -->
                            <hr class="my-4">
                            <h6 class="mb-3">How was your experience?</h6>
                            <form method="POST" action="">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="queue_id" value="<?= htmlspecialchars($ticket['id']) ?>">
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-center gap-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="btn-check" type="radio" name="rating" 
                                                       id="rating<?= $i ?>" value="<?= $i ?>" required>
                                                <label class="btn btn-outline-warning" for="rating<?= $i ?>">
                                                    <i class="bi bi-star-fill"></i>
                                                </label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <textarea class="form-control" name="comment" rows="2" 
                                              placeholder="Any comments? (optional)"></textarea>
                                </div>
                                
                                <button type="submit" name="submit_feedback" class="btn btn-primary">
                                    <i class="bi bi-send me-2"></i>Submit Feedback
                                </button>
                            </form>
                        <?php elseif ($feedbackSubmitted): ?>
                            <hr class="my-4">
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle me-2"></i>Thank you for your feedback!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!$queueNumber): ?>
                <div class="text-center text-muted">
                    <i class="bi bi-ticket-perforated display-1"></i>
                    <p class="mt-3">Enter your queue number above to track your ticket status.</p>
                    <a href="register.php" class="btn btn-primary mt-2">
                        <i class="bi bi-plus me-2"></i>Get a Queue Ticket
                    </a>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php if ($ticket && $ticket['status'] !== 'completed' && $ticket['status'] !== 'cancelled'): ?>
<script>
// Auto-refresh every 15 seconds for active tickets
setTimeout(function() {
    location.reload();
}, 15000);
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
