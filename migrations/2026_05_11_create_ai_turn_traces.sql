-- Migration: Create ai_turn_traces table
--
-- One row per AI chat turn (user message + assistant reply). Captures
-- model, provider, tool calls, token usage, latency, and optional user
-- feedback (thumbs up/down). Used to compute the admin dashboard metrics
-- (total cost, average latency, most-called tools) and to surface
-- "unanswered" questions (downvotes) for the curated module docs loop.

CREATE TABLE IF NOT EXISTS ai_turn_traces (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NULL,
    message_id      INT UNSIGNED NULL,
    user_text       TEXT NOT NULL,
    assistant_text  MEDIUMTEXT NULL,
    provider        VARCHAR(40) NULL,
    model           VARCHAR(80) NULL,
    tokens_input    INT UNSIGNED NULL,
    tokens_output   INT UNSIGNED NULL,
    tokens_total    INT UNSIGNED NULL,
    cost_usd        DECIMAL(10, 6) NULL,
    latency_ms      INT UNSIGNED NULL,
    tool_calls      JSON NULL,
    error           VARCHAR(255) NULL,
    feedback        ENUM('up', 'down') NULL,
    feedback_note   VARCHAR(500) NULL,
    feedback_at     TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant_created (tenant_id, created_at),
    INDEX idx_tenant_feedback (tenant_id, feedback),
    INDEX idx_message (message_id),
    INDEX idx_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
