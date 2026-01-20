<?php
/**
 * Queue API Endpoints
 * RESTful API for queue operations
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Queue.php';
require_once __DIR__ . '/../models/Window.php';

Security::startSecureSession();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$queueModel = new Queue();
$windowModel = new Window();

try {
    switch ($method) {
        case 'GET':
            handleGetRequests($action, $queueModel, $windowModel);
            break;
            
        case 'POST':
            handlePostRequests($action, $queueModel, $windowModel);
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Internal server error'], 500);
}

function handleGetRequests(string $action, Queue $queueModel, Window $windowModel): void {
    switch ($action) {
        case 'status':
            // Get queue status by number
            $number = (int) ($_GET['number'] ?? 0);
            if (!$number) {
                jsonResponse(['error' => 'Queue number required'], 400);
            }
            
            $ticket = $queueModel->getByQueueNumber($number);
            if (!$ticket) {
                jsonResponse(['error' => 'Ticket not found'], 404);
            }
            
            $response = [
                'queue_number' => $ticket['queue_number'],
                'status' => $ticket['status'],
                'transaction_type' => $ticket['transaction_type'],
                'window_number' => $ticket['window_number'] ?? null,
                'created_at' => $ticket['created_at']
            ];
            
            if ($ticket['status'] === 'waiting') {
                $response['position'] = $queueModel->getQueuePosition($ticket['id']);
            }
            
            jsonResponse($response);
            break;
            
        case 'waiting':
            // Get waiting queue
            $queue = $queueModel->getWaitingQueue();
            jsonResponse(['queue' => $queue, 'count' => count($queue)]);
            break;
            
        case 'windows':
            // Get all windows status
            $windows = $windowModel->getAll();
            jsonResponse(['windows' => $windows]);
            break;
            
        case 'stats':
            // Get today's statistics (requires authentication)
            if (!Security::isAuthenticated()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }
            $stats = $queueModel->getTodayStats();
            jsonResponse($stats);
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}

function handlePostRequests(string $action, Queue $queueModel, Window $windowModel): void {
    // Validate CSRF for state-changing operations
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    switch ($action) {
        case 'register':
            // Rate limiting
            if (!Security::checkRateLimit('api_register', 10, 60)) {
                jsonResponse(['error' => 'Too many requests'], 429);
            }
            
            $studentName = Security::sanitize($input['student_name'] ?? '');
            $studentId = Security::sanitize($input['student_id'] ?? '');
            $transactionType = Security::sanitize($input['transaction_type'] ?? '');
            
            // Validation
            if (empty($studentName)) {
                jsonResponse(['error' => 'Student name is required'], 400);
            }
            
            if (!array_key_exists($transactionType, TRANSACTION_TYPES)) {
                jsonResponse(['error' => 'Invalid transaction type'], 400);
            }
            
            if (!empty($studentId) && !Security::validateStudentId($studentId)) {
                jsonResponse(['error' => 'Invalid student ID format'], 400);
            }
            
            $ticket = $queueModel->create([
                'student_name' => $studentName,
                'student_id' => $studentId ?: null,
                'transaction_type' => $transactionType
            ]);
            
            if ($ticket) {
                jsonResponse([
                    'success' => true,
                    'queue_number' => $ticket['queue_number'],
                    'transaction_type' => $ticket['transaction_type'],
                    'created_at' => $ticket['created_at']
                ], 201);
            } else {
                jsonResponse(['error' => 'Failed to create queue entry'], 500);
            }
            break;
            
        case 'cancel':
            $queueId = $input['queue_id'] ?? '';
            
            if (empty($queueId)) {
                jsonResponse(['error' => 'Queue ID is required'], 400);
            }
            
            if ($queueModel->cancel($queueId)) {
                jsonResponse(['success' => true, 'message' => 'Ticket cancelled']);
            } else {
                jsonResponse(['error' => 'Failed to cancel ticket'], 400);
            }
            break;
            
        case 'call_next':
            // Requires staff authentication
            if (!Security::hasRole('staff')) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }
            
            $windowId = (int) ($input['window_id'] ?? 0);
            $transactionType = $input['transaction_type'] ?? null;
            
            if (!$windowId) {
                jsonResponse(['error' => 'Window ID is required'], 400);
            }
            
            $next = $queueModel->callNext($windowId, $transactionType);
            
            if ($next) {
                jsonResponse([
                    'success' => true,
                    'queue_number' => $next['queue_number'],
                    'student_name' => $next['student_name'],
                    'transaction_type' => $next['transaction_type']
                ]);
            } else {
                jsonResponse(['error' => 'No one in queue'], 404);
            }
            break;
            
        case 'complete':
            // Requires staff authentication
            if (!Security::hasRole('staff')) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }
            
            $queueId = $input['queue_id'] ?? '';
            
            if (empty($queueId)) {
                jsonResponse(['error' => 'Queue ID is required'], 400);
            }
            
            if ($queueModel->complete($queueId)) {
                jsonResponse(['success' => true, 'message' => 'Transaction completed']);
            } else {
                jsonResponse(['error' => 'Failed to complete transaction'], 400);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
}
