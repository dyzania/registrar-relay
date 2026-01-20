<?php
/**
 * Queue Model
 * Handles all queue operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Queue {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create new queue entry
     */
    public function create(array $data): ?array {
        $this->db->beginTransaction();
        
        try {
            // Get next queue number for today
            $queueNumber = $this->getNextQueueNumber();
            
            $sql = "INSERT INTO queue (queue_number, transaction_type, student_name, student_id, status, created_at)
                    VALUES (:queue_number, :transaction_type, :student_name, :student_id, 'waiting', NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':queue_number' => $queueNumber,
                ':transaction_type' => $data['transaction_type'],
                ':student_name' => $data['student_name'],
                ':student_id' => $data['student_id'] ?? null
            ]);
            
            $id = $this->db->lastInsertId();
            $this->db->commit();
            
            return $this->getById($id);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Queue creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get next queue number for today
     */
    private function getNextQueueNumber(): int {
        $today = date('Y-m-d');
        
        $sql = "SELECT last_number FROM queue_counter WHERE date = :date FOR UPDATE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':date' => $today]);
        $result = $stmt->fetch();
        
        if ($result) {
            $nextNumber = $result['last_number'] + 1;
            $sql = "UPDATE queue_counter SET last_number = :number WHERE date = :date";
        } else {
            $nextNumber = 1;
            $sql = "INSERT INTO queue_counter (date, last_number) VALUES (:date, :number)";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':date' => $today, ':number' => $nextNumber]);
        
        return $nextNumber;
    }

    /**
     * Get queue entry by ID
     */
    public function getById(string $id): ?array {
        $sql = "SELECT q.*, w.window_number 
                FROM queue q 
                LEFT JOIN windows w ON q.window_id = w.id 
                WHERE q.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get queue entry by queue number (today only)
     */
    public function getByQueueNumber(int $queueNumber): ?array {
        $sql = "SELECT q.*, w.window_number 
                FROM queue q 
                LEFT JOIN windows w ON q.window_id = w.id 
                WHERE q.queue_number = :number 
                AND DATE(q.created_at) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':number' => $queueNumber]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get waiting queue
     */
    public function getWaitingQueue(): array {
        $sql = "SELECT * FROM queue 
                WHERE status = 'waiting' 
                AND DATE(created_at) = CURDATE()
                ORDER BY created_at ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Get in-progress queue items
     */
    public function getInProgressQueue(): array {
        $sql = "SELECT q.*, w.window_number 
                FROM queue q 
                JOIN windows w ON q.window_id = w.id 
                WHERE q.status = 'in_progress' 
                AND DATE(q.created_at) = CURDATE()
                ORDER BY q.called_at ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Get queue position for a specific queue item
     */
    public function getQueuePosition(string $id): int {
        $sql = "SELECT COUNT(*) as position 
                FROM queue 
                WHERE status = 'waiting' 
                AND DATE(created_at) = CURDATE()
                AND created_at < (SELECT created_at FROM queue WHERE id = :id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return ($result['position'] ?? 0) + 1;
    }

    /**
     * Call next in queue for a window
     */
    public function callNext(int $windowId, ?string $transactionType = null): ?array {
        $this->db->beginTransaction();
        
        try {
            // Build query based on whether we're filtering by transaction type
            $sql = "SELECT id FROM queue 
                    WHERE status = 'waiting' 
                    AND DATE(created_at) = CURDATE()";
            
            $params = [];
            
            if ($transactionType) {
                $sql .= " AND transaction_type = :type";
                $params[':type'] = $transactionType;
            }
            
            $sql .= " ORDER BY created_at ASC LIMIT 1 FOR UPDATE";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $next = $stmt->fetch();
            
            if (!$next) {
                $this->db->rollBack();
                return null;
            }
            
            // Update queue item
            $sql = "UPDATE queue 
                    SET status = 'in_progress', window_id = :window_id, called_at = NOW() 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':window_id' => $windowId, ':id' => $next['id']]);
            
            // Update window
            $sql = "UPDATE windows SET current_queue_id = :queue_id WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':queue_id' => $next['id'], ':id' => $windowId]);
            
            $this->db->commit();
            
            return $this->getById($next['id']);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Call next failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Complete a queue transaction
     */
    public function complete(string $id): bool {
        $this->db->beginTransaction();
        
        try {
            $queue = $this->getById($id);
            if (!$queue) {
                $this->db->rollBack();
                return false;
            }
            
            // Update queue item
            $sql = "UPDATE queue SET status = 'completed', completed_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            // Clear window
            if ($queue['window_id']) {
                $sql = "UPDATE windows SET current_queue_id = NULL WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':id' => $queue['window_id']]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Complete failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel a queue entry
     */
    public function cancel(string $id): bool {
        $sql = "UPDATE queue SET status = 'cancelled' WHERE id = :id AND status = 'waiting'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
    }

    /**
     * Get today's statistics
     */
    public function getTodayStats(): array {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    AVG(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(MINUTE, created_at, completed_at) END) as avg_wait_time
                FROM queue 
                WHERE DATE(created_at) = CURDATE()";
        
        return $this->db->query($sql)->fetch();
    }

    /**
     * Get hourly distribution for today
     */
    public function getHourlyDistribution(): array {
        $sql = "SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM queue 
                WHERE DATE(created_at) = CURDATE()
                GROUP BY HOUR(created_at)
                ORDER BY hour";
        
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Get transaction type breakdown for today
     */
    public function getTransactionBreakdown(): array {
        $sql = "SELECT 
                    transaction_type,
                    COUNT(*) as count
                FROM queue 
                WHERE DATE(created_at) = CURDATE()
                GROUP BY transaction_type";
        
        return $this->db->query($sql)->fetchAll();
    }
}
