<?php
/**
 * NewsletterTemplates - Professional preset email templates
 *
 * Pre-built templates for common newsletter types following full theme standards.
 * All templates use table-based layouts for email client compatibility.
 */

namespace Nexus\Services;

class NewsletterTemplates
{
    // Theme colors (matching EmailTemplateBuilder standards)
    private const BRAND_COLOR = '#6366f1';
    private const BRAND_COLOR_DARK = '#4f46e5';
    private const ACCENT_COLOR = '#f59e0b';
    private const SUCCESS_COLOR = '#10b981';
    private const TEXT_COLOR = '#374151';
    private const TEXT_DARK = '#1f2937';
    private const MUTED_COLOR = '#6b7280';

    /**
     * Get list of available templates with metadata
     */
    public static function getTemplates(): array
    {
        return [
            'blank' => [
                'name' => 'Blank',
                'description' => 'Start from scratch with a clean slate',
                'icon' => 'fa-file',
                'category' => 'basic'
            ],
            'announcement' => [
                'name' => 'Announcement',
                'description' => 'Share important news with your community',
                'icon' => 'fa-bullhorn',
                'category' => 'communication'
            ],
            'weekly_update' => [
                'name' => 'Weekly Update',
                'description' => 'Regular digest with multiple sections',
                'icon' => 'fa-newspaper',
                'category' => 'recurring'
            ],
            'event_invite' => [
                'name' => 'Event Invitation',
                'description' => 'Invite members to an upcoming event',
                'icon' => 'fa-calendar-star',
                'category' => 'events'
            ],
            'welcome' => [
                'name' => 'Welcome Email',
                'description' => 'Greet new subscribers warmly',
                'icon' => 'fa-hand-wave',
                'category' => 'onboarding'
            ],
            'feature_spotlight' => [
                'name' => 'Feature Spotlight',
                'description' => 'Highlight new features or updates',
                'icon' => 'fa-lightbulb',
                'category' => 'product'
            ],
            'community_digest' => [
                'name' => 'Community Digest',
                'description' => 'Showcase listings, events, and activity',
                'icon' => 'fa-users',
                'category' => 'community'
            ],
            'promotional' => [
                'name' => 'Promotional',
                'description' => 'Promote offers, deals, or campaigns',
                'icon' => 'fa-tags',
                'category' => 'marketing'
            ],
            'thank_you' => [
                'name' => 'Thank You',
                'description' => 'Express gratitude to your community',
                'icon' => 'fa-heart',
                'category' => 'appreciation'
            ],
            'survey_feedback' => [
                'name' => 'Survey Request',
                'description' => 'Request feedback from your members',
                'icon' => 'fa-clipboard-question',
                'category' => 'engagement'
            ]
        ];
    }

