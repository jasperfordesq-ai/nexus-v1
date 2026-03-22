-- Interview self-scheduling: time slots for candidates to book
-- Migration: 2026_03_22_add_interview_availability.sql

CREATE TABLE IF NOT EXISTS job_interview_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    job_id INT NOT NULL,
    employer_user_id INT NOT NULL,
    slot_start DATETIME NOT NULL,
    slot_end DATETIME NOT NULL,
    is_booked TINYINT(1) DEFAULT 0,
    booked_by_user_id INT DEFAULT NULL,
    booked_at DATETIME DEFAULT NULL,
    interview_type ENUM('video','phone','in_person') DEFAULT 'video',
    meeting_link VARCHAR(500) DEFAULT NULL,
    location VARCHAR(500) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_job (tenant_id, job_id),
    INDEX idx_available (tenant_id, job_id, is_booked, slot_start),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (job_id) REFERENCES job_vacancies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
