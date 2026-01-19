-- Update the re-engagement template with FDS Gold Standard polish (Light Mode)
UPDATE newsletter_templates
SET content = '<!-- Re-engagement Email: Gold Standard Light Mode -->

<!-- Header with Logo -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
    <tr>
        <td style="padding: 32px 40px 24px; text-align: center;">
            <img src="https://hour-timebank.ie/assets/img/logos/nexus_icon.webp" alt="Hour Timebank" width="56" height="56" style="display: inline-block; border-radius: 14px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);">
            <p style="margin: 12px 0 0; font-size: 11px; font-weight: 600; color: #6366f1; text-transform: uppercase; letter-spacing: 2px; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                Hour Timebank
            </p>
        </td>
    </tr>
</table>

<!-- Hero Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 16px 40px 32px; text-align: center; background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);">
            <h1 style="margin: 0 0 12px; font-size: 28px; font-weight: 700; color: #0f172a; line-height: 1.3; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                Hey {{first_name}},<br>We''ve got something new!
            </h1>
            <p style="margin: 0; font-size: 16px; color: #64748b; line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                Your timebank has a brand new home — rebuilt from the ground up.
            </p>
        </td>
    </tr>
</table>

<!-- Main Card with Gradient Border Effect -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 24px;">
    <tr>
        <td style="padding: 0 16px 32px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 20px; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.25);">
                <tr>
                    <td style="padding: 40px 32px; text-align: center;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="text-align: center;">
                                    <div style="display: inline-block; width: 64px; height: 64px; background: rgba(255, 255, 255, 0.2); border-radius: 16px; line-height: 64px; font-size: 32px; margin-bottom: 20px;">🚀</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="text-align: center;">
                                    <h2 style="margin: 0 0 8px; font-size: 24px; font-weight: 700; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                                        A Brand New Experience
                                    </h2>
                                    <p style="margin: 0; font-size: 15px; color: rgba(255, 255, 255, 0.9); line-height: 1.5; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                                        Same community you love,<br>now easier and more rewarding.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Features Header -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 8px 40px 20px; text-align: center;">
            <p style="margin: 0; font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                What''s New
            </p>
        </td>
    </tr>
</table>

<!-- Feature Cards - 2x2 Grid Style -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 24px;">
    <tr>
        <td style="padding: 0 16px;">
            <!-- Row 1 -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="50%" style="padding: 0 6px 12px 0; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px;">
                            <tr>
                                <td style="padding: 20px; text-align: center;">
                                    <div style="font-size: 28px; margin-bottom: 8px;">🏆</div>
                                    <p style="margin: 0 0 4px; font-size: 14px; font-weight: 600; color: #166534; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Earn Badges</p>
                                    <p style="margin: 0; font-size: 12px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Recognition for helping</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%" style="padding: 0 0 12px 6px; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 16px;">
                            <tr>
                                <td style="padding: 20px; text-align: center;">
                                    <div style="font-size: 28px; margin-bottom: 8px;">📱</div>
                                    <p style="margin: 0 0 4px; font-size: 14px; font-weight: 600; color: #1e40af; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Mobile Ready</p>
                                    <p style="margin: 0; font-size: 12px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Works on any device</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <!-- Row 2 -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="50%" style="padding: 0 6px 24px 0; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 16px;">
                            <tr>
                                <td style="padding: 20px; text-align: center;">
                                    <div style="font-size: 28px; margin-bottom: 8px;">👥</div>
                                    <p style="margin: 0 0 4px; font-size: 14px; font-weight: 600; color: #92400e; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Groups</p>
                                    <p style="margin: 0; font-size: 12px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Find your community</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%" style="padding: 0 0 24px 6px; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fce7f3; border: 1px solid #fbcfe8; border-radius: 16px;">
                            <tr>
                                <td style="padding: 20px; text-align: center;">
                                    <div style="font-size: 28px; margin-bottom: 8px;">⚡</div>
                                    <p style="margin: 0 0 4px; font-size: 14px; font-weight: 600; color: #9d174d; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Notifications</p>
                                    <p style="margin: 0; font-size: 12px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">Never miss out</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Stats Section - Clean Minimal -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="padding: 0 24px;">
    <tr>
        <td style="padding: 0 16px 32px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px;">
                <tr>
                    <td style="padding: 28px 20px;">
                        <p style="margin: 0 0 20px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            Your Community
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
        <td style="padding: 0 40px 16px; text-align: center;">
            <p style="margin: 0 0 24px; font-size: 15px; color: #64748b; line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                Your account is ready and waiting.<br>
                <span style="color: #0f172a; font-weight: 500;">Same email, same password.</span>
            </p>
        </td>
    </tr>
</table>

<!-- Primary Button -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 0 40px 32px; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                <tr>
                    <td style="border-radius: 14px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);">
                        <a href="https://hour-timebank.ie/" target="_blank" style="display: inline-block; padding: 16px 48px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif; letter-spacing: 0.3px;">
                            Explore Now →
                        </a>
                    </td>
                </tr>
            </table>
            <p style="margin: 16px 0 0; font-size: 12px; color: #94a3b8; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                No new account needed
            </p>
        </td>
    </tr>
</table>

<!-- Divider -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 0 40px;">
            <div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e2e8f0 20%, #e2e8f0 80%, transparent 100%);"></div>
        </td>
    </tr>
</table>

<!-- Quote Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 32px 40px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td style="padding: 20px 24px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.06) 0%, rgba(139, 92, 246, 0.04) 100%); border-radius: 16px; border-left: 4px solid #6366f1;">
                        <p style="margin: 0 0 12px; font-size: 15px; color: #475569; line-height: 1.7; font-style: italic; font-family: Georgia, ''Times New Roman'', serif;">
                            "We built this because we believe every hour of help deserves recognition."
                        </p>
                        <p style="margin: 0; font-size: 13px; color: #6366f1; font-weight: 600; font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, sans-serif;">
                            — The Hour Timebank Team
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>',
subject = '{{first_name}}, we built something new for you',
preview_text = 'Your timebank has a brand new home — come take a look! ✨'
WHERE tenant_id = 2 AND name = 'Discover the New App';

-- Confirm update
SELECT 'Gold Standard template applied!' as status;
