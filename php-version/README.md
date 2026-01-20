# Queue Management System - PHP Version

A complete PHP implementation of the university registrar queue management system.

## 📁 Folder Structure

```
php-version/
├── api/                    # RESTful API endpoints
│   ├── queue.php          # Queue operations API
│   └── feedback.php       # Feedback API
├── config/                 # Configuration files
│   ├── database.php       # Database connection (singleton)
│   └── constants.php      # Application constants
├── database/               # Database files
│   └── schema.sql         # MySQL schema with all tables
├── includes/               # Core functionality
│   ├── security.php       # Security functions (auth, CSRF, validation)
│   └── functions.php      # Helper functions
├── models/                 # Data models
│   ├── User.php           # User authentication model
│   ├── Queue.php          # Queue operations model
│   ├── Window.php         # Window management model
│   └── Feedback.php       # Feedback model
├── pages/                  # Frontend pages
│   ├── index.php          # Queue display board (public)
│   ├── register.php       # Queue registration (public)
│   ├── my-ticket.php      # Ticket tracking (public)
│   ├── 403.php            # Access denied page
│   ├── 404.php            # Not found page
│   └── staff/             # Staff-only pages
│       ├── login.php      # Staff login
│       ├── dashboard.php  # Staff dashboard
│       ├── analytics.php  # Analytics page
│       └── logout.php     # Logout handler
├── templates/              # Shared templates
│   ├── header.php         # HTML header & navigation
│   └── footer.php         # HTML footer
├── .htaccess              # Apache configuration
└── README.md              # This file
```

## 🔒 Security Features Implemented

### 1. Authentication & Authorization
- Secure password hashing with Argon2ID
- Session management with secure cookies
- Role-based access control (admin, staff, student)
- Protected routes requiring authentication

### 2. CSRF Protection
- Token-based CSRF protection on all forms
- Token validation for POST requests
- Automatic token generation and embedding

### 3. Input Validation & SQL Injection Prevention
- Prepared statements with PDO (no raw SQL)
- Input sanitization with `htmlspecialchars`
- Email and student ID format validation
- Rate limiting on sensitive endpoints

### 4. Additional Security
- Secure session configuration (httponly, secure, samesite)
- Security headers via .htaccess
- Error logging without exposing details
- Protected directories (config, includes, models)

## 🚀 Installation

### Requirements
- PHP 8.0+
- MySQL 8.0+ or MariaDB 10.4+
- Apache with mod_rewrite enabled

### Setup Steps

1. **Create the database:**
   ```sql
   mysql -u root -p < database/schema.sql
   ```

2. **Configure database connection:**
   Edit `config/database.php` with your credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'queue_management');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

3. **Set up Apache virtual host:**
   ```apache
   <VirtualHost *:80>
       ServerName queue.yourdomain.com
       DocumentRoot /path/to/php-version
       
       <Directory /path/to/php-version>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

4. **Create staff accounts:**
   Use the default admin account or create new ones:
   - Email: `admin@university.edu`
   - Password: `admin123` (change immediately!)

## 📡 API Endpoints

### Queue API (`/api/queue.php`)

| Method | Action | Description | Auth Required |
|--------|--------|-------------|---------------|
| GET | `?action=status&number=123` | Get ticket status | No |
| GET | `?action=waiting` | Get waiting queue | No |
| GET | `?action=windows` | Get window status | No |
| GET | `?action=stats` | Get today's stats | Yes (staff) |
| POST | `?action=register` | Create new ticket | No |
| POST | `?action=cancel` | Cancel ticket | No |
| POST | `?action=call_next` | Call next in queue | Yes (staff) |
| POST | `?action=complete` | Complete transaction | Yes (staff) |

### Feedback API (`/api/feedback.php`)

| Method | Action | Description | Auth Required |
|--------|--------|-------------|---------------|
| GET | `?action=summary` | Get feedback summary | Yes (staff) |
| GET | `?action=recent` | Get recent feedback | Yes (staff) |
| POST | `?action=submit` | Submit feedback | No |

## 🔐 Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@university.edu | admin123 |
| Staff | staff@university.edu | staff123 |

⚠️ **Change these passwords immediately in production!**

## 📝 Transaction Types

- `grade_request` - Grade Request
- `enrollment` - Enrollment
- `document_request` - Document Request
- `payment` - Payment
- `clearance` - Clearance
- `other` - Other

## 🛠️ Customization

### Adding New Transaction Types
1. Update `TRANSACTION_TYPES` in `config/constants.php`
2. Update the ENUM in `database/schema.sql`
3. Run ALTER TABLE to add new values

### Changing Window Count
Insert or delete rows in the `windows` table.

### Modifying Rate Limits
Edit the rate limit parameters in `Security::checkRateLimit()` calls.

## 📄 License

MIT License - Feel free to use and modify for your institution.
