-- ============================================================
-- HELP CENTER COMPLETE SETUP - January 2026
-- Full database setup with all tables and comprehensive articles
-- Run this migration to set up a complete Help Center
-- ============================================================

-- ============================================================
-- 1. CREATE HELP_ARTICLES TABLE
-- ============================================================

CREATE TABLE IF NOT EXISTS help_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    module_tag VARCHAR(50) NOT NULL DEFAULT 'core' COMMENT 'Module category for the article',
    content LONGTEXT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY idx_slug (slug),
    INDEX idx_module_tag (module_tag),
    INDEX idx_is_public (is_public),
    INDEX idx_view_count (view_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. CREATE HELP_ARTICLE_FEEDBACK TABLE
-- ============================================================

CREATE TABLE IF NOT EXISTS help_article_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    helpful TINYINT(1) NOT NULL DEFAULT 1,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_article_id (article_id),
    UNIQUE KEY unique_user_feedback (article_id, user_id),
    INDEX idx_anonymous_feedback (article_id, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. COMPREHENSIVE HELP ARTICLES
-- Uses INSERT ... ON DUPLICATE KEY UPDATE for safe re-running
-- ============================================================

-- --------------------------------
-- GETTING STARTED MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Getting Started with the Platform', 'getting-started', 'getting_started',
'<h2>Welcome to Your Community Platform!</h2>
<p>This guide will help you get up and running quickly. Whether you''re looking to offer your skills, find help, or connect with your community, we''ve made it simple to get started.</p>

<h3>Step 1: Complete Your Profile</h3>
<p>A complete profile helps others know who you are and what you offer:</p>
<ul>
    <li><strong>Profile Photo:</strong> Add a friendly photo so people can recognize you</li>
    <li><strong>Bio:</strong> Write a brief introduction about yourself</li>
    <li><strong>Skills:</strong> List what you can offer to the community</li>
    <li><strong>Location:</strong> Set your area to find nearby members</li>
</ul>

<h3>Step 2: Explore the Community</h3>
<p>Here''s what you can do on the platform:</p>
<ul>
    <li><strong>Marketplace:</strong> Browse offers and requests from community members</li>
    <li><strong>Community Hubs:</strong> Join groups based on interests or location</li>
    <li><strong>Events:</strong> Find and attend local community events</li>
    <li><strong>Messages:</strong> Connect directly with other members</li>
</ul>

<h3>Step 3: Make Your First Exchange</h3>
<p>Ready to participate? You can:</p>
<ol>
    <li>Browse the marketplace for services you need</li>
    <li>Post your own offer or request</li>
    <li>Message someone to arrange an exchange</li>
    <li>Complete the exchange and leave a review</li>
</ol>

<h3>Need More Help?</h3>
<p>Browse the other articles in our Help Center or contact your community administrator if you have questions.</p>',
1, FLOOR(RAND() * 200) + 50)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Creating Your Profile', 'creating-profile', 'getting_started',
'<h2>Set Up Your Profile</h2>
<p>Your profile is your identity in the community. A well-crafted profile helps build trust and makes it easier for others to connect with you.</p>

<h3>Profile Photo</h3>
<p>Add a clear, friendly photo of yourself. Profiles with photos receive significantly more engagement than those without.</p>

<h3>Writing Your Bio</h3>
<p>Your bio should include:</p>
<ul>
    <li>A brief introduction about yourself</li>
    <li>What brings you to the community</li>
    <li>Your interests and passions</li>
    <li>What you hope to offer or gain</li>
</ul>

<h3>Adding Your Skills</h3>
<p>List the skills you can share with others. Be specific - instead of "gardening," try "vegetable garden planning" or "composting expertise."</p>

<h3>Location Settings</h3>
<p>Setting your location helps you find members and services nearby. Your exact address is never shared - only your general area is visible to others.</p>

<h3>Privacy Options</h3>
<p>You control what''s visible on your profile. Visit Settings to customize your privacy preferences.</p>',
1, FLOOR(RAND() * 150) + 30)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- CORE / PLATFORM BASICS MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Understanding Your Dashboard', 'understanding-dashboard', 'core',
'<h2>Your Personal Dashboard</h2>
<p>The dashboard is your home base on the platform, giving you quick access to everything you need.</p>

