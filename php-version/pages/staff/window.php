<?php
/**
 * Staff Window Management Page
 * PHP equivalent of Staff.tsx + WindowStaff.tsx
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../models/Window.php';

Security::startSecureSession();
Security::requireRole('staff');

$queueModel = new Queue();
$windowModel = new Window();

$selectedWindow = isset($_GET['window']) ? (int)$_GET['window'] : 1;
$selectedWindow = max(1, min(4, $selectedWindow));

$MAX_CUSTOMERS_PER_WINDOW = 3;
$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'call_next':
            $windowData = $windowModel->getById($selectedWindow);
            if ($windowData) {
                $result = $queueModel->callNext($windowData['id']);
                if ($result) {
                    $message = "Called Queue #" . str_pad($result['queue_number'], 3, '0', STR_PAD_LEFT) . " - " . htmlspecialchars($result['student_name']);
                    $messageType = 'success';
                } else {
                    $message = "No eligible customers waiting.";
                    $messageType = 'info';
                }
            }
            break;

        case 'complete':
            $queueId = Security::sanitize($_POST['queue_id'] ?? '');
            if ($queueId) {
                $windowData = $windowModel->getById($selectedWindow);
                if ($queueModel->complete($queueId)) {
                    $message = "Transaction completed successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to complete transaction.";
                    $messageType = 'error';
                }
            }
            break;

        case 'toggle_service':
            $service = Security::sanitize($_POST['service'] ?? '');
            if ($service && array_key_exists($service, TRANSACTION_TYPES)) {
                $windowData = $windowModel->getById($selectedWindow);
                if ($windowData) {
                    $disabled = json_decode($windowData['disabled_services'] ?? '[]', true) ?: [];
                    if (in_array($service, $disabled)) {
                        $disabled = array_values(array_diff($disabled, [$service]));
                        $message = TRANSACTION_TYPES[$service] . " enabled.";
                    } else {
                        $disabled[] = $service;
                        $message = TRANSACTION_TYPES[$service] . " disabled.";
                    }
                    $windowModel->updateDisabledServices($windowData['id'], $disabled);
                    $messageType = 'info';
                }
            }
            break;
    }
}

// Fetch current state
$window = $windowModel->getById($selectedWindow);
$waitingQueue = $queueModel->getWaitingQueue();
$inProgressQueue = $queueModel->getInProgressQueue();

// Get customers at this window
$windowQueue = array_filter($inProgressQueue, function ($item) use ($selectedWindow) {
    return (int)$item['window_number'] === $selectedWindow;
});
$windowQueue = array_values($windowQueue);
usort($windowQueue, fn($a, $b) => $a['queue_number'] - $b['queue_number']);

$disabledServices = json_decode($window['disabled_services'] ?? '[]', true) ?: [];
$allTypes = array_keys(TRANSACTION_TYPES);
$enabledCount = count($allTypes) - count($disabledServices);

// Filter eligible waiting queue
$eligibleWaiting = array_filter($waitingQueue, function ($item) use ($disabledServices) {
    return !in_array($item['transaction_type'], $disabledServices);
});
$eligibleWaiting = array_values($eligibleWaiting);

$canCallMore = count($windowQueue) < $MAX_CUSTOMERS_PER_WINDOW && count($eligibleWaiting) > 0;

$pageTitle = "Staff - Window $selectedWindow";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container" style="max-width: 700px; margin: 2rem auto; padding: 0 1rem;">

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: <?= $messageType === 'success' ? '#d4edda' : ($messageType === 'error' ? '#f8d7da' : '#d1ecf1') ?>; color: <?= $messageType === 'success' ? '#155724' : ($messageType === 'error' ? '#721c24' : '#0c5460') ?>;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Window Selector -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h2>🧑‍💼 Staff Control Panel</h2>
        </div>
        <div class="card-body">
            <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: #666;">Select Your Window</label>
            <div style="display: flex; gap: 0.5rem;">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <a href="?window=<?= $i ?>"
                       style="flex: 1; text-align: center; padding: 0.75rem; border-radius: 8px; text-decoration: none; font-weight: 600;
                              background: <?= $i === $selectedWindow ? '#4f46e5' : '#f3f4f6' ?>;
                              color: <?= $i === $selectedWindow ? '#fff' : '#374151' ?>;">
                        Window <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Window Panel -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; border-radius: 12px 12px 0 0; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0;">✅ Window <?= $selectedWindow ?></h2>
                <div style="display: flex; gap: 0.5rem;">
                    <span class="badge"><?= count($windowQueue) ?>/<?= $MAX_CUSTOMERS_PER_WINDOW ?></span>
                    <span class="badge"><?= count($eligibleWaiting) ?> eligible</span>
                </div>
            </div>
        </div>

        <div class="card-body" style="padding: 1.5rem;">

            <!-- Service Settings (collapsible) -->
            <details style="margin-bottom: 1.5rem;">
                <summary style="cursor: pointer; padding: 0.75rem 1rem; border: 1px solid #e5e7eb; border-radius: 8px; font-weight: 500;">
                    ⚙️ Services (<?= $enabledCount ?>/<?= count($allTypes) ?> enabled)
                </summary>
                <div style="margin-top: 0.75rem; padding: 1rem; background: #f9fafb; border-radius: 8px;">
                    <?php foreach ($allTypes as $service):
                        $isEnabled = !in_array($service, $disabledServices);
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
                            <span style="<?= !$isEnabled ? 'color: #9ca3af; text-decoration: line-through;' : '' ?>">
                                <?= TRANSACTION_TYPES[$service] ?>
                            </span>
                            <form method="POST" style="margin: 0;">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="toggle_service">
                                <input type="hidden" name="service" value="<?= $service ?>">
                                <button type="submit" style="padding: 0.25rem 0.75rem; border-radius: 999px; border: 1px solid <?= $isEnabled ? '#22c55e' : '#e5e7eb' ?>; background: <?= $isEnabled ? '#dcfce7' : '#f3f4f6' ?>; color: <?= $isEnabled ? '#166534' : '#6b7280' ?>; cursor: pointer; font-size: 0.75rem;">
                                    <?= $isEnabled ? 'ON' : 'OFF' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>

            <!-- Current Customers -->
            <?php if (count($windowQueue) > 0): ?>
                <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.75rem;">
                    👥 Serving <?= count($windowQueue) ?> Customer<?= count($windowQueue) > 1 ? 's' : '' ?>
                </p>
                <?php foreach ($windowQueue as $index => $item): ?>
                    <div style="border: 1px solid <?= $index === 0 ? '#bbf7d0' : '#e5e7eb' ?>; background: <?= $index === 0 ? '#f0fdf4' : '#fafafa' ?>; border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <?php if ($index === 0): ?>
                                    <span style="display: inline-block; background: #22c55e; color: #fff; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 999px; margin-bottom: 0.25rem;">Now Serving</span>
                                <?php endif; ?>
                                <div style="font-size: 1.75rem; font-weight: 700; font-family: monospace;">
                                    <?= str_pad($item['queue_number'], 3, '0', STR_PAD_LEFT) ?>
                                </div>
                                <p style="font-weight: 500; margin: 0.25rem 0;"><?= htmlspecialchars($item['student_name']) ?></p>
                                <span class="badge" style="background: #f3f4f6; color: #374151; font-size: 0.75rem;">
                                    <?= TRANSACTION_TYPES[$item['transaction_type']] ?? $item['transaction_type'] ?>
                                </span>
                                <?php if (!empty($item['student_id'])): ?>
                                    <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">ID: <?= htmlspecialchars($item['student_id']) ?></p>
                                <?php endif; ?>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="queue_id" value="<?= htmlspecialchars($item['id']) ?>">
                                <button type="submit" style="padding: 0.5rem 1rem; background: #22c55e; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                    ✓ Done
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem 0;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #f3f4f6; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.5rem;">🕐</div>
                    <p style="font-size: 1.1rem; font-weight: 500; color: #6b7280;">No Active Customers</p>
                    <p style="font-size: 0.875rem; color: #9ca3af;">Call customers to begin serving</p>
                </div>
            <?php endif; ?>

            <!-- Call Next Button -->
            <form method="POST" style="margin-top: 1rem;">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="call_next">
                <button type="submit" <?= !$canCallMore ? 'disabled' : '' ?>
                    style="width: 100%; padding: 0.875rem; font-size: 1.1rem; font-weight: 600; border: none; border-radius: 10px; cursor: <?= $canCallMore ? 'pointer' : 'not-allowed' ?>; background: <?= $canCallMore ? '#4f46e5' : '#d1d5db' ?>; color: #fff;">
                    📞 Call Next (<?= count($eligibleWaiting) ?> eligible)
                </button>
            </form>

            <?php if (count($windowQueue) >= $MAX_CUSTOMERS_PER_WINDOW): ?>
                <p style="text-align: center; font-size: 0.875rem; color: #9ca3af; margin-top: 0.5rem;">Window at maximum capacity</p>
            <?php endif; ?>

            <?php if (count($disabledServices) > 0): ?>
                <p style="text-align: center; font-size: 0.75rem; color: #9ca3af; margin-top: 0.5rem;">
                    Disabled: <?= implode(', ', array_map(fn($s) => TRANSACTION_TYPES[$s] ?? $s, $disabledServices)) ?>
                </p>
            <?php endif; ?>

            <!-- Next Eligible Preview -->
            <?php if (count($eligibleWaiting) > 0 && count($windowQueue) < $MAX_CUSTOMERS_PER_WINDOW): ?>
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.75rem;">Next Eligible</p>
                    <?php foreach (array_slice($eligibleWaiting, 0, 3) as $index => $item): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-radius: 8px; margin-bottom: 0.5rem; background: <?= $index === 0 ? '#eef2ff' : '#f9fafb' ?>;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-weight: 700; font-size: 1.1rem; font-family: monospace;">
                                    <?= str_pad($item['queue_number'], 3, '0', STR_PAD_LEFT) ?>
                                </span>
                                <span style="font-size: 0.875rem; color: #6b7280; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars($item['student_name']) ?>
                                </span>
                            </div>
                            <span class="badge" style="font-size: 0.7rem; background: #f3f4f6; color: #374151;">
                                <?= explode(' ', TRANSACTION_TYPES[$item['transaction_type']] ?? '')[0] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
