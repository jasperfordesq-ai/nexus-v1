-- Page Builder V2 - Database Schema
-- Clean, modern block-based page builder

-- Create page_blocks table
CREATE TABLE IF NOT EXISTS page_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    block_type VARCHAR(50) NOT NULL,
    block_data JSON NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_page_sort (page_id, sort_order),
    INDEX idx_block_type (block_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add builder_version column to pages table to track which builder was used
ALTER TABLE pages
ADD COLUMN IF NOT EXISTS builder_version VARCHAR(10) DEFAULT 'v2' AFTER content;

-- Example data for testing
-- INSERT INTO page_blocks (page_id, block_type, block_data, sort_order) VALUES
-- (30, 'hero', '{"title":"Welcome to Our Community","subtitle":"Connect, Share, Grow","backgroundImage":"/assets/images/hero.jpg","alignment":"center","height":"large"}', 0),
-- (30, 'richtext', '{"content":"<p>This is a modern page builder with real smart blocks!</p>","width":"normal"}', 1),
-- (30, 'members-grid', '{"limit":6,"columns":3,"orderBy":"created_at","filter":"all"}', 2);