<h3>Activity Feed</h3>
<p>See recent updates from your network, including:</p>
<ul>
    <li>New posts from groups you''ve joined</li>
    <li>Updates from people you follow</li>
    <li>Community announcements</li>
</ul>

<h3>Notifications</h3>
<p>Stay informed about:</p>
<ul>
    <li>New messages</li>
    <li>Responses to your posts</li>
    <li>Event reminders</li>
    <li>Transaction updates</li>
</ul>

<h3>Quick Actions</h3>
<p>From your dashboard, you can quickly:</p>
<ul>
    <li>Create a new listing</li>
    <li>Post an update</li>
    <li>Start a new message</li>
    <li>Browse nearby offers</li>
</ul>

<h3>Your Stats</h3>
<p>Track your community engagement with stats like:</p>
<ul>
    <li>Transactions completed</li>
    <li>Community impact hours</li>
    <li>Badges earned</li>
</ul>',
1, FLOOR(RAND() * 120) + 40)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Navigating the Platform', 'navigation-guide', 'core',
'<h2>Finding Your Way Around</h2>
<p>Our platform is designed to be intuitive and easy to navigate. Here''s a guide to the main sections.</p>

<h3>Main Navigation</h3>
<ul>
    <li><strong>Home:</strong> Your personalized dashboard and feed</li>
    <li><strong>Marketplace:</strong> Browse and create listings</li>
    <li><strong>Community:</strong> Groups, members, and events</li>
    <li><strong>Messages:</strong> Your conversations</li>
    <li><strong>Wallet:</strong> Your credits and transaction history</li>
</ul>

<h3>Mobile App Navigation</h3>
<p>On mobile, use the bottom navigation bar for quick access to:</p>
<ul>
    <li>Home feed</li>
    <li>Messages</li>
    <li>Wallet</li>
    <li>Menu (all features)</li>
</ul>

<h3>Search</h3>
<p>Use the search bar to find:</p>
<ul>
    <li>Members by name or skill</li>
    <li>Listings by keyword</li>
    <li>Groups and events</li>
</ul>

<h3>Keyboard Shortcuts (Desktop)</h3>
<table>
    <tr><th>Shortcut</th><th>Action</th></tr>
    <tr><td>/</td><td>Focus search</td></tr>
    <tr><td>G + H</td><td>Go to Home</td></tr>
    <tr><td>G + M</td><td>Go to Messages</td></tr>
    <tr><td>N</td><td>New post</td></tr>
</table>',
1, FLOOR(RAND() * 100) + 25)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- WALLET MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Understanding Your Wallet', 'wallet-guide', 'wallet',
'<h2>Your Community Wallet</h2>
<p>The wallet is where you manage your community credits - the currency that powers exchanges on our platform.</p>

<h3>What Are Credits?</h3>
<p>Credits represent time and value in our community. Typically, 1 credit equals 1 hour of service, though members can set their own rates.</p>

<h3>Wallet Balance</h3>
<p>Your wallet shows:</p>
<ul>
    <li><strong>Available Balance:</strong> Credits you can spend</li>
    <li><strong>Pending:</strong> Credits from incomplete transactions</li>
    <li><strong>Total Earned:</strong> Lifetime credits earned</li>
    <li><strong>Total Spent:</strong> Lifetime credits spent</li>
</ul>

<h3>Earning Credits</h3>
<p>You can earn credits by:</p>
<ul>
    <li>Providing services to other members</li>
    <li>Completing volunteer opportunities</li>
    <li>Participating in community events</li>
    <li>Receiving welcome bonuses (for new members)</li>
</ul>

