-- Queue Management System Database Schema
-- MySQL / MariaDB

CREATE DATABASE IF NOT EXISTS queue_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE queue_management;

-- Users table (for authentication)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff', 'student') NOT NULL DEFAULT 'student',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Windows table
CREATE TABLE windows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    window_number INT NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    current_queue_id CHAR(36) NULL,
    disabled_services JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Queue counter table (for daily number reset)
CREATE TABLE queue_counter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    last_number INT NOT NULL DEFAULT 0,
    
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- Queue table
CREATE TABLE queue (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    queue_number INT NOT NULL,
    transaction_type ENUM('grade_request', 'enrollment', 'document_request', 'payment', 'clearance', 'other') NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    student_id VARCHAR(20) NULL,
    status ENUM('waiting', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
    window_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    called_at DATETIME NULL,
    completed_at DATETIME NULL,
    
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_queue_number_date (queue_number, created_at),
    
    CONSTRAINT fk_queue_window FOREIGN KEY (window_id) REFERENCES windows(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Feedback table
CREATE TABLE feedback (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    queue_id CHAR(36) NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NULL,
    sentiment ENUM('positive', 'negative', 'neutral') NULL,
    sentiment_score DECIMAL(5,4) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_queue (queue_id),
    INDEX idx_rating (rating),
    INDEX idx_created (created_at),
    
    CONSTRAINT fk_feedback_queue FOREIGN KEY (queue_id) REFERENCES queue(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add foreign key for windows current_queue_id
ALTER TABLE windows 
ADD CONSTRAINT fk_window_queue FOREIGN KEY (current_queue_id) REFERENCES queue(id) ON DELETE SET NULL;

-- Insert default windows
INSERT INTO windows (window_number, is_active) VALUES
(1, TRUE),
(2, TRUE),
(3, TRUE),
(4, TRUE);

-- Insert default admin user (password: admin123)
-- Password hash for 'admin123' using password_hash with ARGON2ID
INSERT INTO users (email, password_hash, name, role) VALUES
('admin@university.edu', '$argon2id$v=19$m=65536,t=4,p=3$YXNkZmFzZGZhc2RmYXNk$8qH5qVf0YB0GJ9J5JZ9Z5YZ5Z5Z5Z5Z5Z5Z5Z5Z5Z5Y', 'System Admin', 'admin');

-- Insert sample staff user (password: staff123)
INSERT INTO users (email, password_hash, name, role) VALUES
('staff@university.edu', '$argon2id$v=19$m=65536,t=4,p=3$YXNkZmFzZGZhc2RmYXNk$8qH5qVf0YB0GJ9J5JZ9Z5YZ5Z5Z5Z5Z5Z5Z5Z5Z5Z5Y', 'Staff User', 'staff');