    /**
     * Get template content by ID
     */
    public static function getTemplate(string $templateId): array
    {
        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $accentColor = self::ACCENT_COLOR;
        $successColor = self::SUCCESS_COLOR;
        $textColor = self::TEXT_COLOR;
        $textDark = self::TEXT_DARK;
        $mutedColor = self::MUTED_COLOR;

        $templates = [
            'blank' => [
                'subject' => '',
                'preview_text' => '',
                'content' => ''
            ],

            'announcement' => [
                'subject' => 'Important Update from {{tenant_name}}',
                'preview_text' => 'We have exciting news to share with you!',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 0 0 25px;">
            <h2 style="margin: 0; font-size: 24px; font-weight: 700; color: {$textDark}; line-height: 1.3;">Big News!</h2>
        </td>
    </tr>
    <tr>
        <td style="padding: 0 0 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Hello {{first_name}},
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding: 0 0 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                We're excited to share some important news with our community...
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding: 0 0 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                [Add your announcement details here. Be clear, concise, and explain why this matters to your readers.]
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 10px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%);">
                        <a href="#" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">Learn More</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding: 0 0 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                As always, we're here if you have any questions.
            </p>
        </td>
    </tr>
    <tr>
        <td>
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Best wishes,<br>
                <strong>The {{tenant_name}} Team</strong>
            </p>
        </td>
    </tr>
</table>
HTML
            ],

            'weekly_update' => [
                'subject' => 'Your Weekly Update from {{tenant_name}}',
                'preview_text' => 'Here\'s what\'s new in your community this week',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 0 0 25px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Hi {{first_name}},
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding: 0 0 30px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Here's what's happening in your community this week:
            </p>
        </td>
    </tr>
</table>

<!-- Highlights Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 25px;">
    <tr>
        <td style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 12px; padding: 25px; border-left: 4px solid {$brandColor};">
            <h3 style="margin: 0 0 15px; font-size: 18px; font-weight: 700; color: {$brandColorDark};">This Week's Highlights</h3>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Highlight one goes here</td></tr>
                <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Highlight two goes here</td></tr>
                <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Highlight three goes here</td></tr>
            </table>
        </td>
    </tr>
</table>

<!-- New Items Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 25px;">
    <tr>
        <td style="padding-bottom: 15px; border-bottom: 2px solid #f3f4f6;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">New This Week</h3>
        </td>
    </tr>
    <tr>
        <td style="padding: 15px 0; border-bottom: 1px solid #f3f4f6;">
            <p style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: {$textDark};">Item Title Here</p>
            <p style="margin: 0; font-size: 14px; color: {$mutedColor};">Brief description of the item</p>
        </td>
    </tr>
    <tr>
        <td style="padding: 15px 0; border-bottom: 1px solid #f3f4f6;">
            <p style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: {$textDark};">Another Item Title</p>
            <p style="margin: 0; font-size: 14px; color: {$mutedColor};">Brief description of the item</p>
        </td>
    </tr>
</table>

<!-- Events Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 15px; border-bottom: 2px solid #f3f4f6;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">Upcoming Events</h3>
        </td>
    </tr>
    <tr>
        <td style="padding-top: 15px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fefce8; border: 1px solid #fef08a; border-radius: 10px;">
                <tr>
                    <td style="padding: 18px;">
                        <p style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: {$textDark};">Event Name</p>
                        <p style="margin: 0; font-size: 14px; color: #92400e; font-weight: 500;">Location &bull; Date &amp; Time</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 10px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%);">
                        <a href="#" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">View All Updates</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="text-align: center;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: {$mutedColor};">
                Have something to share? <a href="#" style="color: {$brandColor}; text-decoration: none; font-weight: 500;">Post it on the platform</a>
            </p>
        </td>
    </tr>
</table>
HTML
            ],