<h3>Spending Credits</h3>
<p>Use your credits to:</p>
<ul>
    <li>Request services from other members</li>
    <li>Access premium community features</li>
    <li>Support community initiatives</li>
</ul>

<h3>Transaction History</h3>
<p>View all your transactions in the wallet section. Each entry shows the date, amount, other party, and description.</p>',
1, FLOOR(RAND() * 180) + 60)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Making Transactions', 'making-transactions', 'wallet',
'<h2>How to Exchange Credits</h2>
<p>Transactions are how you exchange credits for services within the community.</p>

<h3>Sending Credits</h3>
<ol>
    <li>Navigate to the member''s profile or use "Send Credits" from your wallet</li>
    <li>Enter the amount and a description</li>
    <li>Review and confirm the transaction</li>
    <li>The recipient will be notified</li>
</ol>

<h3>Receiving Credits</h3>
<p>When someone sends you credits:</p>
<ul>
    <li>You''ll receive a notification</li>
    <li>Credits are added to your available balance</li>
    <li>The transaction appears in your history</li>
</ul>

<h3>Transaction Disputes</h3>
<p>If there''s an issue with a transaction:</p>
<ol>
    <li>First, try to resolve it directly with the other member</li>
    <li>If needed, contact a community administrator</li>
    <li>Provide details about the transaction and issue</li>
</ol>

<h3>Transaction Limits</h3>
<p>Some communities set transaction limits. Check with your community administrator for specific rules.</p>',
1, FLOOR(RAND() * 130) + 35)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- MARKETPLACE MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Marketplace Guide', 'marketplace-guide', 'listings',
'<h2>Using the Marketplace</h2>
<p>The marketplace is where community members post offers and requests for goods and services.</p>

<h3>Types of Listings</h3>
<ul>
    <li><strong>Offers:</strong> Services or items you can provide</li>
    <li><strong>Requests:</strong> Help or items you''re looking for</li>
</ul>

<h3>Creating a Listing</h3>
<ol>
    <li>Click "Create Listing" from the marketplace</li>
    <li>Choose Offer or Request</li>
    <li>Add a clear, descriptive title</li>
    <li>Write a detailed description</li>
    <li>Set your price (in credits or free)</li>
    <li>Add photos to attract interest</li>
    <li>Set your availability or location if relevant</li>
</ol>

<h3>Finding Listings</h3>
<p>Browse listings using:</p>
<ul>
    <li><strong>Categories:</strong> Filter by type of service</li>
    <li><strong>Location:</strong> Find nearby offers</li>
    <li><strong>Search:</strong> Look for specific keywords</li>
    <li><strong>Sort:</strong> By newest, price, or distance</li>
</ul>

<h3>Responding to Listings</h3>
<p>Found something interesting? Click "Message" to start a conversation with the poster and arrange the exchange.</p>',
1, FLOOR(RAND() * 200) + 70)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Writing Great Listings', 'writing-great-listings', 'listings',
'<h2>Tips for Effective Listings</h2>
<p>A well-written listing gets more responses. Here''s how to make yours stand out.</p>

<h3>Title Tips</h3>
<ul>
    <li>Be specific: "Guitar Lessons for Beginners" beats "Music Lessons"</li>
    <li>Include key details: location, skill level, time commitment</li>
    <li>Keep it concise but descriptive</li>
</ul>

<h3>Description Best Practices</h3>
<ul>
    <li><strong>Start with what you offer:</strong> Get to the point quickly</li>
    <li><strong>Include details:</strong> Experience, qualifications, tools needed</li>
    <li><strong>Set expectations:</strong> How long, where, what''s included</li>
    <li><strong>End with a call to action:</strong> "Message me to schedule!"</li>
</ul>

<h3>Photos Matter</h3>
<p>Listings with photos get 3x more responses:</p>
<ul>
    <li>Show examples of your work</li>
    <li>Use good lighting</li>
    <li>Include multiple angles for items</li>
