# Queue Management System - Summary & PHP Prompt

## System Overview

A **Student Services Queue Management System** for campus/university registrar offices with:

### Core Features
1. **Student Registration** (`/register`) - Students join queue by entering name, optional ID, and selecting transaction type
2. **My Ticket** (`/my-ticket`) - Students track their ticket status in real-time
3. **Staff Dashboard** (`/staff`) - Staff manage windows, call next customers, complete transactions
4. **Display Board** (`/display`) - Public display showing current queue status
5. **Analytics** (`/analytics`) - Feedback and sentiment analysis dashboard

### Transaction Types
- Grade Request
- Enrollment
- Document Request
- Payment
- Clearance
- Other

### Queue Flow
1. Student registers → Gets queue number
2. Staff calls next → Student moves to "In Progress"
3. Staff completes → Student prompted for feedback
4. Feedback stored with sentiment analysis

---

## PHP Development Prompt

Use this prompt for building with PHP/MySQL:

```
Build a Student Services Queue Management System with PHP and MySQL.

## Database Schema

### Tables

1. **queue** - Main queue entries
   - id (UUID, PRIMARY KEY)
   - queue_number (INT, NOT NULL)
   - student_name (VARCHAR(255), NOT NULL)
   - student_id (VARCHAR(50), NULLABLE)
   - transaction_type (ENUM: 'grade_request', 'enrollment', 'document_request', 'payment', 'clearance', 'other')
   - status (ENUM: 'waiting', 'in_progress', 'completed', 'cancelled', DEFAULT 'waiting')
   - window_id (INT, NULLABLE, FK to windows.id)
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
   - called_at (TIMESTAMP, NULLABLE)
   - completed_at (TIMESTAMP, NULLABLE)

2. **windows** - Service windows
   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
   - window_number (INT, NOT NULL)
   - is_active (BOOLEAN, DEFAULT TRUE)
   - current_queue_id (UUID, NULLABLE)
   - disabled_services (JSON, DEFAULT '[]')
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

3. **queue_counter** - Daily queue number tracking
   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
   - date (DATE, UNIQUE)
   - last_number (INT, DEFAULT 0)

4. **feedback** - Customer feedback
   - id (UUID, PRIMARY KEY)
   - queue_id (UUID, FK to queue.id)
   - rating (INT, 1-5)
   - comment (TEXT, NULLABLE)
   - sentiment (VARCHAR(50), NULLABLE)
   - sentiment_score (DECIMAL(5,4), NULLABLE)
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

5. **users** - Staff authentication
   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
   - username (VARCHAR(100), UNIQUE, NOT NULL)
   - password_hash (VARCHAR(255), NOT NULL)
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

6. **user_roles** - Role-based access control
   - id (INT, PRIMARY KEY, AUTO_INCREMENT)
   - user_id (INT, FK to users.id ON DELETE CASCADE)
   - role (ENUM: 'admin', 'staff', 'viewer')
   - UNIQUE(user_id, role)

## CRITICAL SECURITY REQUIREMENTS

### 1. Authentication & Authorization
- Staff pages (staff dashboard, analytics) require login
- Use PHP sessions with secure cookies
- Hash passwords with password_hash() and verify with password_verify()
- NEVER store roles in client-side storage (localStorage/cookies that can be modified)

### 2. Role-Based Access Control
- Create has_role() function to check user permissions
- Roles stored in separate user_roles table (NOT in users table)
- Validate role server-side on EVERY protected request

```php
function has_role($pdo, $user_id, $role) {
    $stmt = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role = ?");
    $stmt->execute([$user_id, $role]);
    return $stmt->fetchColumn() !== false;
}

