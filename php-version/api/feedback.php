<?php
/**
 * Feedback API Endpoints
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Feedback.php';

Security::startSecureSession();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$feedbackModel = new Feedback();

try {
    switch ($method) {
        case 'GET':
            handleGetRequests($action, $feedbackModel);
            break;
            
        case 'POST':
            handlePostRequests($action, $feedbackModel);
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Feedback API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Internal server error'], 500);
}

function handleGetRequests(string $action, Feedback $feedbackModel): void {
    switch ($action) {
        case 'summary':
            // Requires staff authentication
            if (!Security::hasRole('staff')) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }
            
            $summary = $feedbackModel->getTodaySummary();
            jsonResponse($summary);
            break;
            
        case 'recent':
            // Requires staff authentication
            if (!Security::hasRole('staff')) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }
            
            $limit = min((int) ($_GET['limit'] ?? 10), 50);
            $feedback = $feedbackModel->getRecent($limit);
            jsonResponse(['feedback' => $feedback]);
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

function handlePostRequests(string $action, Feedback $feedbackModel): void {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    switch ($action) {
        case 'submit':
            // Rate limiting
            if (!Security::checkRateLimit('feedback_submit', 5, 300)) {
                jsonResponse(['error' => 'Too many requests'], 429);
            }
            
            $queueId = $input['queue_id'] ?? '';
            $rating = (int) ($input['rating'] ?? 0);
            $comment = Security::sanitize($input['comment'] ?? '');
            
            // Validation
            if (empty($queueId)) {
                jsonResponse(['error' => 'Queue ID is required'], 400);
            }
            
            if ($rating < 1 || $rating > 5) {
                jsonResponse(['error' => 'Rating must be between 1 and 5'], 400);
            }
            
            // Check if feedback already exists
            $existing = $feedbackModel->getByQueueId($queueId);
            if ($existing) {
                jsonResponse(['error' => 'Feedback already submitted for this transaction'], 400);
            }
            
            // Simple sentiment analysis based on rating
            $sentiment = null;
            if ($rating >= 4) {
                $sentiment = 'positive';
            } elseif ($rating <= 2) {
                $sentiment = 'negative';
            } else {
                $sentiment = 'neutral';
            }
            
            $result = $feedbackModel->create([
                'queue_id' => $queueId,
                'rating' => $rating,
                'comment' => $comment ?: null,
                'sentiment' => $sentiment,
                'sentiment_score' => $rating / 5
            ]);
            
            if ($result) {
                jsonResponse(['success' => true, 'message' => 'Feedback submitted'], 201);
            } else {
                jsonResponse(['error' => 'Failed to submit feedback'], 500);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}
