<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="University Registrar Queue Management System">
    <title><?= htmlspecialchars($pageTitle ?? 'Queue Management System') ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
        }
        
        .card {
            border-radius: 0.75rem;
            border: 1px solid rgba(0,0,0,0.08);
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #2563eb);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        
        .display-1 {
            font-weight: 700;
        }
        
        .badge {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <i class="bi bi-people-fill me-2"></i>Queue System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/index.php">
                            <i class="bi bi-display me-1"></i>Queue Board
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/register.php">
                            <i class="bi bi-ticket me-1"></i>Get Ticket
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/my-ticket.php">
                            <i class="bi bi-search me-1"></i>My Ticket
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/staff/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/staff/login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Staff Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