            'event_invite' => [
                'subject' => 'You\'re Invited: [Event Name]',
                'preview_text' => 'Join us for an exciting event - save your spot!',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="text-align: center; padding-bottom: 30px;">
            <span style="display: inline-block; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 8px 16px; border-radius: 20px;">You're Invited</span>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 15px;">
            <h2 style="margin: 0; font-size: 28px; font-weight: 800; color: {$textDark}; line-height: 1.2;">Event Name Here</h2>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 30px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.6; color: {$mutedColor};">
                A brief, compelling description of what this event is about and why it's not to be missed.
            </p>
        </td>
    </tr>
</table>

<!-- Event Details Card -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e5e7eb; border-radius: 16px; margin-bottom: 30px;">
    <tr>
        <td style="padding: 30px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td style="padding: 12px 0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="40" style="vertical-align: top; font-size: 24px;">&#128197;</td>
                                <td style="vertical-align: top;">
                                    <p style="margin: 0; font-size: 13px; color: {$mutedColor}; text-transform: uppercase; letter-spacing: 0.5px;">Date</p>
                                    <p style="margin: 4px 0 0; font-size: 16px; font-weight: 600; color: {$textDark};">Saturday, January 15, 2025</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px 0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="40" style="vertical-align: top; font-size: 24px;">&#128336;</td>
                                <td style="vertical-align: top;">
                                    <p style="margin: 0; font-size: 13px; color: {$mutedColor}; text-transform: uppercase; letter-spacing: 0.5px;">Time</p>
                                    <p style="margin: 4px 0 0; font-size: 16px; font-weight: 600; color: {$textDark};">2:00 PM - 5:00 PM</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px 0;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="40" style="vertical-align: top; font-size: 24px;">&#128205;</td>
                                <td style="vertical-align: top;">
                                    <p style="margin: 0; font-size: 13px; color: {$mutedColor}; text-transform: uppercase; letter-spacing: 0.5px;">Location</p>
                                    <p style="margin: 4px 0 0; font-size: 16px; font-weight: 600; color: {$textDark};">Venue Name, Address</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- What to Expect -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 15px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">What to Expect</h3>
        </td>
    </tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Point one about the event</td></tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Point two about the event</td></tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Point three about the event</td></tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 12px; background: linear-gradient(135deg, {$successColor} 0%, #059669 100%); box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">
                        <a href="#" style="display: inline-block; padding: 18px 40px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">RSVP Now - Save Your Spot</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="text-align: center;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: {$mutedColor};">
                Spots are limited! Reserve yours today.
            </p>
        </td>
    </tr>
</table>
HTML
            ],

            'welcome' => [
                'subject' => 'Welcome to {{tenant_name}}!',
                'preview_text' => 'We\'re thrilled to have you join our community',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="text-align: center; padding-bottom: 25px;">
            <span style="font-size: 48px;">&#128075;</span>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 20px;">
            <h2 style="margin: 0; font-size: 28px; font-weight: 800; color: {$textDark}; line-height: 1.2;">Welcome to the Community!</h2>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Hi {{first_name}},
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 25px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                We're absolutely thrilled to have you join {{tenant_name}}! You're now part of a vibrant community of like-minded people.
            </p>
        </td>
    </tr>
</table>

<!-- Quick Start Steps -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">Get Started in 3 Steps</h3>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 15px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 12px; border-left: 4px solid {$successColor};">
                <tr>
                    <td style="padding: 20px;">
                        <p style="margin: 0; font-size: 15px; color: #065f46;">
                            <strong style="color: #047857;">Step 1:</strong> Complete your profile to connect with others
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 15px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 12px; border-left: 4px solid #3b82f6;">
                <tr>
                    <td style="padding: 20px;">
                        <p style="margin: 0; font-size: 15px; color: #1e40af;">
                            <strong style="color: #1d4ed8;">Step 2:</strong> Browse what others are offering and requesting
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 25px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; border-left: 4px solid {$accentColor};">
                <tr>
                    <td style="padding: 20px;">
                        <p style="margin: 0; font-size: 15px; color: #92400e;">
                            <strong style="color: #b45309;">Step 3:</strong> Post your first listing or join a group
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 10px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%);">
                        <a href="#" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">Complete Your Profile</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding-top: 30px;">
            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.8; color: {$textColor};">
                If you have any questions, just reply to this email &#8212; we're always happy to help!
            </p>
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Welcome aboard!<br>
                <strong>The {{tenant_name}} Team</strong>
            </p>
        </td>
    </tr>
</table>
HTML
            ],