</ul>

<h3>Pricing Strategies</h3>
<ul>
    <li>Research what similar services cost</li>
    <li>Consider offering introductory rates</li>
    <li>Be clear about what''s included</li>
</ul>',
1, FLOOR(RAND() * 110) + 30)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- GROUPS MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Joining and Creating Groups', 'groups-guide', 'groups',
'<h2>Community Hubs & Groups</h2>
<p>Groups (also called Community Hubs) are spaces for members with shared interests to connect, share, and collaborate.</p>

<h3>Finding Groups</h3>
<p>Browse the Groups directory to discover communities that match your interests:</p>
<ul>
    <li>Search by name or topic</li>
    <li>Filter by category (hobbies, skills, neighborhoods)</li>
    <li>View featured and popular groups</li>
</ul>

<h3>Joining a Group</h3>
<ol>
    <li>Visit the group''s page</li>
    <li>Click "Join Group"</li>
    <li>Some groups require approval from an admin</li>
    <li>Once approved, you can participate in discussions</li>
</ol>

<h3>Creating a Group</h3>
<p>Don''t see a group for your interest? Create one!</p>
<ol>
    <li>Click "Create Group"</li>
    <li>Choose a descriptive name</li>
    <li>Write a compelling description</li>
    <li>Set privacy (public, private, or hidden)</li>
    <li>Add a cover image</li>
    <li>Invite members to join</li>
</ol>

<h3>Group Privacy Settings</h3>
<ul>
    <li><strong>Public:</strong> Anyone can see and join</li>
    <li><strong>Private:</strong> Visible, but requires approval to join</li>
    <li><strong>Hidden:</strong> Invite-only, not listed in directory</li>
</ul>',
1, FLOOR(RAND() * 160) + 45)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- EVENTS MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Events Guide', 'events-guide', 'events',
'<h2>Community Events</h2>
<p>Events bring the community together. Find local gatherings, workshops, and activities.</p>

<h3>Finding Events</h3>
<p>Discover events through:</p>
<ul>
    <li><strong>Calendar View:</strong> See upcoming events by date</li>
    <li><strong>List View:</strong> Browse events with details</li>
    <li><strong>Map View:</strong> Find events near you</li>
    <li><strong>Categories:</strong> Filter by type (workshop, social, volunteer)</li>
</ul>

<h3>RSVPing to Events</h3>
<ol>
    <li>Find an event you''re interested in</li>
    <li>Click "RSVP" or "I''m Going"</li>
    <li>Add it to your personal calendar</li>
    <li>You''ll receive reminders before the event</li>
</ol>

<h3>Creating Events</h3>
<p>Host your own community event:</p>
<ol>
    <li>Click "Create Event"</li>
    <li>Add title, date, time, and location</li>
    <li>Write a description of what to expect</li>
    <li>Set capacity limits if needed</li>
    <li>Choose whether it''s public or group-only</li>
    <li>Add a cover image</li>
</ol>

<h3>Managing Your Event</h3>
<p>As an organizer, you can:</p>
<ul>
    <li>View and manage RSVPs</li>
    <li>Send updates to attendees</li>
    <li>Edit event details</li>
    <li>Cancel if necessary (attendees will be notified)</li>
</ul>',
1, FLOOR(RAND() * 140) + 40)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- VOLUNTEERING MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Volunteering Guide', 'volunteering-guide', 'volunteering',
'<h2>Volunteer Opportunities</h2>
<p>Make a difference in your community through volunteer opportunities posted by local organizations and groups.</p>

<h3>Finding Opportunities</h3>
<p>Browse volunteer opportunities by:</p>
<ul>
    <li><strong>Cause:</strong> Environment, education, seniors, etc.</li>
    <li><strong>Commitment:</strong> One-time, recurring, flexible</li>
    <li><strong>Location:</strong> In-person or remote</li>
    <li><strong>Skills needed:</strong> Match your abilities</li>
</ul>

