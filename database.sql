-- CatControl Database Schema
-- Created for Debian 12 LXC with MariaDB

CREATE DATABASE IF NOT EXISTS catcontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE catcontrol;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    country VARCHAR(50) NOT NULL,
    city VARCHAR(100),
    allow_messages BOOLEAN DEFAULT TRUE,
    custom_background VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    first_login BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_email (email)
);

-- Password reset tokens
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- User preferences for field visibility
CREATE TABLE user_field_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    visible BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_field (user_id, field_name)
);

-- Kittens table
CREATE TABLE kittens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    color VARCHAR(100),
    mother VARCHAR(100),
    found_location VARCHAR(255),
    found_date DATE,
    tasso_id VARCHAR(50),
    ear_tattoo VARCHAR(50),
    postal_code VARCHAR(10),
    profile_image VARCHAR(255),
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner (owner_id),
    INDEX idx_birth_date (birth_date)
);

-- Kitten shared access (multiple users can manage same kitten)
CREATE TABLE kitten_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kitten_id INT NOT NULL,
    user_id INT NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kitten_id) REFERENCES kittens(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kitten_user (kitten_id, user_id)
);

-- Feeding records
CREATE TABLE feeding_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kitten_id INT NOT NULL,
    user_id INT NOT NULL,
    feeding_date DATETIME NOT NULL,
    weight_grams INT,
    food_amount_grams INT,
    food_type ENUM('katzenmilch', 'mischfutter', 'nassfutter', 'trockenfutter') DEFAULT 'katzenmilch',
    heating_pad_refilled BOOLEAN DEFAULT FALSE,
    stool_type ENUM('urin', 'kot', 'beides'),
    stool_consistency ENUM('fest', 'fluessig'),
    stool_color ENUM('braun', 'schwarz', 'orange', 'rot', 'grau', 'sonstiges'),
    stool_color_other VARCHAR(100),
    fitness_level INT DEFAULT 5 CHECK (fitness_level >= 0 AND fitness_level <= 10),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kitten_id) REFERENCES kittens(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_kitten_date (kitten_id, feeding_date),
    INDEX idx_feeding_date (feeding_date)
);

-- Veterinary records
CREATE TABLE veterinary_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kitten_id INT NOT NULL,
    user_id INT NOT NULL,
    visit_date DATE NOT NULL,
    veterinarian_name VARCHAR(100),
    diagnosis TEXT,
    vaccination TEXT,
    next_vaccination_date DATE,
    deworming BOOLEAN DEFAULT FALSE,
    deworming_medication VARCHAR(60),
    next_deworming_interval ENUM('1_week', '2_weeks', '4_weeks', '2_months', '3_months', '4_months', '6_months', '1_year'),
    tick_protection BOOLEAN DEFAULT FALSE,
    tick_protection_medication TEXT,
    next_tick_protection_interval ENUM('1_week', '2_weeks', '4_weeks', '2_months', '3_months', '4_months', '6_months', '1_year'),
    next_visit_date DATE,
    cost_eur DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kitten_id) REFERENCES kittens(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_kitten_visit (kitten_id, visit_date),
    INDEX idx_next_vaccination (next_vaccination_date),
    INDEX idx_next_visit (next_visit_date)
);

-- Kitten images
CREATE TABLE kitten_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kitten_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    is_profile_image BOOLEAN DEFAULT FALSE,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kitten_id) REFERENCES kittens(id) ON DELETE CASCADE,
    INDEX idx_kitten (kitten_id)
);

-- Internal messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    recipient_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    message_type ENUM('user_message', 'system_notification', 'appointment_reminder') DEFAULT 'user_message',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id),
    INDEX idx_created (created_at),
    INDEX idx_unread (recipient_id, is_read)
);

-- User blacklist for blocking messages
CREATE TABLE user_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    blocked_user_id INT NOT NULL,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (user_id, blocked_user_id)
);

-- System settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Reminder notifications (for automated reminders)
CREATE TABLE reminder_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kitten_id INT NOT NULL,
    user_id INT NOT NULL,
    reminder_type ENUM('vaccination', 'deworming', 'tick_protection', 'vet_visit') NOT NULL,
    reminder_date DATE NOT NULL,
    sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kitten_id) REFERENCES kittens(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reminder_date (reminder_date, sent)
);

-- Insert default admin user (password: katze)
INSERT INTO users (username, email, password_hash, country, allow_messages, first_login) 
VALUES ('admin', 'admin@localhost', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Deutschland', TRUE, TRUE);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('site_name', 'CatControl'),
('admin_email', 'admin@localhost');

-- Create database user
CREATE USER IF NOT EXISTS 'phpuser'@'localhost' IDENTIFIED BY 'changeme123';
CREATE USER IF NOT EXISTS 'phpuser'@'%' IDENTIFIED BY 'changeme123';

-- Grant privileges
GRANT ALL PRIVILEGES ON catcontrol.* TO 'phpuser'@'localhost';
GRANT ALL PRIVILEGES ON catcontrol.* TO 'phpuser'@'%';
FLUSH PRIVILEGES;