            'feature_spotlight' => [
                'subject' => 'New Feature: [Feature Name] is Here!',
                'preview_text' => 'Check out what\'s new and how it can help you',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="text-align: center; padding-bottom: 20px;">
            <span style="display: inline-block; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 8px 16px; border-radius: 20px;">New Feature</span>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 15px;">
            <h2 style="margin: 0; font-size: 28px; font-weight: 800; color: {$textDark}; line-height: 1.2;">Introducing: Feature Name</h2>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 30px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.6; color: {$mutedColor};">
                A brief description of the new feature and the problem it solves.
            </p>
        </td>
    </tr>
</table>

<!-- Feature Image Placeholder -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 16px; padding: 60px 40px; text-align: center;">
            <span style="font-size: 48px;">&#128444;</span>
            <p style="margin: 15px 0 0; font-size: 14px; color: {$mutedColor};">Add a screenshot or illustration here</p>
        </td>
    </tr>
</table>

<!-- Benefits Grid -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">What's New</h3>
        </td>
    </tr>
    <tr>
        <td>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="50%" style="padding: 10px; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="text-align: center; padding: 20px;">
                            <tr><td style="font-size: 32px; padding-bottom: 12px;">&#9889;</td></tr>
                            <tr><td style="font-size: 16px; font-weight: 700; color: {$textDark}; padding-bottom: 8px;">Benefit One</td></tr>
                            <tr><td style="font-size: 14px; color: {$mutedColor}; line-height: 1.5;">Explain this benefit briefly</td></tr>
                        </table>
                    </td>
                    <td width="50%" style="padding: 10px; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="text-align: center; padding: 20px;">
                            <tr><td style="font-size: 32px; padding-bottom: 12px;">&#127919;</td></tr>
                            <tr><td style="font-size: 16px; font-weight: 700; color: {$textDark}; padding-bottom: 8px;">Benefit Two</td></tr>
                            <tr><td style="font-size: 14px; color: {$mutedColor}; line-height: 1.5;">Explain this benefit briefly</td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td width="50%" style="padding: 10px; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="text-align: center; padding: 20px;">
                            <tr><td style="font-size: 32px; padding-bottom: 12px;">&#10024;</td></tr>
                            <tr><td style="font-size: 16px; font-weight: 700; color: {$textDark}; padding-bottom: 8px;">Benefit Three</td></tr>
                            <tr><td style="font-size: 14px; color: {$mutedColor}; line-height: 1.5;">Explain this benefit briefly</td></tr>
                        </table>
                    </td>
                    <td width="50%" style="padding: 10px; vertical-align: top;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="text-align: center; padding: 20px;">
                            <tr><td style="font-size: 32px; padding-bottom: 12px;">&#128640;</td></tr>
                            <tr><td style="font-size: 16px; font-weight: 700; color: {$textDark}; padding-bottom: 8px;">Benefit Four</td></tr>
                            <tr><td style="font-size: 14px; color: {$mutedColor}; line-height: 1.5;">Explain this benefit briefly</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 10px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%);">
                        <a href="#" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">Try It Now</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HTML
            ],

            'community_digest' => [
                'subject' => 'What\'s Happening in Your Community',
                'preview_text' => 'New offers, requests, and events from your neighbors',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 25px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Hi {{first_name}},
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 30px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Here's the latest from your community &#8212; fresh offers, new requests, and upcoming events!
            </p>
        </td>
    </tr>
</table>

<!-- New Offers Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 15px; border-bottom: 2px solid {$successColor};">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">New Offers</h3>
        </td>
    </tr>
    <tr>
        <td style="padding: 15px 0; border-bottom: 1px solid #f3f4f6;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="70" style="vertical-align: top; padding-right: 15px;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 10px; text-align: center; line-height: 60px;">
                            <span style="font-size: 24px;">&#128230;</span>
                        </div>
                    </td>
                    <td style="vertical-align: top;">
                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: {$textDark};">
                            <a href="#" style="color: {$textDark}; text-decoration: none;">Offer Title Here</a>
                        </p>
                        <p style="margin: 0 0 4px; font-size: 14px; color: {$mutedColor};">Posted by Member Name</p>
                        <p style="margin: 0; font-size: 13px; color: {$successColor}; font-weight: 500;">Location</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding: 15px 0; border-bottom: 1px solid #f3f4f6;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td width="70" style="vertical-align: top; padding-right: 15px;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 10px; text-align: center; line-height: 60px;">
                            <span style="font-size: 24px;">&#128295;</span>
                        </div>
                    </td>
                    <td style="vertical-align: top;">
                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: {$textDark};">
                            <a href="#" style="color: {$textDark}; text-decoration: none;">Another Offer Title</a>
                        </p>
                        <p style="margin: 0 0 4px; font-size: 14px; color: {$mutedColor};">Posted by Member Name</p>
                        <p style="margin: 0; font-size: 13px; color: {$successColor}; font-weight: 500;">Location</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Looking For Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 15px; border-bottom: 2px solid {$accentColor};">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">People Looking For</h3>
        </td>
    </tr>
    <tr>
        <td style="padding-top: 15px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%); border-radius: 10px; margin-bottom: 10px;">
                <tr>
                    <td style="padding: 15px;">
                        <p style="margin: 0 0 4px; font-size: 15px; font-weight: 600; color: {$textDark};">Request Title Here</p>
                        <p style="margin: 0; font-size: 14px; color: #92400e;">Can you help? <a href="#" style="color: #d97706; font-weight: 500; text-decoration: none;">Respond &#8594;</a></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%); border-radius: 10px;">
                <tr>
                    <td style="padding: 15px;">
                        <p style="margin: 0 0 4px; font-size: 15px; font-weight: 600; color: {$textDark};">Another Request</p>
                        <p style="margin: 0; font-size: 14px; color: #92400e;">Can you help? <a href="#" style="color: #d97706; font-weight: 500; text-decoration: none;">Respond &#8594;</a></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Upcoming Events Section -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 15px; border-bottom: 2px solid {$brandColor};">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">Upcoming Events</h3>
        </td>
    </tr>
    <tr>
        <td style="padding-top: 15px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 10px;">
                <tr>
                    <td style="padding: 18px;">
                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: {$textDark};">Event Name</p>
                        <p style="margin: 0; font-size: 14px; color: {$brandColor};">Venue &bull; Date at Time</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 10px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%);">
                        <a href="#" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">Explore Your Community</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HTML
            ],

            'promotional' => [
                'subject' => '[Limited Time] Special Offer Inside!',
                'preview_text' => 'Don\'t miss this exclusive offer for our community',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="text-align: center; padding-bottom: 25px;">
            <span style="display: inline-block; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 8px 16px; border-radius: 20px;">Limited Time Offer</span>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 15px;">
            <h2 style="margin: 0; font-size: 32px; font-weight: 800; color: {$textDark}; line-height: 1.2;">Your Special Offer</h2>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 30px;">
            <p style="margin: 0; font-size: 18px; line-height: 1.6; color: {$mutedColor};">
                Don't miss out on this exclusive opportunity!
            </p>
        </td>
    </tr>
</table>

<!-- Offer Box -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px dashed {$accentColor}; border-radius: 16px; margin-bottom: 30px;">
    <tr>
        <td style="padding: 35px; text-align: center;">
            <p style="margin: 0 0 10px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: #92400e; font-weight: 600;">Your Exclusive Offer</p>
            <p style="margin: 0 0 10px; font-size: 48px; font-weight: 800; color: {$textDark}; line-height: 1;">50% OFF</p>
            <p style="margin: 0; font-size: 16px; color: #78350f;">Use code: <strong style="background: white; padding: 4px 12px; border-radius: 6px; font-family: monospace;">SPECIAL50</strong></p>
        </td>
    </tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Describe the offer in more detail here. Explain what they get, why it's valuable, and what makes this offer special.
            </p>
        </td>
    </tr>
</table>

<!-- What's Included -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 15px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">What's Included:</h3>
        </td>
    </tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Benefit or feature one</td></tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Benefit or feature two</td></tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; Benefit or feature three</td></tr>
</table>

<!-- Urgency -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border-radius: 10px; padding: 15px 20px; text-align: center;">
            <p style="margin: 0; font-size: 15px; color: #dc2626; font-weight: 600;">
                &#9200; Offer expires in [X days] &#8212; Don't wait!
            </p>
        </td>
    </tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 12px; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); box-shadow: 0 4px 14px rgba(220, 38, 38, 0.4);">
                        <a href="#" style="display: inline-block; padding: 18px 40px; font-size: 18px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 12px;">Claim Your Offer Now</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HTML
            ],

            'thank_you' => [
                'subject' => 'Thank You!',
                'preview_text' => 'A heartfelt thank you from all of us',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="text-align: center; padding-bottom: 25px;">
            <span style="font-size: 60px;">&#10084;&#65039;</span>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 20px;">
            <h2 style="margin: 0; font-size: 28px; font-weight: 800; color: {$textDark}; line-height: 1.2;">Thank You, {{first_name}}!</h2>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 25px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                We wanted to take a moment to express our sincere gratitude.
            </p>
        </td>
    </tr>
</table>

<!-- Quote Box -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); border-radius: 16px; padding: 30px; border-left: 4px solid #ec4899;">
            <p style="margin: 0; font-size: 17px; line-height: 1.8; color: {$textColor}; font-style: italic;">
                "Your support means the world to us. Because of members like you, our community continues to grow and thrive."
            </p>
        </td>
    </tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                [Personalize this section with specific reasons for thanking them &#8212; their participation, support, feedback, etc.]
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 30px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                We're committed to making this community even better, and we couldn't do it without you.
            </p>
        </td>
    </tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 10px; background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                        <a href="#" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">Continue the Journey</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td>
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                With gratitude,<br>
                <strong>The {{tenant_name}} Team</strong>
            </p>
        </td>
    </tr>
</table>
HTML
            ],

            'survey_feedback' => [
                'subject' => 'We Value Your Opinion',
                'preview_text' => 'Help us improve &#8212; share your feedback',
                'content' => <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="text-align: center; padding-bottom: 25px;">
            <span style="font-size: 48px;">&#128221;</span>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 15px;">
            <h2 style="margin: 0; font-size: 28px; font-weight: 800; color: {$textDark}; line-height: 1.2;">We'd Love Your Feedback</h2>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-bottom: 30px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.6; color: {$mutedColor};">
                Your opinion matters! Help us make things better.
            </p>
        </td>
    </tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom: 20px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                Hi {{first_name}},
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 25px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.8; color: {$textColor};">
                We're always working to improve your experience, and your feedback is incredibly valuable. Could you spare a few minutes to share your thoughts?
            </p>
        </td>
    </tr>
</table>

<!-- Survey Info -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px; padding: 25px; text-align: center;">
            <p style="margin: 0 0 10px; font-size: 14px; color: {$mutedColor};">&#9200; Takes only</p>
            <p style="margin: 0; font-size: 32px; font-weight: 800; color: {$brandColor};">2-3 minutes</p>
        </td>
    </tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
    <tr>
        <td style="padding-bottom: 15px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: {$textDark};">What We'll Ask:</h3>
        </td>
    </tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; How you're finding the platform</td></tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; What's working well for you</td></tr>
    <tr><td style="padding: 6px 0; font-size: 15px; line-height: 1.6; color: {$textColor};">&#8226; What we could do better</td></tr>
</table>

<!-- CTA -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="padding: 35px 0; text-align: center;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                <tr>
                    <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%);">
                        <a href="#" style="display: inline-block; padding: 18px 40px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">Take the Survey</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="text-align: center;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: {$mutedColor};">
                Your responses are anonymous and will help shape the future of {{tenant_name}}.
            </p>
        </td>
    </tr>
</table>
HTML
            ]
        ];

        return $templates[$templateId] ?? $templates['blank'];
    }

    /**
     * Replace tenant name placeholder
     */
    public static function processTemplate(array $template, string $tenantName): array
    {
        $search = '{{tenant_name}}';
        $template['subject'] = str_replace($search, $tenantName, $template['subject']);
        $template['preview_text'] = str_replace($search, $tenantName, $template['preview_text']);
        $template['content'] = str_replace($search, $tenantName, $template['content']);
        return $template;
    }
}
