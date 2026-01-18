-- ===================================================================
-- OPTIMIZE SMART MATCH QUERY PERFORMANCE
-- ===================================================================
-- This adds an index to speed up the batch user queries
-- Run this ONLY if you experience slow performance
-- ===================================================================

-- Check current indexes on users table
SHOW INDEXES FROM users;

-- Add composite index for smart match query
-- This speeds up: WHERE tenant_id = ? AND status = 'active' ORDER BY id
CREATE INDEX IF NOT EXISTS idx_users_smart_match
ON users (tenant_id, status, id);

-- Verify the index was created
SHOW INDEXES FROM users WHERE Key_name = 'idx_users_smart_match';

-- ===================================================================
-- This index will make the OFFSET queries much faster
-- Without it: Each OFFSET=220 query scans 220+ rows
-- With it: Direct lookup using index
-- ===================================================================
