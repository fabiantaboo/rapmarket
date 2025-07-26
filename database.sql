-- RapMarket.de Datenbank Schema
-- MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS rapmarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rapmarket;

-- Users Tabelle
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    points INT DEFAULT 1000,
    avatar VARCHAR(255) NULL,
    bio TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_points (points DESC),
    INDEX idx_created_at (created_at),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Events Tabelle
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    result_date TIMESTAMP NULL,
    status ENUM('draft', 'active', 'closed', 'resolved') DEFAULT 'draft',
    created_by INT NOT NULL,
    image_url VARCHAR(255) NULL,
    min_bet INT DEFAULT 10,
    max_bet INT DEFAULT 1000,
    total_bets_amount INT DEFAULT 0,
    total_bets_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_start_date (start_date),
    INDEX idx_end_date (end_date)
) ENGINE=InnoDB;

-- Event Options Tabelle (Wettoptionen)
CREATE TABLE event_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    odds DECIMAL(4,2) NOT NULL DEFAULT 2.00,
    is_winning_option BOOLEAN DEFAULT FALSE,
    total_bets_amount INT DEFAULT 0,
    total_bets_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_odds (odds)
) ENGINE=InnoDB;

-- Bets Tabelle
CREATE TABLE bets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    option_id INT NOT NULL,
    amount INT NOT NULL,
    odds DECIMAL(4,2) NOT NULL,
    potential_winnings INT NOT NULL,
    actual_winnings INT DEFAULT 0,
    status ENUM('active', 'won', 'lost', 'cancelled') DEFAULT 'active',
    placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES event_options(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_status (status),
    INDEX idx_placed_at (placed_at)
) ENGINE=InnoDB;

-- Point Transactions Tabelle
CREATE TABLE point_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    reason VARCHAR(255) NOT NULL,
    reference_id INT NULL,
    reference_type VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- User Logs Tabelle
CREATE TABLE user_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_ip (ip_address),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Rate Limits Tabelle
CREATE TABLE rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_ip_action (ip, action),
    INDEX idx_ip (ip),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- User Sessions Tabelle (optional für erweiterte Session-Verwaltung)
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- Comments Tabelle (für Community-Funktionen)
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    parent_id INT NULL,
    content TEXT NOT NULL,
    likes_count INT DEFAULT 0,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Comment Likes Tabelle
CREATE TABLE comment_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_comment (user_id, comment_id),
    INDEX idx_comment_id (comment_id)
) ENGINE=InnoDB;

-- Notifications Tabelle
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Settings Tabelle
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Standard-Einstellungen einfügen
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'RapMarket.de', 'Name der Website'),
('starting_points', '1000', 'Startpunkte für neue User'),
('min_bet_amount', '10', 'Mindest-Wetteinsatz'),
('max_bet_amount', '1000', 'Maximal-Wetteinsatz'),
('daily_bonus_points', '50', 'Tägliche Bonus-Punkte'),
('maintenance_mode', '0', 'Wartungsmodus (0=aus, 1=an)');

-- Sample Events einfügen
INSERT INTO events (title, description, category, start_date, end_date, status, created_by) VALUES
('Capital Bra vs. Apache 207 - Streaming Battle', 'Wer erreicht mehr Streams in der ersten Woche nach Release?', 'streaming', '2024-08-15 00:00:00', '2024-08-22 23:59:59', 'active', 1),
('18 Karat Album Charts Position', 'Wird das neue 18 Karat Album Platz 1 der deutschen Charts erreichen?', 'charts', '2024-08-20 00:00:00', '2024-08-27 23:59:59', 'active', 1),
('Bonez MC Tour 2024', 'Wie viele ausverkaufte Shows wird die Bonez MC Tour 2024 haben?', 'tour', '2024-09-01 00:00:00', '2024-12-31 23:59:59', 'active', 1);

-- Sample Event Options
INSERT INTO event_options (event_id, option_text, odds) VALUES
(1, 'Capital Bra gewinnt', 1.80),
(1, 'Apache 207 gewinnt', 2.10),
(2, 'Ja, Platz 1', 1.50),
(2, 'Nein, nicht Platz 1', 2.50),
(3, 'Unter 10 Shows', 3.00),
(3, '10-20 Shows', 1.80),
(3, 'Über 20 Shows', 2.20);

-- Admin User erstellen (Passwort: admin123)
INSERT INTO users (username, email, password, points, is_admin, is_verified) VALUES
('admin', 'admin@rapmarket.de', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 10000, TRUE, TRUE);

-- Views für häufige Abfragen
CREATE VIEW user_stats AS
SELECT 
    u.id,
    u.username,
    u.points,
    COUNT(b.id) as total_bets,
    SUM(CASE WHEN b.status = 'won' THEN 1 ELSE 0 END) as won_bets,
    SUM(CASE WHEN b.status = 'lost' THEN 1 ELSE 0 END) as lost_bets,
    SUM(CASE WHEN b.status = 'won' THEN b.actual_winnings ELSE 0 END) as total_winnings,
    SUM(b.amount) as total_wagered
FROM users u
LEFT JOIN bets b ON u.id = b.user_id
WHERE u.is_active = 1
GROUP BY u.id, u.username, u.points;

-- Leaderboard View
CREATE VIEW leaderboard AS
SELECT 
    ROW_NUMBER() OVER (ORDER BY points DESC) as rank,
    id,
    username,
    points,
    (SELECT COUNT(*) FROM bets WHERE user_id = users.id AND status = 'won') as wins,
    created_at
FROM users 
WHERE is_active = 1 
ORDER BY points DESC 
LIMIT 100;

-- Triggers für automatische Updates
DELIMITER $$

CREATE TRIGGER update_event_totals_after_bet_insert
AFTER INSERT ON bets
FOR EACH ROW
BEGIN
    UPDATE events 
    SET total_bets_amount = total_bets_amount + NEW.amount,
        total_bets_count = total_bets_count + 1
    WHERE id = NEW.event_id;
    
    UPDATE event_options 
    SET total_bets_amount = total_bets_amount + NEW.amount,
        total_bets_count = total_bets_count + 1
    WHERE id = NEW.option_id;
END$$

CREATE TRIGGER update_comment_likes_count
AFTER INSERT ON comment_likes
FOR EACH ROW
BEGIN
    UPDATE comments 
    SET likes_count = likes_count + 1 
    WHERE id = NEW.comment_id;
END$$

CREATE TRIGGER decrease_comment_likes_count
AFTER DELETE ON comment_likes
FOR EACH ROW
BEGIN
    UPDATE comments 
    SET likes_count = likes_count - 1 
    WHERE id = OLD.comment_id;
END$$

DELIMITER ;

-- Indizes für Performance-Optimierung
CREATE INDEX idx_bets_user_status ON bets(user_id, status);
CREATE INDEX idx_events_status_date ON events(status, start_date);
CREATE INDEX idx_users_points_active ON users(points DESC, is_active);

-- Cleanup-Job für alte Daten (manuell ausführen)
-- DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
-- DELETE FROM user_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
-- DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);