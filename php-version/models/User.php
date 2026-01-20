<?php
/**
 * User Model
 * Handles user authentication and management
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create new user
     */
    public function create(array $data): ?int {
        $sql = "INSERT INTO users (email, password_hash, name, role, created_at) 
                VALUES (:email, :password, :name, :role, NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':email' => $data['email'],
                ':password' => Security::hashPassword($data['password']),
                ':name' => $data['name'],
                ':role' => $data['role'] ?? 'student'
            ]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("User creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Authenticate user
     */
    public function authenticate(string $email, string $password): ?array {
        $sql = "SELECT id, email, password_hash, name, role FROM users WHERE email = :email AND is_active = 1";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if ($user && Security::verifyPassword($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Remove password from return data
                unset($user['password_hash']);
                return $user;
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("Authentication failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin(int $userId): void {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
    }

    /**
     * Get user by ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT id, email, name, role, created_at, last_login FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get user by email
     */
    public function getByEmail(string $email): ?array {
        $sql = "SELECT id, email, name, role FROM users WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Update user
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        
        if (isset($data['email'])) {
            $fields[] = 'email = :email';
            $params[':email'] = $data['email'];
        }
        
        if (isset($data['password'])) {
            $fields[] = 'password_hash = :password';
            $params[':password'] = Security::hashPassword($data['password']);
        }
        
        if (empty($fields)) return false;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("User update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all staff users
     */
    public function getStaffUsers(): array {
        $sql = "SELECT id, email, name, role, last_login FROM users WHERE role IN ('staff', 'admin') ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Login user (set session)
     */
    public function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        Security::logSecurityEvent('user_login', ['user_id' => $user['id']]);
    }

    /**
     * Logout user
     */
    public function logout(): void {
        Security::logSecurityEvent('user_logout', ['user_id' => $_SESSION['user_id'] ?? null]);
        
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
}
