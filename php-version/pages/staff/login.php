<?php
/**
 * Staff Login Page
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';

Security::startSecureSession();

// Redirect if already logged in
if (Security::isAuthenticated()) {
    redirect('/pages/staff/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request. Please try again.';
    }
    // Rate limiting
    elseif (!Security::checkRateLimit('login', 5, 300)) {
        $error = 'Too many login attempts. Please wait 5 minutes.';
        Security::logSecurityEvent('login_rate_limited', ['email' => $_POST['email'] ?? '']);
    }
    else {
        $email = Security::sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } elseif (!Security::validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $userModel = new User();
            $user = $userModel->authenticate($email, $password);
            
            if ($user) {
                // Check if user has staff role
                if (!in_array($user['role'], ['staff', 'admin'])) {
                    $error = 'You do not have permission to access the staff area.';
                    Security::logSecurityEvent('unauthorized_staff_login', ['email' => $email]);
                } else {
                    $userModel->login($user);
                    redirect('/pages/staff/dashboard.php', 'Welcome back, ' . $user['name'] . '!', 'success');
                }
            } else {
                $error = 'Invalid email or password.';
                Security::logSecurityEvent('failed_login', ['email' => $email]);
            }
        }
    }
}

$pageTitle = 'Staff Login';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock display-1 text-primary"></i>
                <h2 class="mt-3">Staff Login</h2>
                <p class="text-muted">Access the queue management system</p>
            </div>
            
            <div class="card shadow">
                <div class="card-body p-4">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?= renderFlashMessage() ?>
                    
                    <form method="POST" action="">
                        <?= Security::csrfField() ?>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       required autocomplete="email"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="staff@university.edu">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required autocomplete="current-password"
                                       placeholder="Enter your password">
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="../index.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i>Back to Queue Board
                </a>
            </div>
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
