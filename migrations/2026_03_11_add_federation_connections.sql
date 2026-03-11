-- Federation Connections table
-- Enables cross-tenant connection requests between federated members.
-- Mirrors the same-tenant connections table but with tenant awareness.

CREATE TABLE IF NOT EXISTS federation_connections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_user_id INT UNSIGNED NOT NULL,
    requester_tenant_id INT UNSIGNED NOT NULL,
    receiver_user_id INT UNSIGNED NOT NULL,
    receiver_tenant_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    message VARCHAR(500) DEFAULT NULL COMMENT 'Optional message with connection request',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_requester (requester_user_id, requester_tenant_id),
    INDEX idx_receiver (receiver_user_id, receiver_tenant_id),
    INDEX idx_status (status),
    UNIQUE KEY uk_connection (requester_user_id, requester_tenant_id, receiver_user_id, receiver_tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
