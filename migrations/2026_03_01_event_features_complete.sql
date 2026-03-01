-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Event Module Features Migration
-- E1: Recurring events
-- E2: Capacity limits (column already exists as max_attendees)
-- E3: Waitlist management
-- E4: Event reminders
-- E5: Event cancellation (status column)
-- E6: Event attendance tracking
-- E7: Event series linking
--
-- All operations are idempotent (IF NOT EXISTS / IF EXISTS)

-- ============================================================================
-- E1: RECURRING EVENTS — recurrence rules table
-- ============================================================================
CREATE TABLE IF NOT EXISTS event_recurrence_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL COMMENT 'The parent/template event',
    tenant_id INT NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly', 'yearly', 'custom') NOT NULL DEFAULT 'weekly',
    interval_value INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Every N frequency units',
    days_of_week VARCHAR(50) DEFAULT NULL COMMENT 'Comma-separated: 0=Sun,1=Mon,...,6=Sat',
    day_of_month INT UNSIGNED DEFAULT NULL COMMENT 'For monthly: 1-31',
    month_of_year INT UNSIGNED DEFAULT NULL COMMENT 'For yearly: 1-12',
    rrule TEXT DEFAULT NULL COMMENT 'iCal RRULE string for custom patterns',
    ends_type ENUM('never', 'after_count', 'on_date') NOT NULL DEFAULT 'never',
    ends_after_count INT UNSIGNED DEFAULT NULL COMMENT 'Stop after N occurrences',
    ends_on_date DATE DEFAULT NULL COMMENT 'Stop on this date',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_recurrence_event (event_id),
    INDEX idx_recurrence_tenant (tenant_id),
    CONSTRAINT fk_recurrence_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_recurrence_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add recurrence tracking columns to events table
-- parent_event_id: links occurrences back to the template event
-- occurrence_date: the specific date this occurrence represents
-- is_recurring_template: marks the event as a recurrence template (not shown directly)
ALTER TABLE events ADD COLUMN IF NOT EXISTS parent_event_id INT DEFAULT NULL COMMENT 'Links to parent recurring event template';
ALTER TABLE events ADD COLUMN IF NOT EXISTS occurrence_date DATE DEFAULT NULL COMMENT 'Specific date for this occurrence';
ALTER TABLE events ADD COLUMN IF NOT EXISTS is_recurring_template TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'True if this event is a recurrence template';

CREATE INDEX IF NOT EXISTS idx_events_parent ON events (parent_event_id);
CREATE INDEX IF NOT EXISTS idx_events_occurrence ON events (occurrence_date);

-- ============================================================================
-- E2: CAPACITY LIMITS — max_attendees column likely exists; ensure it does
-- ============================================================================
ALTER TABLE events ADD COLUMN IF NOT EXISTS max_attendees INT UNSIGNED DEFAULT NULL COMMENT 'Maximum number of going attendees';

-- ============================================================================
-- E3: WAITLIST MANAGEMENT
-- ============================================================================
CREATE TABLE IF NOT EXISTS event_waitlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    position INT UNSIGNED NOT NULL COMMENT 'Position in waitlist queue',
    status ENUM('waiting', 'promoted', 'cancelled', 'expired') NOT NULL DEFAULT 'waiting',
    promoted_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_waitlist_event (event_id),
    INDEX idx_waitlist_user (user_id),
    INDEX idx_waitlist_tenant (tenant_id),
    INDEX idx_waitlist_status (event_id, status),
    INDEX idx_waitlist_position (event_id, position),
    UNIQUE KEY uk_waitlist_event_user (event_id, user_id),
    CONSTRAINT fk_waitlist_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- E4: EVENT REMINDERS
-- ============================================================================
CREATE TABLE IF NOT EXISTS event_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    remind_before_minutes INT UNSIGNED NOT NULL COMMENT '60=1hr, 1440=1day, 10080=1week',
    reminder_type ENUM('platform', 'email', 'both') NOT NULL DEFAULT 'both',
    sent_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When the reminder was actually sent',
    scheduled_for TIMESTAMP NOT NULL COMMENT 'When the reminder should fire',
    status ENUM('pending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reminder_event (event_id),
    INDEX idx_reminder_user (user_id),
    INDEX idx_reminder_tenant (tenant_id),
    INDEX idx_reminder_scheduled (scheduled_for, status),
    INDEX idx_reminder_pending (status, scheduled_for),
    UNIQUE KEY uk_reminder_event_user_time (event_id, user_id, remind_before_minutes),
    CONSTRAINT fk_reminder_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- E5: EVENT CANCELLATION — add status + cancellation columns to events
-- ============================================================================
ALTER TABLE events ADD COLUMN IF NOT EXISTS status ENUM('active', 'cancelled', 'postponed', 'draft') NOT NULL DEFAULT 'active';
ALTER TABLE events ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS cancelled_by INT DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_events_status ON events (status);

-- ============================================================================
-- E6: EVENT ATTENDANCE TRACKING
-- ============================================================================
CREATE TABLE IF NOT EXISTS event_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    checked_in_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When the attendee was checked in',
    checked_in_by INT DEFAULT NULL COMMENT 'User who checked them in (organizer/admin)',
    checked_out_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Optional: when they left',
    hours_credited DECIMAL(6,2) DEFAULT NULL COMMENT 'Time credits awarded',
    notes TEXT DEFAULT NULL COMMENT 'Organizer notes about attendance',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_attendance_event (event_id),
    INDEX idx_attendance_user (user_id),
    INDEX idx_attendance_tenant (tenant_id),
    UNIQUE KEY uk_attendance_event_user (event_id, user_id),
    CONSTRAINT fk_attendance_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- E7: EVENT SERIES LINKING
-- ============================================================================
CREATE TABLE IF NOT EXISTS event_series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    title VARCHAR(255) NOT NULL COMMENT 'Series title e.g. "Weekly Book Club"',
    description TEXT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_series_tenant (tenant_id),
    INDEX idx_series_creator (created_by),
    CONSTRAINT fk_series_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_series_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add series_id to events table to link events to a series
ALTER TABLE events ADD COLUMN IF NOT EXISTS series_id INT DEFAULT NULL COMMENT 'Links event to a series';
CREATE INDEX IF NOT EXISTS idx_events_series ON events (series_id);

-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT 'Event features migration complete' AS status;
SELECT 'Tables created: event_recurrence_rules, event_waitlist, event_reminders, event_attendance, event_series' AS created;
SELECT 'Columns added: parent_event_id, occurrence_date, is_recurring_template, status, cancellation_reason, cancelled_at, cancelled_by, series_id' AS columns_added;