<h3>Applying to Volunteer</h3>
<ol>
    <li>Find an opportunity that interests you</li>
    <li>Click "Apply" or "Sign Up"</li>
    <li>Complete any required information</li>
    <li>Wait for confirmation from the organizer</li>
    <li>Show up and make a difference!</li>
</ol>

<h3>Tracking Your Impact</h3>
<p>Your volunteer hours are tracked on your profile:</p>
<ul>
    <li>Total hours contributed</li>
    <li>Organizations supported</li>
    <li>Impact badges earned</li>
    <li>SDG contributions (if applicable)</li>
</ul>

<h3>For Organizations</h3>
<p>If you represent an organization, you can post volunteer opportunities, manage applications, and track volunteer contributions.</p>',
1, FLOOR(RAND() * 120) + 35)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- GAMIFICATION MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Badges, XP, and Leaderboards', 'gamification-guide', 'gamification',
'<h2>Level Up Your Engagement</h2>
<p>Earn recognition for your contributions to the community through our gamification system.</p>

<h3>Earning XP (Experience Points)</h3>
<p>You earn XP for almost every active participation:</p>
<ul>
    <li>Posting in the feed or groups</li>
    <li>Completing transactions</li>
    <li>RSVPing to events</li>
    <li>Volunteering</li>
    <li>Helping other members</li>
    <li>Daily logins</li>
</ul>

<h3>Badges</h3>
<p>Unlock badges for specific milestones and achievements:</p>
<ul>
    <li><strong>First Exchange:</strong> Complete your first transaction</li>
    <li><strong>Community Pillar:</strong> Help 10+ members</li>
    <li><strong>Event Organizer:</strong> Host community events</li>
    <li><strong>Top Contributor:</strong> Reach the monthly leaderboard</li>
</ul>
<p>Badges display proudly on your profile!</p>

<h3>Leaderboards</h3>
<p>Check the leaderboards to see top contributors:</p>
<ul>
    <li>Monthly rankings</li>
    <li>All-time achievements</li>
    <li>Category-specific leaders</li>
</ul>

<h3>Why It Matters</h3>
<p>Gamification isn''t just for fun - it helps build trust, encourages participation, and celebrates the members who make our community thrive.</p>',
1, FLOOR(RAND() * 170) + 55)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- AI ASSISTANT MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Using the AI Assistant', 'using-ai-assistant', 'ai_assistant',
'<h2>Meet Your AI Community Assistant</h2>
<p>Our AI Assistant is designed to help you get the most out of the platform and community.</p>

<h3>What Can It Do?</h3>
<ul>
    <li><strong>Draft Content:</strong> Help write descriptions for listings, events, or posts</li>
    <li><strong>Answer Questions:</strong> Get instant answers about platform features</li>
    <li><strong>Suggest Connections:</strong> Recommendations based on your interests</li>
    <li><strong>Navigate:</strong> Find features and pages quickly</li>
</ul>

<h3>How to Access</h3>
<p>Click the sparkle icon (<i class="fa-solid fa-sparkles"></i>) in the bottom right corner of your screen to open the AI chat.</p>

<h3>Example Prompts</h3>
<ul>
    <li>"Help me write a listing for guitar lessons"</li>
    <li>"What events are happening this week?"</li>
    <li>"How do I earn more credits?"</li>
    <li>"Find members who offer gardening help"</li>
</ul>

<h3>Privacy</h3>
<p>Your conversations with the AI are private. The assistant uses your profile information to provide personalized help but doesn''t share this with other members.</p>

<h3>Limitations</h3>
<p>The AI is a helpful tool but isn''t perfect. For complex issues or official support, contact your community administrator.</p>',
1, FLOOR(RAND() * 130) + 40)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- SUSTAINABILITY / SDGs MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Tracking Impact with UN SDGs', 'tracking-sdgs', 'sustainability',
'<h2>Making Your Impact Visible</h2>
<p>Our platform integrates the 17 UN Sustainable Development Goals (SDGs) to help measure the social and environmental impact of your community.</p>

