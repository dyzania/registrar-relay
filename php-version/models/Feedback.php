<?php
/**
 * Feedback Model
 * Handles customer feedback operations
 */

require_once __DIR__ . '/../config/database.php';

class Feedback {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create new feedback
     */
    public function create(array $data): ?string {
        $sql = "INSERT INTO feedback (queue_id, rating, comment, sentiment, sentiment_score, created_at)
                VALUES (:queue_id, :rating, :comment, :sentiment, :sentiment_score, NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':queue_id' => $data['queue_id'],
                ':rating' => $data['rating'],
                ':comment' => $data['comment'] ?? null,
                ':sentiment' => $data['sentiment'] ?? null,
                ':sentiment_score' => $data['sentiment_score'] ?? null
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Feedback creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get feedback by queue ID
     */
    public function getByQueueId(string $queueId): ?array {
        $sql = "SELECT * FROM feedback WHERE queue_id = :queue_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':queue_id' => $queueId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get today's feedback summary
     */
    public function getTodaySummary(): array {
        $sql = "SELECT 
                    COUNT(*) as total_feedback,
                    AVG(rating) as average_rating,
                    SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as neutral,
                    SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative
                FROM feedback f
                JOIN queue q ON f.queue_id = q.id
                WHERE DATE(f.created_at) = CURDATE()";
        
        return $this->db->query($sql)->fetch();
    }

    /**
     * Get recent feedback
     */
    public function getRecent(int $limit = 10): array {
        $sql = "SELECT f.*, q.queue_number, q.transaction_type
                FROM feedback f
                JOIN queue q ON f.queue_id = q.id
                ORDER BY f.created_at DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get sentiment distribution
     */
    public function getSentimentDistribution(): array {
        $sql = "SELECT 
                    sentiment,
                    COUNT(*) as count
                FROM feedback 
                WHERE sentiment IS NOT NULL
                AND DATE(created_at) = CURDATE()
                GROUP BY sentiment";
        
        return $this->db->query($sql)->fetchAll();
    }
}
