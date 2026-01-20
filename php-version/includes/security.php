<?php
/**
 * Security Functions
 * Handles authentication, authorization, CSRF protection, and input validation
 */

require_once __DIR__ . '/../config/constants.php';

class Security {
    
    /**
     * Start secure session
     */
    public static function startSecureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            session_name(SESSION_NAME);
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken(): string {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken(string $token): bool {
        if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Get CSRF input field HTML
     */
    public static function csrfField(): string {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Hash password securely
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Sanitize input
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email
     */
    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate student ID format
     */
    public static function validateStudentId(string $studentId): bool {
        // Format: XXXX-XXXXX (e.g., 2024-12345)
        return preg_match('/^\d{4}-\d{5}$/', $studentId) === 1;
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Check if user has required role
     */
    public static function hasRole(string $requiredRole): bool {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'] ?? '';
        
        // Admin has access to everything
        if ($userRole === 'admin') {
            return true;
        }
        
        // Staff can access staff and student resources
        if ($userRole === 'staff' && in_array($requiredRole, ['staff', 'student'])) {
            return true;
        }
        
        return $userRole === $requiredRole;
    }

    /**
     * Require authentication - redirect if not logged in
     */
    public static function requireAuth(): void {
        if (!self::isAuthenticated()) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole(string $role): void {
        self::requireAuth();
        if (!self::hasRole($role)) {
            http_response_code(403);
            include __DIR__ . '/../pages/403.php';
            exit;
        }
    }

    /**
     * Get current user ID
     */
    public static function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public static function getCurrentUserRole(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit(string $action, int $maxAttempts = 5, int $timeWindow = 300): bool {
        $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start' => time()];
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $data['start'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'start' => time()];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $data = []): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => self::getCurrentUserId(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ];
        
        error_log(json_encode($logEntry));
    }
}
