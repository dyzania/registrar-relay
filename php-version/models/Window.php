<?php
/**
 * Window Model
 * Handles service window operations
 */

require_once __DIR__ . '/../config/database.php';

class Window {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all windows
     */
    public function getAll(): array {
        $sql = "SELECT w.*, q.queue_number, q.transaction_type, q.student_name
                FROM windows w
                LEFT JOIN queue q ON w.current_queue_id = q.id
                ORDER BY w.window_number";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Get window by ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT w.*, q.queue_number, q.transaction_type, q.student_name
                FROM windows w
                LEFT JOIN queue q ON w.current_queue_id = q.id
                WHERE w.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get active windows
     */
    public function getActive(): array {
        $sql = "SELECT w.*, q.queue_number, q.transaction_type, q.student_name
                FROM windows w
                LEFT JOIN queue q ON w.current_queue_id = q.id
                WHERE w.is_active = 1
                ORDER BY w.window_number";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Create new window
     */
    public function create(int $windowNumber): ?int {
        $sql = "INSERT INTO windows (window_number, is_active, created_at) VALUES (:number, 1, NOW())";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':number' => $windowNumber]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Window creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Toggle window active status
     */
    public function toggleActive(int $id): bool {
        $sql = "UPDATE windows SET is_active = NOT is_active WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Update disabled services
     */
    public function updateDisabledServices(int $id, array $services): bool {
        $sql = "UPDATE windows SET disabled_services = :services WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':services' => json_encode($services)
        ]);
    }

    /**
     * Clear current queue from window
     */
    public function clearCurrentQueue(int $id): bool {
        $sql = "UPDATE windows SET current_queue_id = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Check if window can handle transaction type
     */
    public function canHandleTransaction(int $windowId, string $transactionType): bool {
        $window = $this->getById($windowId);
        if (!$window || !$window['is_active']) {
            return false;
        }
        
        $disabledServices = json_decode($window['disabled_services'] ?? '[]', true);
        return !in_array($transactionType, $disabledServices);
    }
}
