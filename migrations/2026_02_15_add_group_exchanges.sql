-- Group exchanges: multi-participant exchange system
-- Allows multiple providers and receivers in a single exchange
-- Supports equal, custom, and weighted hour splits

CREATE TABLE IF NOT EXISTS group_exchanges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    organizer_id INT NOT NULL,
    listing_id INT DEFAULT NULL,
    status ENUM('draft','pending_participants','pending_broker','active','pending_confirmation','completed','cancelled','disputed') NOT NULL DEFAULT 'draft',
    split_type ENUM('equal','custom','weighted') NOT NULL DEFAULT 'equal',
    total_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
    broker_id INT DEFAULT NULL,
    broker_notes TEXT,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_organizer (organizer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_exchange_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_exchange_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('provider','receiver') NOT NULL,
    hours DECIMAL(10,2) NOT NULL DEFAULT 0,
    weight DECIMAL(5,2) DEFAULT 1.00,
    confirmed TINYINT(1) NOT NULL DEFAULT 0,
    confirmed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_exchange_user_role (group_exchange_id, user_id, role),
    INDEX idx_exchange (group_exchange_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