<h3>What Are SDGs?</h3>
<p>The Sustainable Development Goals are a universal call to action to end poverty, protect the planet, and ensure prosperity for all by 2030.</p>

<h3>How to Tag Content</h3>
<p>When creating a Listing, Event, or Volunteer Opportunity, you''ll see an "Impact Goal" option. Select the primary goal your activity contributes to:</p>
<ul>
    <li>No Poverty</li>
    <li>Zero Hunger</li>
    <li>Good Health and Well-being</li>
    <li>Quality Education</li>
    <li>Climate Action</li>
    <li>And more...</li>
</ul>

<h3>Impact Dashboard</h3>
<p>Your contributions are tracked and displayed:</p>
<ul>
    <li>On your personal profile</li>
    <li>In the community Impact Dashboard</li>
    <li>In periodic impact reports</li>
</ul>

<h3>Why It Matters</h3>
<p>This data helps organizations report on collective impact for grants, public funding, and community awareness. Every action counts!</p>',
1, FLOOR(RAND() * 100) + 30)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- OFFLINE MODE MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Using Offline Mode', 'offline-mode', 'offline',
'<h2>No Signal? No Problem.</h2>
<p>Our platform includes offline capabilities so you can continue using it even without internet access.</p>

<h3>What Works Offline</h3>
<p>When you''re offline, you can still:</p>
<ul>
    <li>Browse previously loaded content</li>
    <li>Write posts and comments</li>
    <li>Draft marketplace listings</li>
    <li>Compose messages</li>
</ul>

<h3>How It Works</h3>
<p>Your actions are saved in a local queue on your device. When your connection returns, everything syncs automatically in the background.</p>

<h3>Offline Indicator</h3>
<p>You''ll see an indicator when you''re offline. Don''t worry - any queued actions will show as "pending" until they sync.</p>

<h3>Tips for Offline Use</h3>
<ul>
    <li>Load content you might need before going offline</li>
    <li>Keep the app open to maintain cached data</li>
    <li>Large actions (like uploading photos) will wait for WiFi</li>
</ul>

<h3>Sync Conflicts</h3>
<p>If someone else made changes while you were offline, you''ll be notified when you reconnect. The system will help you resolve any conflicts.</p>',
1, FLOOR(RAND() * 80) + 20)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- MOBILE APP MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Mobile App Guide', 'mobile-app-guide', 'mobile',
'<h2>Using the Mobile App</h2>
<p>Access your community anywhere with our mobile app, available for iOS and Android.</p>

<h3>Getting the App</h3>
<ul>
    <li><strong>iOS:</strong> Download from the App Store</li>
    <li><strong>Android:</strong> Download from Google Play</li>
    <li><strong>PWA:</strong> Add to home screen from your browser</li>
</ul>

<h3>Mobile Features</h3>
<p>The mobile app includes:</p>
<ul>
    <li>Push notifications for messages and updates</li>
    <li>Quick access to your wallet</li>
    <li>Camera integration for photos</li>
    <li>Location-based features</li>
    <li>Biometric login (fingerprint/Face ID)</li>
</ul>

<h3>Navigation</h3>
<p>Use the bottom navigation bar for quick access:</p>
<ul>
    <li><strong>Home:</strong> Your feed and dashboard</li>
    <li><strong>Messages:</strong> Conversations</li>
    <li><strong>Wallet:</strong> Credits and transactions</li>
    <li><strong>Menu:</strong> All features and settings</li>
</ul>

<h3>Notifications</h3>
<p>Enable push notifications to stay informed about:</p>
<ul>
    <li>New messages</li>
    <li>Transaction confirmations</li>
    <li>Event reminders</li>
    <li>Community updates</li>
