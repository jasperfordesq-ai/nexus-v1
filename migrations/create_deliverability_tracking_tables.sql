-- ============================================================================
-- Project Deliverability Tracking Module - Database Schema
-- ============================================================================
-- This migration creates tables for tracking project deliverables, milestones,
-- and delivery status across the NEXUS platform.
--
-- Tables created:
-- 1. deliverables - Main deliverables tracking table
-- 2. deliverable_milestones - Sub-tasks and milestones for each deliverable
-- 3. deliverable_history - Audit trail of status changes
-- 4. deliverable_comments - Collaboration comments on deliverables
-- ============================================================================

-- Table: deliverables
-- Tracks project deliverables with ownership, status, and metadata
CREATE TABLE IF NOT EXISTS deliverables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,

    -- Core identification
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',

    -- Ownership and assignment
    owner_id INT NOT NULL,  -- User who created/owns the deliverable
    assigned_to INT NULL,    -- User currently assigned to deliver
    assigned_group_id INT NULL,  -- Optional: assigned to a group

    -- Timeline
    start_date DATETIME NULL,
    due_date DATETIME NULL,
    completed_at DATETIME NULL,

    -- Status tracking
    status ENUM(
        'draft',           -- Initial planning
        'ready',           -- Ready to start
        'in_progress',     -- Active work
        'blocked',         -- Blocked by dependencies
        'review',          -- Under review
        'completed',       -- Successfully delivered
        'cancelled',       -- Cancelled/abandoned
        'on_hold'          -- Temporarily paused
    ) DEFAULT 'draft',

    -- Progress tracking
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,
    estimated_hours DECIMAL(8,2) NULL,
    actual_hours DECIMAL(8,2) NULL,

    -- Dependencies and relationships
    parent_deliverable_id INT NULL,  -- For sub-deliverables
    blocking_deliverable_ids JSON NULL,  -- IDs of deliverables this blocks
    depends_on_deliverable_ids JSON NULL,  -- IDs this depends on

    -- Metadata and tags
    tags JSON NULL,  -- Array of tag strings
    custom_fields JSON NULL,  -- Extensible custom data

    -- Deliverability metrics
    delivery_confidence ENUM('low', 'medium', 'high') DEFAULT 'medium',
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    risk_notes TEXT NULL,

    -- Collaboration
    watchers JSON NULL,  -- Array of user IDs watching this deliverable
    collaborators JSON NULL,  -- Array of user IDs collaborating

    -- Attachments and links
    attachment_urls JSON NULL,  -- Array of file URLs
    external_links JSON NULL,   -- Array of related links

    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_owner_id (owner_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_assigned_group (assigned_group_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_due_date (due_date),
    INDEX idx_parent (parent_deliverable_id),
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_tenant_assigned (tenant_id, assigned_to),

    -- Foreign key constraints
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_group_id) REFERENCES groups(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_deliverable_id) REFERENCES deliverables(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: deliverable_milestones
-- Tracks sub-tasks and milestones within a deliverable
CREATE TABLE IF NOT EXISTS deliverable_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    deliverable_id INT NOT NULL,

    -- Milestone details
    title VARCHAR(255) NOT NULL,
    description TEXT,
    order_position INT DEFAULT 0,

    -- Status and completion
    status ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
    completed_at DATETIME NULL,
    completed_by INT NULL,

    -- Timeline
    due_date DATETIME NULL,
    estimated_hours DECIMAL(8,2) NULL,

    -- Dependencies
    depends_on_milestone_ids JSON NULL,

    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_deliverable_id (deliverable_id),
    INDEX idx_status (status),
    INDEX idx_order (deliverable_id, order_position),

    -- Foreign key constraints
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (deliverable_id) REFERENCES deliverables(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: deliverable_history
-- Audit trail of all changes to deliverables
CREATE TABLE IF NOT EXISTS deliverable_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    deliverable_id INT NOT NULL,

    -- Change tracking
    action_type ENUM(
        'created',
        'status_changed',
        'assigned',
        'reassigned',
        'progress_updated',
        'deadline_changed',
        'priority_changed',
        'milestone_completed',
        'commented',
        'attachment_added',
        'completed',
        'cancelled',
        'reopened',
        'metadata_updated'
    ) NOT NULL,

    -- Who and when
    user_id INT NOT NULL,
    action_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Change details
    old_value TEXT NULL,  -- JSON or text representation of old value
    new_value TEXT NULL,  -- JSON or text representation of new value
    field_name VARCHAR(100) NULL,  -- Which field changed
    change_description TEXT NULL,  -- Human-readable description

    -- Context
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_deliverable_id (deliverable_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_timestamp (action_timestamp),
    INDEX idx_deliverable_timestamp (deliverable_id, action_timestamp),

    -- Foreign key constraints
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (deliverable_id) REFERENCES deliverables(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: deliverable_comments
-- Discussion and collaboration comments on deliverables
CREATE TABLE IF NOT EXISTS deliverable_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    deliverable_id INT NOT NULL,

    -- Comment details
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    comment_type ENUM('general', 'blocker', 'question', 'update', 'resolution') DEFAULT 'general',

    -- Threading
    parent_comment_id INT NULL,  -- For threaded discussions

    -- Reactions and engagement
    reactions JSON NULL,  -- {user_id: emoji} mapping
    is_pinned BOOLEAN DEFAULT FALSE,

    -- Status
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at DATETIME NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at DATETIME NULL,

    -- Mentions
    mentioned_user_ids JSON NULL,  -- Array of @mentioned user IDs

    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_deliverable_id (deliverable_id),
    INDEX idx_user_id (user_id),
    INDEX idx_parent_comment (parent_comment_id),
    INDEX idx_created_at (created_at),
    INDEX idx_deliverable_created (deliverable_id, created_at),

    -- Foreign key constraints
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (deliverable_id) REFERENCES deliverables(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES deliverable_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Initial Data Setup
-- ============================================================================

-- Note: No initial data is inserted. Deliverables will be created by users.

-- ============================================================================
-- Migration Complete
-- ============================================================================
-- The deliverability tracking module tables have been created successfully.
-- Next steps:
-- 1. Deploy corresponding PHP models
-- 2. Deploy services for business logic
-- 3. Deploy API controllers
-- 4. Configure permissions and access control
-- ============================================================================
