<?php
/**
 * Queue Registration Page
 * Allows students to register for a queue ticket
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Queue.php';

Security::startSecureSession();

$error = '';
$success = '';
$ticket = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } 
    // Rate limiting
    elseif (!Security::checkRateLimit('queue_register', 10, 60)) {
        $error = 'Too many requests. Please wait a moment before trying again.';
    }
    else {
        $studentName = Security::sanitize($_POST['student_name'] ?? '');
        $studentId = Security::sanitize($_POST['student_id'] ?? '');
        $transactionType = Security::sanitize($_POST['transaction_type'] ?? '');
        
        // Validation
        if (empty($studentName)) {
            $error = 'Please enter your name.';
        } elseif (strlen($studentName) < 2 || strlen($studentName) > 100) {
            $error = 'Name must be between 2 and 100 characters.';
        } elseif (!empty($studentId) && !Security::validateStudentId($studentId)) {
            $error = 'Invalid student ID format. Use format: XXXX-XXXXX';
        } elseif (!array_key_exists($transactionType, TRANSACTION_TYPES)) {
            $error = 'Please select a valid transaction type.';
        } else {
            // Create queue entry
            $queueModel = new Queue();
            $ticket = $queueModel->create([
                'student_name' => $studentName,
                'student_id' => $studentId ?: null,
                'transaction_type' => $transactionType
            ]);
            
            if ($ticket) {
                $success = 'Your queue ticket has been created successfully!';
            } else {
                $error = 'Failed to create queue ticket. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register for Queue';
include __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <?php if ($ticket): ?>
                <!-- Success: Show Ticket -->
                <div class="card shadow-lg border-success">
                    <div class="card-header bg-success text-white text-center">
                        <h4 class="mb-0"><i class="bi bi-check-circle me-2"></i>Queue Ticket</h4>
                    </div>
                    <div class="card-body text-center py-5">
                        <p class="text-muted mb-2">Your Queue Number</p>
                        <div class="display-1 fw-bold text-primary mb-4">
                            <?= str_pad($ticket['queue_number'], 3, '0', STR_PAD_LEFT) ?>
                        </div>
                        
                        <div class="mb-3">
                            <span class="badge bg-secondary fs-6">
                                <?= htmlspecialchars(getTransactionLabel($ticket['transaction_type'])) ?>
                            </span>
                        </div>
                        
                        <p class="mb-1"><strong><?= htmlspecialchars($ticket['student_name']) ?></strong></p>
                        <?php if ($ticket['student_id']): ?>
                            <p class="text-muted small"><?= htmlspecialchars(maskStudentId($ticket['student_id'])) ?></p>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <p class="text-muted small mb-3">
                            <i class="bi bi-clock me-1"></i>
                            Created at <?= formatDate($ticket['created_at'], 'h:i A') ?>
                        </p>
                        
                        <div class="d-grid gap-2">
                            <a href="my-ticket.php?number=<?= $ticket['queue_number'] ?>" class="btn btn-primary">
                                <i class="bi bi-eye me-2"></i>Track My Ticket
                            </a>
                            <a href="register.php" class="btn btn-outline-secondary">
                                <i class="bi bi-plus me-2"></i>Get Another Ticket
                            </a>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Registration Form -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i>Get Queue Ticket</h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <?= Security::csrfField() ?>
                            
                            <div class="mb-3">
                                <label for="student_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="student_name" name="student_name" 
                                       required minlength="2" maxlength="100"
                                       value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>"
                                       placeholder="Enter your full name">
                            </div>
                            
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student ID <small class="text-muted">(Optional)</small></label>
                                <input type="text" class="form-control" id="student_id" name="student_id" 
                                       pattern="\d{4}-\d{5}"
                                       value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>"
                                       placeholder="e.g., 2024-12345">
                                <div class="form-text">Format: XXXX-XXXXX</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="transaction_type" class="form-label">Transaction Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="transaction_type" name="transaction_type" required>
                                    <option value="">Select transaction type...</option>
                                    <?php foreach (TRANSACTION_TYPES as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($_POST['transaction_type'] ?? '') === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-ticket me-2"></i>Get Queue Number
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="my-ticket.php" class="text-decoration-none">
                        <i class="bi bi-search me-1"></i>Already have a ticket? Track it here
                    </a>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
