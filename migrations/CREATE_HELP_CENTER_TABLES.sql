-- ============================================================
-- CREATE HELP CENTER TABLES
-- Migration to create help_articles and help_article_feedback tables
-- Run this BEFORE update_help_center_content.sql
-- ============================================================

-- ============================================================
-- 1. CREATE HELP_ARTICLES TABLE
-- ============================================================

CREATE TABLE IF NOT EXISTS help_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    module_tag VARCHAR(50) NOT NULL DEFAULT 'core' COMMENT 'core, getting_started, wallet, listings, groups, events, volunteering, blog, polls, goals, governance, gamification, ai_assistant, sustainability, offline, mobile, insights, security, resources',
    content LONGTEXT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes for performance
    UNIQUE KEY idx_slug (slug),
    INDEX idx_module_tag (module_tag),
    INDEX idx_is_public (is_public),
    INDEX idx_view_count (view_count DESC),
    INDEX idx_module_views (module_tag, view_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. CREATE HELP_ARTICLE_FEEDBACK TABLE
-- ============================================================

CREATE TABLE IF NOT EXISTS help_article_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    helpful TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = helpful, 0 = not helpful',
    user_id INT UNSIGNED NULL COMMENT 'NULL for anonymous feedback',
    ip_address VARCHAR(45) NULL COMMENT 'For anonymous rate limiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for performance
    INDEX idx_article_id (article_id),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),

    -- Prevent duplicate feedback from same user
    UNIQUE KEY unique_user_feedback (article_id, user_id),

    -- Index for anonymous feedback lookup
    INDEX idx_anonymous_feedback (article_id, ip_address),

    -- Foreign key constraint
    CONSTRAINT fk_help_feedback_article
        FOREIGN KEY (article_id)
        REFERENCES help_articles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. SEED INITIAL HELP ARTICLES (Core/Getting Started)
-- ============================================================

INSERT INTO help_articles (title, slug, module_tag, content, is_public, created_at) VALUES
('Getting Started with the Platform', 'getting-started', 'getting_started',
'<h2>Welcome to Your Community Platform</h2>
<p>This guide will help you get started with the key features of our platform.</p>
<h3>Creating Your Profile</h3>
<p>Start by completing your profile. Add a photo, bio, and list your skills and interests to help others connect with you.</p>
<h3>Exploring the Community</h3>
<ul>
<li><strong>Feed:</strong> See updates from your community</li>
<li><strong>Groups:</strong> Join interest-based communities</li>
<li><strong>Events:</strong> Find and attend local events</li>
<li><strong>Marketplace:</strong> Exchange goods and services</li>
</ul>
<h3>Need Help?</h3>
<p>Browse our Help Center articles or contact your community administrator.</p>',
1, NOW()),

('Understanding Your Dashboard', 'understanding-dashboard', 'core',
'<h2>Your Personal Dashboard</h2>
<p>The dashboard is your home base on the platform, giving you quick access to everything you need.</p>
<h3>Key Sections</h3>
<ul>
<li><strong>Activity Feed:</strong> Recent posts and updates from your network</li>
<li><strong>Notifications:</strong> Messages, mentions, and important alerts</li>
<li><strong>Quick Actions:</strong> Create posts, listings, or events</li>
<li><strong>Stats:</strong> Your engagement metrics and achievements</li>
</ul>',
1, NOW()),

('How to Use the Marketplace', 'marketplace-guide', 'listings',
'<h2>Marketplace Guide</h2>
<p>The marketplace is where community members can offer and request goods and services.</p>
<h3>Creating a Listing</h3>
<ol>
<li>Click "Create Listing" from the marketplace page</li>
<li>Choose whether you are offering or requesting</li>
<li>Add a clear title and description</li>
<li>Set your price or indicate if it is free/trade</li>
<li>Add photos to make your listing stand out</li>
</ol>
<h3>Contacting Sellers</h3>
<p>Click "Message" on any listing to start a conversation with the seller.</p>',
1, NOW()),

('Joining and Creating Groups', 'groups-guide', 'groups',
'<h2>Community Groups</h2>
<p>Groups are spaces for members with shared interests to connect and collaborate.</p>
<h3>Finding Groups</h3>
<p>Browse the Groups directory to find communities that match your interests. Use filters to narrow down by category or location.</p>
<h3>Creating a Group</h3>
<p>If you cannot find a group that fits, create your own! Click "Create Group" and set up your community space with a name, description, and privacy settings.</p>',
1, NOW()),

('Events and RSVPs', 'events-guide', 'events',
'<h2>Events Guide</h2>
<p>Discover and participate in community events.</p>
<h3>Finding Events</h3>
<p>Browse upcoming events on the Events page. Filter by date, category, or location to find what interests you.</p>
<h3>RSVPing</h3>
<p>Click "RSVP" on any event to let the organizer know you are attending. You can also add events to your personal calendar.</p>
<h3>Creating Events</h3>
<p>Organize your own events by clicking "Create Event". Add all the details including date, time, location, and description.</p>',
1, NOW());

-- ============================================================
-- VERIFICATION
-- ============================================================

SELECT 'Help Center tables created successfully!' AS status;
SELECT COUNT(*) AS article_count FROM help_articles;
