-- =========================================================
-- Migration: Add indexes for newsletter engagement queries
-- Purpose: Optimize SmartSegmentSuggestionService and NewsletterSegment
--          algorithm-based targeting queries
-- =========================================================

-- Index for newsletter_queue lookups by user and status
-- Used by: email_open_rate, email_click_rate, newsletters_received conditions
CREATE INDEX idx_newsletter_queue_user_status
ON newsletter_queue(user_id, status);

-- Index for newsletter_queue lookups by email (for joining with opens/clicks)
CREATE INDEX idx_newsletter_queue_email_newsletter
ON newsletter_queue(email, newsletter_id);

-- Index for newsletter_queue sent_at for date-range filtering
CREATE INDEX idx_newsletter_queue_sent_at
ON newsletter_queue(sent_at);

-- Index for newsletter_opens email lookups
-- Used by: email open rate calculations
CREATE INDEX idx_newsletter_opens_email_newsletter
ON newsletter_opens(email, newsletter_id);

-- Index for newsletter_clicks email lookups
-- Used by: email click rate calculations
CREATE INDEX idx_newsletter_clicks_email_newsletter
ON newsletter_clicks(email, newsletter_id);

-- Index for transactions by sender (used by transaction_count and CommunityRank)
CREATE INDEX idx_transactions_sender
ON transactions(sender_id);

-- Index for transactions by receiver
CREATE INDEX idx_transactions_receiver
ON transactions(receiver_id);

-- Index for listings (used by active_contributors suggestion and CommunityRank)
CREATE INDEX idx_listings_user_status
ON listings(user_id, status);

-- Index for users login recency queries
CREATE INDEX idx_users_last_login_tenant
ON users(tenant_id, is_approved, last_login_at);

-- Index for users creation date queries (new members)
CREATE INDEX idx_users_created_tenant
ON users(tenant_id, is_approved, created_at);

-- =========================================================
-- NOTE: Run each CREATE INDEX separately. If an index already
-- exists, you'll get an error - just skip that one and continue.
-- =========================================================
-- VERIFICATION:
-- SHOW INDEX FROM newsletter_queue;
-- SHOW INDEX FROM newsletter_opens;
-- SHOW INDEX FROM newsletter_clicks;
-- SHOW INDEX FROM transactions;
-- SHOW INDEX FROM listings;
-- SHOW INDEX FROM users;