</ul>',
1, FLOOR(RAND() * 140) + 45)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- SECURITY MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Privacy and Security Guide', 'privacy-security', 'security',
'<h2>Keeping Your Account Secure</h2>
<p>Your security is our priority. Here''s how to protect your account and understand our privacy practices.</p>

<h3>Password Security</h3>
<ul>
    <li>Use a strong, unique password</li>
    <li>Don''t share your password with anyone</li>
    <li>Change your password if you suspect compromise</li>
    <li>Enable two-factor authentication if available</li>
</ul>

<h3>Biometric Login</h3>
<p>Enable fingerprint or Face ID for secure, convenient access:</p>
<ol>
    <li>Go to Settings > Security</li>
    <li>Enable Biometric Login</li>
    <li>Verify with your fingerprint or face</li>
</ol>

<h3>Privacy Settings</h3>
<p>Control who sees your information:</p>
<ul>
    <li><strong>Profile visibility:</strong> Public, members only, or connections only</li>
    <li><strong>Location:</strong> Show general area or hide completely</li>
    <li><strong>Activity:</strong> Control what appears in your feed</li>
</ul>

<h3>Reporting Issues</h3>
<p>If you encounter suspicious activity:</p>
<ol>
    <li>Use the "Report" button on any content</li>
    <li>Contact your community administrator</li>
    <li>Don''t engage with suspicious accounts</li>
</ol>

<h3>Data Protection</h3>
<p>Your data is encrypted and stored securely. We never sell your information. See our Privacy Policy for details.</p>',
1, FLOOR(RAND() * 110) + 35)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- REVIEWS MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Reviews and Ratings Guide', 'reviews-guide', 'reviews',
'<h2>Building Trust Through Reviews</h2>
<p>Reviews help build trust in the community. Leave honest feedback to help others make informed decisions.</p>

<h3>When to Leave a Review</h3>
<p>You can review members after:</p>
<ul>
    <li>Completing a transaction</li>
    <li>Attending their event</li>
    <li>Working together on a project</li>
    <li>Receiving a service</li>
</ul>

<h3>Writing a Good Review</h3>
<ul>
    <li><strong>Be specific:</strong> Mention what went well</li>
    <li><strong>Be honest:</strong> Include constructive feedback</li>
    <li><strong>Be respectful:</strong> Focus on the experience, not the person</li>
    <li><strong>Be timely:</strong> Review while details are fresh</li>
</ul>

<h3>Rating Scale</h3>
<table>
    <tr><th>Stars</th><th>Meaning</th></tr>
    <tr><td>5 Stars</td><td>Excellent - Exceeded expectations</td></tr>
    <tr><td>4 Stars</td><td>Great - Would recommend</td></tr>
    <tr><td>3 Stars</td><td>Good - Met expectations</td></tr>
    <tr><td>2 Stars</td><td>Fair - Some issues</td></tr>
    <tr><td>1 Star</td><td>Poor - Significant problems</td></tr>
</table>

<h3>Responding to Reviews</h3>
<p>You can respond to reviews on your profile. Thank positive reviewers and professionally address any concerns in negative reviews.</p>',
1, FLOOR(RAND() * 90) + 25)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- GOVERNANCE MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Community Governance Guide', 'community-governance', 'governance',
'<h2>Democracy in Your Community</h2>
<p>Participate in community decisions through our governance system.</p>

<h3>Voting on Proposals</h3>
<ol>
    <li>Navigate to the Governance section</li>
    <li>View open proposals</li>
    <li>Read the proposal details</li>
    <li>Cast your vote: Yes, No, or Abstain</li>
    <li>See results after voting closes</li>
</ol>

<h3>Creating Proposals</h3>
<p>If you have permission, you can submit proposals:</p>
<ol>
    <li>Click "Create Proposal"</li>
    <li>Write a clear title and description</li>
    <li>Explain the impact and reasoning</li>
    <li>Set a voting deadline</li>
    <li>Submit for community review</li>
</ol>

