-- Content Injection for Help Center Modernization
-- Generated on: 2026-01-10
-- Purpose: Sync Help Center with Executive Platform Showcase (Jan 2026 Specs)
-- Uses INSERT ... ON DUPLICATE KEY UPDATE to allow re-running safely

-- 1. AI Assistant
INSERT INTO `help_articles` (
        `title`,
        `slug`,
        `module_tag`,
        `content`,
        `is_public`,
        `created_at`
    )
VALUES (
        'How to use the AI Community Assistant',
        'using-ai-assistant',
        'ai_assistant',
        '<h2>Meet Your AI Assistant</h2><p>The Nexus AI Assistant is designed to help you manage your community more effectively. It understands the context of your platform, including groups, events, and listings.</p><h3>What can it do?</h3><ul><li><strong>Draft Content:</strong> Ask it to write a description for your new event or marketplace listing.</li><li><strong>Answer Questions:</strong> Get instant answers about platform features or community guidelines.</li><li><strong>Suggest Connections:</strong> Ask for recommendations on who to connect with based on your interests.</li></ul><p>To access the assistant, click the sparkle icon (<i class="fas fa-sparkles"></i>) in the bottom right corner of your screen.</p>',
        1,
        NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    updated_at = NOW();
-- 2. Sustainability (SDGs)
INSERT INTO `help_articles` (
        `title`,
        `slug`,
        `module_tag`,
        `content`,
        `is_public`,
        `created_at`
    )
VALUES (
        'Tracking Impact with UN Sustainable Development Goals',
        'tracking-sdgs',
        'sustainability',
        '<h2>Making Your Impact Visible</h2><p>Our platform natively integrates the 17 UN Sustainable Development Goals (SDGs) to help measure the social and environmental impact of your community.</p><h3>How to Tag Content</h3><p>When creating a Listing, Event, or Volunteer Opportunity, you will see an "Impact Goal" dropdown. Select the primary goal your activity contributes to (e.g., "Zero Hunger" or "Climate Action").</p><h3>Impact Reporting</h3><p>Your contributions are tracked on your profile and the community Impact Dashboard. This data helps organizations report on their collective impact for grants and public funding.</p>',
        1,
        NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    updated_at = NOW();
-- 3. Offline Performance (Rural Resilience)
INSERT INTO `help_articles` (
        `title`,
        `slug`,
        `module_tag`,
        `content`,
        `is_public`,
        `created_at`
    )
VALUES (
        'Using the App Without Internet (Offline Mode)',
        'offline-mode',
        'offline',
        '<h2>No Signal? No Problem.</h2><p>The platform is equipped with "Rural Resilience" technology, allowing it to work even when you lose your internet connection.</p><h3>Queue-it-Forward™</h3><p>If you are offline, you can still:</p><ul><li>Write posts and comments</li><li>Create marketplace listings</li><li>Send messages</li></ul><p>Your actions are saved in a secure "Queue" on your device. As soon as your phone detects a signal again, the app will automatically sync your actions to the server in the background.</p>',
        1,
        NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    updated_at = NOW();
-- 4. Governance
INSERT INTO `help_articles` (
        `title`,
        `slug`,
        `module_tag`,
        `content`,
        `is_public`,
        `created_at`
    )
VALUES (
        'Participating in Community Governance',
        'community-governance',
        'governance',
        '<h2>Democracy in Action</h2><p>Official community members can participate in governance through the Proposals system.</p><h3>Voting on Proposals</h3><p>Navigate to the <strong>Governance</strong> tab in your community hub. You will see open proposals. Click to view details and cast your vote (Yes/No/Abstain).</p><h3>Creating a Proposal</h3><p>If you have permission, you can draft a new proposal. Describe the motion clearly and set a voting deadline. Once published, all eligible members will be notified to vote.</p>',
        1,
        NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    updated_at = NOW();
-- 5. Gamification
INSERT INTO `help_articles` (
        `title`,
        `slug`,
        `module_tag`,
        `content`,
        `is_public`,
        `created_at`
    )
VALUES (
        'Badges, XP, and Leaderboards',
        'gamification-guide',
        'gamification',
        '<h2>Level Up Your Engagement</h2><p>Earn recognition for your contributions to the community.</p><h3>Earning XP</h3><p>You earn Experience Points (XP) for almost every active participation:</p><ul><li>Posting in the feed</li><li>Completing a transaction</li><li>RSVPing to an event</li><li>Volunteering</li></ul><h3>Badges</h3><p>Unlock badges for specific milestones, such as "First Exchange" or "Community Pillar". Display them proudly on your profile!</p><h3>Leaderboards</h3><p>Check the Leaderboards to see the top contributors this month. Friendly competition helps drive community vitality.</p>',
        1,
        NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    updated_at = NOW();
-- 6. Goals
INSERT INTO `help_articles` (
        `title`,
        `slug`,
        `module_tag`,
        `content`,
        `is_public`,
        `created_at`
    )
VALUES (
        'Setting Goals & Finding Accountability Buddies',
        'goals-and-buddies',
        'goals',
        '<h2>Achieve More Together</h2><p>Set personal goals and find support within your community.</p><h3>Creating a Goal</h3><p>Go to your profile and select the <strong>Goals</strong> tab. Click "New Goal" and define what you want to achieve and by when. You can make goals Public (for support) or Private.</p><h3>Accountability Buddies</h3><p>On a public goal, you can invite a friend to be your "Buddy". They can check in on your progress and offer encouragement.</p>',
        1,
        NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    updated_at = NOW();