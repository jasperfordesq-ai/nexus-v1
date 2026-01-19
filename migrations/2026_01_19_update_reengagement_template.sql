-- Update the re-engagement template with a cleaner, modern light theme design
UPDATE newsletter_templates
SET content = '<!-- Re-engagement Email: Discover the New App - Modern Light Theme -->

<!-- Hero Section -->
<div style="text-align: center; padding: 40px 30px 30px;">
    <div style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 12px 24px; border-radius: 50px; margin-bottom: 24px;">
        <span style="color: white; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">✨ SOMETHING NEW</span>
    </div>
    <h1 style="margin: 0 0 16px; font-size: 36px; font-weight: 800; color: #111827; line-height: 1.2;">
        Your Timebank Has<br>A New Home
    </h1>
    <p style="margin: 0; font-size: 18px; color: #6b7280; line-height: 1.6; max-width: 400px; margin: 0 auto;">
        Hi {{first_name}}, we''ve rebuilt everything from the ground up. Come see what''s new!
    </p>
</div>

<!-- Main Card -->
<div style="padding: 0 30px 30px;">
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 32px; text-align: center;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 20px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
            <span style="font-size: 40px; line-height: 80px;">🚀</span>
        </div>
        <h2 style="margin: 0 0 12px; font-size: 24px; font-weight: 700; color: #111827;">A Brand New Experience</h2>
        <p style="margin: 0; font-size: 16px; color: #6b7280; line-height: 1.6;">
            Same community you love, now easier<br>and more rewarding than ever.
        </p>
    </div>
</div>

<!-- Features Grid -->
<div style="padding: 0 30px 30px;">
    <h3 style="margin: 0 0 24px; font-size: 14px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; text-align: center;">
        What''s New
    </h3>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse: separate; border-spacing: 0 12px;">
        <tr>
            <td style="background: #f0fdf4; border-radius: 16px; padding: 20px; vertical-align: top;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50" style="vertical-align: top;">
                            <span style="font-size: 28px;">🏆</span>
                        </td>
                        <td style="vertical-align: top;">
                            <h4 style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #166534;">Earn Badges</h4>
                            <p style="margin: 0; font-size: 14px; color: #4b5563;">Get recognized for helping others</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background: #eff6ff; border-radius: 16px; padding: 20px; vertical-align: top;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50" style="vertical-align: top;">
                            <span style="font-size: 28px;">📱</span>
                        </td>
                        <td style="vertical-align: top;">
                            <h4 style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #1e40af;">Mobile Ready</h4>
                            <p style="margin: 0; font-size: 14px; color: #4b5563;">Works beautifully on your phone</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background: #fef3c7; border-radius: 16px; padding: 20px; vertical-align: top;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50" style="vertical-align: top;">
                            <span style="font-size: 28px;">👥</span>
                        </td>
                        <td style="vertical-align: top;">
                            <h4 style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #92400e;">Groups & Events</h4>
                            <p style="margin: 0; font-size: 14px; color: #4b5563;">Connect with like-minded members</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background: #fce7f3; border-radius: 16px; padding: 20px; vertical-align: top;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50" style="vertical-align: top;">
                            <span style="font-size: 28px;">⚡</span>
                        </td>
                        <td style="vertical-align: top;">
                            <h4 style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #9d174d;">Instant Updates</h4>
                            <p style="margin: 0; font-size: 14px; color: #4b5563;">Never miss an opportunity</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<!-- Community Stats -->
<div style="padding: 0 30px 30px;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 20px; padding: 28px; text-align: center; color: white;">
        <p style="margin: 0 0 8px; font-size: 13px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;">
            Your Community Right Now
        </p>
        [[community_stats]]
    </div>
</div>

<!-- CTA Section -->
<div style="padding: 0 30px 40px; text-align: center;">
    <p style="margin: 0 0 24px; font-size: 16px; color: #6b7280; line-height: 1.6;">
        Your account is ready. Same email, same password.<br>Just click below to explore!
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
        <tr>
            <td style="border-radius: 14px; background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);">
                <a href="{{app_url}}/login" style="display: inline-block; padding: 18px 48px; font-size: 18px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 14px;">
                    Take Me There →
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 20px 0 0; font-size: 13px; color: #9ca3af;">
        No new account needed - just log in!
    </p>
</div>

<!-- Footer Quote -->
<div style="padding: 0 30px 30px;">
    <div style="border-top: 1px solid #e5e7eb; padding-top: 24px; text-align: center;">
        <p style="margin: 0 0 8px; font-size: 15px; color: #6b7280; font-style: italic; line-height: 1.6;">
            "We built this because we believe every hour<br>of help deserves recognition."
        </p>
        <p style="margin: 0; font-size: 14px; color: #9ca3af;">
            — The Hour Timebank Team
        </p>
    </div>
</div>',
subject = '{{first_name}}, have you seen your community''s new home?',
preview_text = 'We''ve rebuilt everything - come take a look! ✨'
WHERE tenant_id = 2 AND name = 'Discover the New App';

-- Confirm update
SELECT 'Template updated successfully!' as status;
SELECT id, name, subject, LEFT(content, 80) as preview FROM newsletter_templates WHERE tenant_id = 2 AND name = 'Discover the New App';
