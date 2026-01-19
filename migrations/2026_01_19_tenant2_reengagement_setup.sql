-- ============================================================================
-- Re-engagement Setup for Tenant 2
-- ============================================================================
-- Run this to set up everything for your re-engagement campaign
-- ============================================================================

-- 1. Add the "Never Logged In" segment for tenant 2
INSERT INTO newsletter_segments (tenant_id, name, description, rules, is_active, created_at)
SELECT
    2,
    'Never Logged In',
    'Members who have never logged in to the app - perfect for re-engagement campaigns',
    '{"match":"all","conditions":[{"field":"login_recency","operator":"equals","value":"never"}]}',
    1,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM newsletter_segments
    WHERE tenant_id = 2 AND name = 'Never Logged In'
);

-- 2. Add the email template for tenant 2
INSERT INTO newsletter_templates (
    tenant_id, name, description, category, subject, preview_text, content, is_active, created_at
)
SELECT
    2,
    'Discover the New App',
    'Re-engagement email for members who have never logged in. Use with the "Never Logged In" segment.',
    'custom',
    'Have you seen what''s new? Your community has a brand new home',
    'We''ve built something special for you - come take a look!',
    '<!-- Re-engagement Email: Discover the New App -->
<div style="padding: 40px 40px 20px; text-align: center;">
    <h1 style="margin: 0 0 10px; font-size: 32px; font-weight: 800; color: #1f2937; line-height: 1.2;">
        Have You Seen<br>What''s New?
    </h1>
    <p style="margin: 0; font-size: 18px; color: #6b7280; line-height: 1.6;">
        Hi {{first_name}}, we''ve been busy building something special for you.
    </p>
</div>

<!-- The Big Reveal -->
<div style="padding: 20px 40px 30px;">
    <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 16px; padding: 30px; text-align: center; color: white;">
        <p style="margin: 0 0 15px; font-size: 16px; opacity: 0.9;">Your timebank now has</p>
        <h2 style="margin: 0 0 15px; font-size: 28px; font-weight: 700;">A Brand New App</h2>
        <p style="margin: 0; font-size: 16px; opacity: 0.9; line-height: 1.6;">
            Everything you loved about your community, <br>now easier and more rewarding than ever.
        </p>
    </div>
</div>

<!-- What''s Different -->
<div style="padding: 0 40px 30px;">
    <h3 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: #1f2937; text-align: center;">
        What''s Different?
    </h3>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 15px; vertical-align: top; width: 60px;">
                <div style="width: 50px; height: 50px; background: #f0fdf4; border-radius: 12px; text-align: center; line-height: 50px; font-size: 24px;">&#127942;</div>
            </td>
            <td style="padding: 15px; vertical-align: top;">
                <h4 style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: #1f2937;">Earn Badges & Level Up</h4>
                <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.5;">Get recognized for your contributions with achievements.</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 15px; vertical-align: top; width: 60px;">
                <div style="width: 50px; height: 50px; background: #eff6ff; border-radius: 12px; text-align: center; line-height: 50px; font-size: 24px;">&#128241;</div>
            </td>
            <td style="padding: 15px; vertical-align: top;">
                <h4 style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: #1f2937;">Works on Your Phone</h4>
                <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.5;">Browse offers and connect with members from anywhere.</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 15px; vertical-align: top; width: 60px;">
                <div style="width: 50px; height: 50px; background: #fef3c7; border-radius: 12px; text-align: center; line-height: 50px; font-size: 24px;">&#128101;</div>
            </td>
            <td style="padding: 15px; vertical-align: top;">
                <h4 style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: #1f2937;">Join Groups & Events</h4>
                <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.5;">Connect with members who share your interests.</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 15px; vertical-align: top; width: 60px;">
                <div style="width: 50px; height: 50px; background: #fce7f3; border-radius: 12px; text-align: center; line-height: 50px; font-size: 24px;">&#9889;</div>
            </td>
            <td style="padding: 15px; vertical-align: top;">
                <h4 style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: #1f2937;">Instant Notifications</h4>
                <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.5;">Never miss a message or opportunity.</p>
            </td>
        </tr>
    </table>
</div>

<!-- Community Stats -->
<div style="padding: 0 40px 30px;">
    <div style="background: #f9fafb; border-radius: 12px; padding: 25px; text-align: center;">
        <p style="margin: 0 0 15px; font-size: 14px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">
            Your Community Right Now
        </p>
        [[community_stats]]
    </div>
</div>

<!-- Recent Listings -->
<div style="padding: 0 40px 30px;">
    <h3 style="margin: 0 0 15px; font-size: 18px; font-weight: 700; color: #1f2937;">
        See What Members Are Offering
    </h3>
    [[recent_listings:3]]
</div>

<!-- Big CTA -->
<div style="padding: 0 40px 40px; text-align: center;">
    <p style="margin: 0 0 20px; font-size: 16px; color: #6b7280; line-height: 1.6;">
        Your account is ready and waiting.<br>Just click below to explore.
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
        <tr>
            <td style="border-radius: 12px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <a href="{{app_url}}/login" style="display: inline-block; padding: 18px 40px; font-size: 18px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 12px;">
                    Take Me There &rarr;
                </a>
            </td>
        </tr>
    </table>
    <p style="margin: 20px 0 0; font-size: 13px; color: #9ca3af;">
        Same email, same account - just a whole new experience.
    </p>
</div>

<!-- Personal Touch -->
<div style="padding: 0 40px 30px;">
    <div style="border-left: 4px solid #6366f1; padding-left: 20px;">
        <p style="margin: 0; font-size: 15px; color: #4b5563; line-height: 1.7; font-style: italic;">
            "We built this because we believe every hour of help deserves recognition. We hope you''ll love it."
        </p>
        <p style="margin: 10px 0 0; font-size: 14px; color: #6b7280;">
            - The Team
        </p>
    </div>
</div>',
    1,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM newsletter_templates
    WHERE tenant_id = 2 AND name = 'Discover the New App'
);

-- 3. Show results
SELECT '--- SETUP COMPLETE ---' as status;

-- Show segment
SELECT id, name, description FROM newsletter_segments
WHERE tenant_id = 2 AND name = 'Never Logged In';

-- Count never-logged-in users for tenant 2
SELECT
    COUNT(*) as never_logged_in_count,
    'members have never logged in' as description
FROM users
WHERE tenant_id = 2
AND is_approved = 1
AND last_login_at IS NULL;

-- Show template
SELECT id, name, subject FROM newsletter_templates
WHERE name = 'Discover the New App';
