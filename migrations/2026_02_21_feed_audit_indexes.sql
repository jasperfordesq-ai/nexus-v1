-- Feed Audit: Add composite indexes for performance
-- Created: 2026-02-21
-- Context: Feed audit found main queries use (tenant_id, created_at) but only separate indexes exist

-- Composite index for main feed query (tenant_id + created_at DESC + id DESC)
CREATE INDEX IF NOT EXISTS idx_feed_posts_tenant_created ON feed_posts(tenant_id, created_at DESC, id DESC);

-- Composite index for like duplicate checking (fast unique-like lookups)
CREATE INDEX IF NOT EXISTS idx_likes_user_tenant_target ON likes(user_id, tenant_id, target_type, target_id);

-- Composite index for tenant-scoped comment queries
CREATE INDEX IF NOT EXISTS idx_comments_tenant_target ON comments(tenant_id, target_type, target_id);