// Protect staff routes
function require_staff_role($pdo) {
    session_start();
    if (!isset($_SESSION['user_id']) || !has_role($pdo, $_SESSION['user_id'], 'staff')) {
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized']));
    }
}
```

### 3. Prevent Queue Manipulation
- Public users can ONLY:
  - INSERT their own queue entry (with server-generated queue number)
  - SELECT their own queue entry (by queue_id stored in session)
  - UPDATE only to cancel their own entry
- Staff users can:
  - SELECT all queue entries
  - UPDATE status, window_id, called_at, completed_at

```php
// Public queue creation - validate and sanitize
function create_queue_entry($pdo, $student_name, $transaction_type, $student_id = null) {
    // Validate transaction type
    $valid_types = ['grade_request', 'enrollment', 'document_request', 'payment', 'clearance', 'other'];
    if (!in_array($transaction_type, $valid_types)) {
        throw new Exception('Invalid transaction type');
    }
    
    // Sanitize inputs
    $student_name = htmlspecialchars(trim($student_name), ENT_QUOTES, 'UTF-8');
    if (strlen($student_name) < 2 || strlen($student_name) > 255) {
        throw new Exception('Invalid name length');
    }
    
    // Get next queue number atomically
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO queue_counter (date, last_number) 
            VALUES (CURDATE(), 1)
            ON DUPLICATE KEY UPDATE last_number = last_number + 1
        ");
        $stmt->execute();
        
        $stmt = $pdo->query("SELECT last_number FROM queue_counter WHERE date = CURDATE()");
        $queue_number = $stmt->fetchColumn();
        
        $id = generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO queue (id, queue_number, student_name, student_id, transaction_type, status)
            VALUES (?, ?, ?, ?, ?, 'waiting')
        ");
        $stmt->execute([$id, $queue_number, $student_name, $student_id, $transaction_type]);
        
        $pdo->commit();
        return ['id' => $id, 'queue_number' => $queue_number];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
```

### 4. Protect Student Data
- Queue display should show queue NUMBER only, not student names
- Student names visible only to:
  - The student themselves (matching their session queue_id)
  - Authenticated staff members

```php
// Public display - numbers only
function get_public_queue($pdo) {
    $stmt = $pdo->query("
        SELECT queue_number, status, window_id, transaction_type
        FROM queue 
        WHERE DATE(created_at) = CURDATE() AND status IN ('waiting', 'in_progress')
        ORDER BY queue_number
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Staff view - includes names
function get_staff_queue($pdo) {
    require_staff_role($pdo);
    $stmt = $pdo->query("
        SELECT id, queue_number, student_name, student_id, status, 
               window_id, transaction_type, created_at, called_at
        FROM queue 
        WHERE DATE(created_at) = CURDATE()
        ORDER BY queue_number
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### 5. Input Validation & SQL Injection Prevention
- Use prepared statements for ALL database queries
- Validate all inputs server-side
- Use CSRF tokens for all forms

```php
// CSRF protection
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

## Pages to Implement

1. **index.php** - Landing page with links
2. **register.php** - Student queue registration form
3. **my-ticket.php** - Student ticket status (uses session to identify)
4. **display.php** - Public queue display (auto-refresh, numbers only)
5. **login.php** - Staff login
6. **staff.php** - Staff dashboard (protected)
7. **analytics.php** - Feedback analytics (protected)

## Real-time Updates (Polling)

```javascript
// Client-side polling for queue updates
setInterval(async () => {
    const response = await fetch('/api/queue-status.php?id=' + ticketId);
    const data = await response.json();
    updateUI(data);
}, 5000);
```

## Feedback System

After transaction completion:
1. Show rating modal (1-5 stars)
2. Optional comment field
3. Server-side sentiment analysis (optional)
4. Store in feedback table linked to queue_id
```

---

## Security Issues Fixed

| Issue | Problem | Solution |
|-------|---------|----------|
| **PUBLIC_USER_DATA** | Student names exposed publicly | Only show queue numbers publicly; names visible to staff/self only |
| **UNRESTRICTED_DATA_MODIFICATION** | Anyone can manipulate queue | Require staff auth for updates; validate public inserts |
| **RLS_DISABLED** | No row-level security | Implement role checks on all protected endpoints |

---

## Key Differences: React vs PHP

| Feature | Current (React/Supabase) | PHP Implementation |
|---------|--------------------------|-------------------|
| Real-time | Supabase Realtime channels | AJAX polling (5s intervals) |
| Auth | Supabase Auth | PHP Sessions + bcrypt |
| Database | PostgreSQL (Supabase) | MySQL/MariaDB |
| Frontend | React SPA | PHP templates or AJAX |
| Hosting | Lovable Cloud | Traditional LAMP stack |