<h3>Types of Proposals</h3>
<ul>
    <li><strong>Policy Changes:</strong> Community rules and guidelines</li>
    <li><strong>Budget Allocation:</strong> How community funds are used</li>
    <li><strong>New Features:</strong> Platform improvements</li>
    <li><strong>Community Projects:</strong> Collective initiatives</li>
</ul>

<h3>Voting Requirements</h3>
<p>Requirements vary by community. Some may require:</p>
<ul>
    <li>Minimum membership duration</li>
    <li>Verified account status</li>
    <li>Participation history</li>
</ul>',
1, FLOOR(RAND() * 85) + 20)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- GOALS MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Goals and Accountability Buddies', 'goals-and-buddies', 'goals',
'<h2>Achieve More Together</h2>
<p>Set personal goals and find support within your community to stay accountable.</p>

<h3>Creating a Goal</h3>
<ol>
    <li>Go to your profile or the Goals section</li>
    <li>Click "New Goal"</li>
    <li>Define what you want to achieve</li>
    <li>Set a target date</li>
    <li>Choose visibility: Public or Private</li>
    <li>Add milestones to track progress</li>
</ol>

<h3>Public vs Private Goals</h3>
<ul>
    <li><strong>Public:</strong> Visible to the community for support and accountability</li>
    <li><strong>Private:</strong> Only you can see it</li>
</ul>

<h3>Accountability Buddies</h3>
<p>For public goals, you can invite a friend to be your "Buddy":</p>
<ul>
    <li>They can check in on your progress</li>
    <li>Offer encouragement and support</li>
    <li>Get notified of your updates</li>
</ul>

<h3>Tracking Progress</h3>
<p>Update your goal regularly:</p>
<ul>
    <li>Mark milestones as complete</li>
    <li>Add progress notes</li>
    <li>Share achievements</li>
</ul>

<h3>Tips for Success</h3>
<ul>
    <li>Make goals specific and measurable</li>
    <li>Break big goals into smaller milestones</li>
    <li>Check in weekly</li>
    <li>Celebrate progress, not just completion</li>
</ul>',
1, FLOOR(RAND() * 95) + 30)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- --------------------------------
-- POLLS MODULE
-- --------------------------------

INSERT INTO help_articles (title, slug, module_tag, content, is_public, view_count) VALUES
('Polls and Voting Guide', 'polls-guide', 'polls',
'<h2>Community Polls</h2>
<p>Polls are a quick way to gather community opinions and make decisions together.</p>

<h3>Voting in Polls</h3>
<ol>
    <li>Find a poll in your feed or the Polls section</li>
    <li>Read the question and options</li>
    <li>Click your choice</li>
    <li>See results (if visible)</li>
</ol>

<h3>Creating Polls</h3>
<ol>
    <li>Click "Create Poll"</li>
    <li>Write a clear question</li>
    <li>Add 2-6 answer options</li>
    <li>Set when voting ends</li>
    <li>Choose if results are visible during voting</li>
    <li>Decide if votes are anonymous</li>
</ol>

<h3>Poll Settings</h3>
<ul>
    <li><strong>Single choice:</strong> Pick one option</li>
    <li><strong>Multiple choice:</strong> Select several options</li>
    <li><strong>Anonymous:</strong> Votes aren''t tied to names</li>
    <li><strong>Results visibility:</strong> Show during or after voting</li>
</ul>

<h3>Best Practices</h3>
<ul>
    <li>Keep questions clear and neutral</li>
    <li>Provide distinct, non-overlapping options</li>
    <li>Include an "Other" option if appropriate</li>
    <li>Set reasonable voting deadlines</li>
</ul>',
1, FLOOR(RAND() * 75) + 20)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW();

-- ============================================================
-- 4. VERIFICATION
-- ============================================================

SELECT 'Help Center setup complete!' AS status;
SELECT COUNT(*) AS total_articles FROM help_articles;
SELECT module_tag, COUNT(*) AS article_count FROM help_articles GROUP BY module_tag ORDER BY article_count DESC;
