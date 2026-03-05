-- AI Integration Database Schema
-- Migration for Project NEXUS TimeBank
-- Run this migration to set up AI feature tables

-- AI conversation history
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255),
    provider VARCHAR(50),
    model VARCHAR(100),
    context_type VARCHAR(50) DEFAULT 'general',
    context_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_conv_user (tenant_id, user_id),
    INDEX idx_ai_conv_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI messages within conversations
CREATE TABLE IF NOT EXISTS ai_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    tokens_used INT DEFAULT 0,
    model VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_msg_conv (conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI usage tracking (for limits/billing)
CREATE TABLE IF NOT EXISTS ai_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    feature VARCHAR(50) NOT NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    cost_usd DECIMAL(10,6) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_usage_tenant (tenant_id, created_at),
    INDEX idx_ai_usage_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI-generated content cache
CREATE TABLE IF NOT EXISTS ai_content_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    cache_key VARCHAR(255) NOT NULL,
    content TEXT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ai_cache_key (tenant_id, cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI settings (admin-configurable, stored encrypted)
CREATE TABLE IF NOT EXISTS ai_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    is_encrypted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ai_settings_key (tenant_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User AI limits
CREATE TABLE IF NOT EXISTS ai_user_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    daily_limit INT DEFAULT 50,
    monthly_limit INT DEFAULT 1000,
    daily_used INT DEFAULT 0,
    monthly_used INT DEFAULT 0,
    last_reset_daily DATE NULL,
    last_reset_monthly DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ai_limits_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default AI settings for each tenant (run after creating tenant)
-- These can be updated via Admin > AI Settings
/*
INSERT INTO ai_settings (tenant_id, setting_key, setting_value, is_encrypted) VALUES
(1, 'ai_enabled', '1', 0),
(1, 'ai_provider', 'gemini', 0),
(1, 'ai_chat_enabled', '1', 0),
(1, 'ai_content_gen_enabled', '1', 0),
(1, 'ai_recommendations_enabled', '1', 0),
(1, 'ai_analytics_enabled', '1', 0),
(1, 'gemini_model', 'gemini-pro', 0),
(1, 'openai_model', 'gpt-4-turbo', 0),
(1, 'claude_model', 'claude-sonnet-4-20250514', 0),
(1, 'ollama_model', 'llama2', 0),
(1, 'ollama_host', 'http://localhost:11434', 0),
(1, 'default_daily_limit', '50', 0),
(1, 'default_monthly_limit', '1000', 0);
*/
