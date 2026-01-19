-- Update the re-engagement template with a fully polished, email-client-compatible design
UPDATE newsletter_templates
SET content = '<!-- Re-engagement Email: Discover the New App - Polished Version -->

<!-- Logo Header -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 30px 40px 20px; text-align: center;">
            <img src="https://hour-timebank.ie/assets/img/logos/nexus_icon.webp" alt="Hour Timebank" width="60" height="60" style="display: inline-block; border-radius: 12px;">
        </td>
    </tr>
</table>

<!-- Hero Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 10px 40px 30px; text-align: center;">
            <h1 style="margin: 0 0 16px; font-size: 32px; font-weight: 800; color: #111827; line-height: 1.3; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                Hey {{first_name}}, have you<br>seen what''s new?
            </h1>
            <p style="margin: 0; font-size: 18px; color: #6b7280; line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                We''ve completely rebuilt your timebank from the ground up.
            </p>
        </td>
    </tr>
</table>

<!-- Main Announcement Card -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 30px;">
    <tr>
        <td style="padding: 0 10px 30px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #6366f1; border-radius: 16px;">
                <tr>
                    <td style="padding: 35px 30px; text-align: center;">
                        <p style="margin: 0 0 8px; font-size: 14px; color: rgba(255,255,255,0.85); text-transform: uppercase; letter-spacing: 1.5px; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            Introducing
                        </p>
                        <h2 style="margin: 0 0 12px; font-size: 28px; font-weight: 700; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            A Brand New App
                        </h2>
                        <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9); line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            Same community, now easier<br>and more rewarding than ever.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Features Section Header -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 10px 40px 24px; text-align: center;">
            <p style="margin: 0; font-size: 13px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 1.5px; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                What''s New
            </p>
        </td>
    </tr>
</table>

<!-- Feature 1: Badges -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 30px;">
    <tr>
        <td style="padding: 0 10px 12px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f0fdf4; border-radius: 12px;">
                <tr>
                    <td width="60" style="padding: 18px 0 18px 18px; vertical-align: middle;">
                        <div style="width: 44px; height: 44px; background: #dcfce7; border-radius: 10px; text-align: center; line-height: 44px; font-size: 22px;">🏆</div>
                    </td>
                    <td style="padding: 18px 18px 18px 14px; vertical-align: middle;">
                        <p style="margin: 0 0 2px; font-size: 15px; font-weight: 600; color: #166534; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Earn Badges & Level Up</p>
                        <p style="margin: 0; font-size: 13px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Get recognized for helping others</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Feature 2: Mobile -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 30px;">
    <tr>
        <td style="padding: 0 10px 12px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #eff6ff; border-radius: 12px;">
                <tr>
                    <td width="60" style="padding: 18px 0 18px 18px; vertical-align: middle;">
                        <div style="width: 44px; height: 44px; background: #dbeafe; border-radius: 10px; text-align: center; line-height: 44px; font-size: 22px;">📱</div>
                    </td>
                    <td style="padding: 18px 18px 18px 14px; vertical-align: middle;">
                        <p style="margin: 0 0 2px; font-size: 15px; font-weight: 600; color: #1e40af; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Works on Your Phone</p>
                        <p style="margin: 0; font-size: 13px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Browse and connect from anywhere</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Feature 3: Groups -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 30px;">
    <tr>
        <td style="padding: 0 10px 12px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fef3c7; border-radius: 12px;">
                <tr>
                    <td width="60" style="padding: 18px 0 18px 18px; vertical-align: middle;">
                        <div style="width: 44px; height: 44px; background: #fde68a; border-radius: 10px; text-align: center; line-height: 44px; font-size: 22px;">👥</div>
                    </td>
                    <td style="padding: 18px 18px 18px 14px; vertical-align: middle;">
                        <p style="margin: 0 0 2px; font-size: 15px; font-weight: 600; color: #92400e; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Groups & Events</p>
                        <p style="margin: 0; font-size: 13px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Connect with like-minded members</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Feature 4: Notifications -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 30px;">
    <tr>
        <td style="padding: 0 10px 30px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fce7f3; border-radius: 12px;">
                <tr>
                    <td width="60" style="padding: 18px 0 18px 18px; vertical-align: middle;">
                        <div style="width: 44px; height: 44px; background: #fbcfe8; border-radius: 10px; text-align: center; line-height: 44px; font-size: 22px;">⚡</div>
                    </td>
                    <td style="padding: 18px 18px 18px 14px; vertical-align: middle;">
                        <p style="margin: 0 0 2px; font-size: 15px; font-weight: 600; color: #9d174d; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Instant Notifications</p>
                        <p style="margin: 0; font-size: 13px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Never miss an opportunity</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Community Stats -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 30px;">
    <tr>
        <td style="padding: 0 10px 30px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                <tr>
                    <td style="padding: 24px; text-align: center;">
                        <p style="margin: 0 0 16px; font-size: 12px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 1.5px; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            Your Community Right Now
                        </p>
                        [[community_stats]]
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- CTA Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 10px 40px 20px; text-align: center;">
            <p style="margin: 0 0 24px; font-size: 16px; color: #6b7280; line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                Your account is ready and waiting.<br>Same email, same password.
            </p>

            <!-- Button -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                <tr>
                    <td style="border-radius: 12px; background: #6366f1;">
                        <a href="{{app_url}}/login" target="_blank" style="display: inline-block; padding: 16px 40px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            Take Me There →
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin: 20px 0 0; font-size: 13px; color: #9ca3af; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                No new account needed — just log in!
            </p>
        </td>
    </tr>
</table>

<!-- Divider -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 20px 40px;">
            <div style="border-top: 1px solid #e5e7eb;"></div>
        </td>
    </tr>
</table>

<!-- Personal Note -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 0 40px 30px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="4" style="background: #6366f1; border-radius: 2px;"></td>
                    <td style="padding-left: 16px;">
                        <p style="margin: 0 0 8px; font-size: 15px; color: #4b5563; line-height: 1.7; font-style: italic; font-family: Georgia, ''Times New Roman'', serif;">
                            "We built this because we believe every hour of help deserves recognition."
                        </p>
                        <p style="margin: 0; font-size: 14px; color: #6b7280; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            — The Hour Timebank Team
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>',
subject = '{{first_name}}, have you seen what''s new?',
preview_text = 'We''ve completely rebuilt your timebank — come take a look! ✨'
WHERE tenant_id = 2 AND name = 'Discover the New App';

-- Confirm update
SELECT 'Template updated with polished design!' as status;
