<?php
/**
 * Weekly Digest Email Template
 *
 * Follows full theme standards from EmailTemplateBuilder
 *
 * Variables:
 * - $userName: User's display name
 * - $offers: Array of new offer listings
 * - $requests: Array of new request listings
 * - $events: Array of upcoming events
 * - $tenantName: (optional) Organization name
 * - $unsubscribeToken: (optional) Token for unsubscribe link
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Env;

$brandColor = '#6366f1';
$brandColorDark = '#4f46e5';
$accentColor = '#f59e0b';
$textColor = '#374151';
$mutedColor = '#6b7280';
$bgColor = '#f3f4f6';
$cardBg = '#ffffff';

$tenantName = $tenantName ?? 'Community';
$appUrl = rtrim(Env::get('APP_URL') ?? '', '/');
$basePath = TenantContext::getBasePath();
$year = date('Y');

$unsubscribeUrl = !empty($unsubscribeToken)
    ? $appUrl . $basePath . '/newsletter/unsubscribe?token=' . $unsubscribeToken
    : $appUrl . $basePath . '/settings';
$settingsUrl = $appUrl . $basePath . '/settings';
$platformUrl = $appUrl . $basePath . '/dashboard';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>Community Pulse - Weekly Digest</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: <?= $bgColor ?>; }

        /* Typography */
        body, table, td, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }

        /* Link styles */
        a { color: <?= $brandColor ?>; text-decoration: underline; }
        a:hover { color: <?= $brandColorDark ?>; }

        /* Button hover */
        .button-primary:hover { background-color: <?= $brandColorDark ?> !important; }

        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .fluid { width: 100% !important; max-width: 100% !important; height: auto !important; }
            .stack-column { display: block !important; width: 100% !important; max-width: 100% !important; }
            .center-on-narrow { text-align: center !important; display: block !important; margin-left: auto !important; margin-right: auto !important; float: none !important; }
            table.center-on-narrow { display: inline-block !important; }
            .hide-mobile { display: none !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #1f2937 !important; }
            .email-container-inner { background-color: #374151 !important; }
            .text-dark { color: #f3f4f6 !important; }
            .text-muted { color: #9ca3af !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: <?= $bgColor ?>;">

    <!-- Preview text -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        Your weekly community highlights - new offers, requests, and upcoming events
        &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847;
    </div>

    <!-- Email wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: <?= $bgColor ?>;" class="email-bg">
        <tr>
            <td style="padding: 40px 10px;">

                <!-- Email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center" class="email-container" style="margin: auto;">

                    <!-- Header with gradient -->
                    <tr>
                        <td style="padding: 30px 40px; text-align: center; background: linear-gradient(135deg, <?= $brandColor ?> 0%, <?= $brandColorDark ?> 100%); border-radius: 16px 16px 0 0;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">Community Pulse</h1>
                            <p style="margin: 10px 0 0; font-size: 16px; color: rgba(255,255,255,0.9);">Your Weekly Digest</p>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="background-color: <?= $cardBg ?>; padding: 0;" class="email-container-inner">

                            <!-- Greeting -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 40px 40px 20px;" class="mobile-padding">
                                        <p style="margin: 0 0 15px; font-size: 18px; line-height: 1.6; color: <?= $textColor ?>;" class="text-dark">Hi <?= htmlspecialchars($userName) ?>,</p>
                                        <p style="margin: 0; font-size: 16px; line-height: 1.8; color: <?= $textColor ?>;" class="text-dark">Here's what's happening in your community this week.</p>
                                    </td>
                                </tr>
                            </table>

                            <?php if (!empty($offers)): ?>
                            <!-- New Offers Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 20px 40px;" class="mobile-padding">
                                        <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: <?= $textColor ?>; display: flex; align-items: center;" class="text-dark">
                                            <span style="margin-right: 10px;">New Offers</span>
                                        </h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <?php foreach ($offers as $item): ?>
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="vertical-align: top;">
                                                                <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: <?= $textColor ?>;" class="text-dark">
                                                                    <a href="<?= $appUrl . $basePath ?>/listings/<?= $item['id'] ?>" style="color: <?= $brandColor ?>; text-decoration: none;"><?= htmlspecialchars($item['title']) ?></a>
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; color: <?= $mutedColor ?>;" class="text-muted">by <?= htmlspecialchars($item['user_name']) ?></p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

                            <?php if (!empty($requests)): ?>
                            <!-- New Requests Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 20px 40px;" class="mobile-padding">
                                        <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: <?= $textColor ?>; display: flex; align-items: center;" class="text-dark">
                                            <span style="margin-right: 10px;">New Requests</span>
                                        </h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <?php foreach ($requests as $item): ?>
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="vertical-align: top;">
                                                                <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: <?= $textColor ?>;" class="text-dark">
                                                                    <a href="<?= $appUrl . $basePath ?>/listings/<?= $item['id'] ?>" style="color: <?= $brandColor ?>; text-decoration: none;"><?= htmlspecialchars($item['title']) ?></a>
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; color: <?= $mutedColor ?>;" class="text-muted">by <?= htmlspecialchars($item['user_name']) ?></p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

                            <?php if (!empty($events)): ?>
                            <!-- Upcoming Events Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 20px 40px;" class="mobile-padding">
                                        <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: <?= $textColor ?>; display: flex; align-items: center;" class="text-dark">
                                            <span style="margin-right: 10px;">Upcoming Events</span>
                                        </h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <?php foreach ($events as $item): ?>
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td width="60" style="vertical-align: top; padding-right: 15px;">
                                                                <div style="background: linear-gradient(135deg, <?= $brandColor ?> 0%, <?= $brandColorDark ?> 100%); color: white; text-align: center; padding: 8px; border-radius: 8px;">
                                                                    <div style="font-size: 18px; font-weight: 700;"><?= date('j', strtotime($item['start_time'])) ?></div>
                                                                    <div style="font-size: 11px; text-transform: uppercase;"><?= date('M', strtotime($item['start_time'])) ?></div>
                                                                </div>
                                                            </td>
                                                            <td style="vertical-align: top;">
                                                                <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: <?= $textColor ?>;" class="text-dark">
                                                                    <a href="<?= $appUrl . $basePath ?>/events/<?= $item['id'] ?>" style="color: <?= $brandColor ?>; text-decoration: none;"><?= htmlspecialchars($item['title']) ?></a>
                                                                </p>
                                                                <p style="margin: 0; font-size: 13px; color: <?= $accentColor ?>; font-weight: 500;">
                                                                    <?= date('M j @ g:ia', strtotime($item['start_time'])) ?> &bull; <?= htmlspecialchars($item['location']) ?>
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 30px 40px 40px; text-align: center;" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 10px; background: linear-gradient(135deg, <?= $brandColor ?> 0%, <?= $brandColorDark ?> 100%);" class="button-primary">
                                                    <a href="<?= $platformUrl ?>" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">Visit Platform</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px 40px; border-radius: 0 0 16px 16px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            &copy; <?= $year ?> <?= htmlspecialchars($tenantName) ?>. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 13px; color: #9ca3af; line-height: 1.6;">
                                            You received this email because you opted into weekly digests.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="<?= $unsubscribeUrl ?>" style="color: #6b7280; text-decoration: underline; font-size: 13px;">Unsubscribe</a>
                                        <span style="color: #d1d5db; margin: 0 8px;">|</span>
                                        <a href="<?= $settingsUrl ?>" style="color: #6b7280; text-decoration: underline; font-size: 13px;">Manage Preferences</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
