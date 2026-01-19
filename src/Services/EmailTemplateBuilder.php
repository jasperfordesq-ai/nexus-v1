<?php
/**
 * EmailTemplateBuilder - Professional Rich HTML Email Generator
 *
 * Creates beautiful, responsive HTML emails that work across all email clients
 * including Outlook, Gmail, Apple Mail, and mobile clients.
 */

namespace Nexus\Services;

use Nexus\Core\Env;
use Nexus\Core\TenantContext;

class EmailTemplateBuilder
{
    private string $brandColor = '#6366f1';
    private string $brandColorDark = '#4f46e5';
    private string $accentColor = '#f59e0b';
    private string $successColor = '#10b981';
    private string $textColor = '#374151';
    private string $mutedColor = '#6b7280';
    private string $bgColor = '#f3f4f6';
    private string $cardBg = '#ffffff';

    private string $tenantName;
    private ?string $logoUrl = null;
    private string $appUrl;
    private string $basePath;

    private array $sections = [];
    private string $previewText = '';
    private ?string $unsubscribeToken = null;

    public function __construct(string $tenantName = 'Newsletter')
    {
        $this->tenantName = $tenantName;
        $this->appUrl = rtrim(Env::get('APP_URL') ?? '', '/');
        $this->basePath = TenantContext::getBasePath();
    }

    /**
     * Set brand colors for the email
     */
    public function setBrandColors(string $primary, string $accent = null): self
    {
        $this->brandColor = $primary;
        if ($accent) {
            $this->accentColor = $accent;
        }
        return $this;
    }

    /**
     * Set the logo URL
     */
    public function setLogo(string $logoUrl): self
    {
        $this->logoUrl = $logoUrl;
        return $this;
    }

    /**
     * Set preview text (shown in email client inbox)
     */
    public function setPreviewText(string $text): self
    {
        $this->previewText = $text;
        return $this;
    }

    /**
     * Set unsubscribe token for tracking
     */
    public function setUnsubscribeToken(string $token): self
    {
        $this->unsubscribeToken = $token;
        return $this;
    }

    /**
     * Add a hero section with large heading and optional image
     */
    public function addHero(string $title, string $subtitle = '', string $imageUrl = null, string $ctaText = null, string $ctaUrl = null): self
    {
        $this->sections[] = [
            'type' => 'hero',
            'title' => $title,
            'subtitle' => $subtitle,
            'image' => $imageUrl,
            'ctaText' => $ctaText,
            'ctaUrl' => $ctaUrl
        ];
        return $this;
    }

    /**
     * Add a text paragraph section
     */
    public function addText(string $content, string $alignment = 'left'): self
    {
        $this->sections[] = [
            'type' => 'text',
            'content' => $content,
            'align' => $alignment
        ];
        return $this;
    }

    /**
     * Add a heading
     */
    public function addHeading(string $text, int $level = 2, string $alignment = 'left'): self
    {
        $this->sections[] = [
            'type' => 'heading',
            'text' => $text,
            'level' => $level,
            'align' => $alignment
        ];
        return $this;
    }

    /**
     * Add a call-to-action button
     */
    public function addButton(string $text, string $url, string $style = 'primary', string $alignment = 'center'): self
    {
        $this->sections[] = [
            'type' => 'button',
            'text' => $text,
            'url' => $url,
            'style' => $style,
            'align' => $alignment
        ];
        return $this;
    }

    /**
     * Add an image
     */
    public function addImage(string $url, string $alt = '', string $linkUrl = null, int $maxWidth = 600): self
    {
        $this->sections[] = [
            'type' => 'image',
            'url' => $url,
            'alt' => $alt,
            'link' => $linkUrl,
            'maxWidth' => $maxWidth
        ];
        return $this;
    }

    /**
     * Add a divider line
     */
    public function addDivider(string $style = 'solid'): self
    {
        $this->sections[] = [
            'type' => 'divider',
            'style' => $style
        ];
        return $this;
    }

    /**
     * Add a spacer
     */
    public function addSpacer(int $height = 30): self
    {
        $this->sections[] = [
            'type' => 'spacer',
            'height' => $height
        ];
        return $this;
    }

    /**
     * Add a feature grid (2-3 columns of icon + text)
     */
    public function addFeatureGrid(array $features): self
    {
        // Features should be array of ['icon' => '🎯', 'title' => 'Title', 'text' => 'Description']
        $this->sections[] = [
            'type' => 'feature_grid',
            'features' => $features
        ];
        return $this;
    }

    /**
     * Add a content card with optional image
     */
    public function addCard(string $title, string $text, string $imageUrl = null, string $ctaText = null, string $ctaUrl = null): self
    {
        $this->sections[] = [
            'type' => 'card',
            'title' => $title,
            'text' => $text,
            'image' => $imageUrl,
            'ctaText' => $ctaText,
            'ctaUrl' => $ctaUrl
        ];
        return $this;
    }

    /**
     * Add a list of items (listings, events, etc.)
     */
    public function addItemList(array $items, string $title = null): self
    {
        // Items: ['title' => '', 'subtitle' => '', 'meta' => '', 'url' => '', 'image' => '']
        $this->sections[] = [
            'type' => 'item_list',
            'title' => $title,
            'items' => $items
        ];
        return $this;
    }

    /**
     * Add a quote/testimonial block
     */
    public function addQuote(string $quote, string $author = null, string $role = null): self
    {
        $this->sections[] = [
            'type' => 'quote',
            'quote' => $quote,
            'author' => $author,
            'role' => $role
        ];
        return $this;
    }

    /**
     * Add social media links
     */
    public function addSocialLinks(array $links): self
    {
        // ['facebook' => 'url', 'twitter' => 'url', 'instagram' => 'url', etc.]
        $this->sections[] = [
            'type' => 'social',
            'links' => $links
        ];
        return $this;
    }

    /**
     * Add raw HTML content (for custom content)
     */
    public function addRawHtml(string $html): self
    {
        $this->sections[] = [
            'type' => 'raw',
            'html' => $html
        ];
        return $this;
    }

    /**
     * Render the complete email HTML
     */
    public function render(): string
    {
        $content = $this->renderSections();
        $unsubscribeUrl = $this->unsubscribeToken
            ? $this->appUrl . $this->basePath . '/newsletter/unsubscribe?token=' . $this->unsubscribeToken
            : $this->appUrl . $this->basePath . '/settings';

        $year = date('Y');

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>{$this->tenantName}</title>
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
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: {$this->bgColor}; }

        /* Typography */
        body, table, td, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }

        /* Link styles */
        a { color: {$this->brandColor}; text-decoration: underline; }
        a:hover { color: {$this->brandColorDark}; }

        /* Button hover */
        .button-primary:hover { background-color: {$this->brandColorDark} !important; }
        .button-secondary:hover { background-color: #e5e7eb !important; }
        .button-success:hover { background-color: #059669 !important; }

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
<body style="margin: 0; padding: 0; background-color: {$this->bgColor};">

    <!-- Preview text -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {$this->previewText}
        &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847;
    </div>

    <!-- Email wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: {$this->bgColor};" class="email-bg">
        <tr>
            <td style="padding: 40px 10px;">

                <!-- Email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center" class="email-container" style="margin: auto;">

                    <!-- Header with logo -->
                    <tr>
                        <td style="padding: 30px 40px; text-align: center; background: linear-gradient(135deg, {$this->brandColor} 0%, {$this->brandColorDark} 100%); border-radius: 16px 16px 0 0;">
                            {$this->renderLogo()}
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="background-color: {$this->cardBg}; padding: 0;" class="email-container-inner">
                            {$content}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px 40px; border-radius: 0 0 16px 16px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            &copy; {$year} {$this->tenantName}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 13px; color: #9ca3af; line-height: 1.6;">
                                            You received this email because you're subscribed to the {$this->tenantName} newsletter.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$unsubscribeUrl}" style="color: #6b7280; text-decoration: underline; font-size: 13px;">Unsubscribe</a>
                                        <span style="color: #d1d5db; margin: 0 8px;">|</span>
                                        <a href="{$this->appUrl}{$this->basePath}/settings" style="color: #6b7280; text-decoration: underline; font-size: 13px;">Manage Preferences</a>
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
HTML;
    }

    /**
     * Render logo in header
     */
    private function renderLogo(): string
    {
        if ($this->logoUrl) {
            return '<img src="' . htmlspecialchars($this->logoUrl) . '" alt="' . htmlspecialchars($this->tenantName) . '" width="180" style="display: block; margin: 0 auto;">';
        }
        return '<h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">' . htmlspecialchars($this->tenantName) . '</h1>';
    }

    /**
     * Render all content sections
     */
    private function renderSections(): string
    {
        $html = '';
        foreach ($this->sections as $section) {
            $html .= $this->renderSection($section);
        }
        return $html;
    }

    /**
     * Render a single section
     */
    private function renderSection(array $section): string
    {
        switch ($section['type']) {
            case 'hero':
                return $this->renderHero($section);
            case 'text':
                return $this->renderText($section);
            case 'heading':
                return $this->renderHeading($section);
            case 'button':
                return $this->renderButton($section);
            case 'image':
                return $this->renderImage($section);
            case 'divider':
                return $this->renderDivider($section);
            case 'spacer':
                return $this->renderSpacer($section);
            case 'feature_grid':
                return $this->renderFeatureGrid($section);
            case 'card':
                return $this->renderCard($section);
            case 'item_list':
                return $this->renderItemList($section);
            case 'quote':
                return $this->renderQuote($section);
            case 'social':
                return $this->renderSocialLinks($section);
            case 'raw':
                return $section['html'];
            default:
                return '';
        }
    }

    private function renderHero(array $section): string
    {
        $image = '';
        if (!empty($section['image'])) {
            $image = '<img src="' . htmlspecialchars($section['image']) . '" width="600" style="width: 100%; max-width: 600px; height: auto; display: block; margin-bottom: 30px; border-radius: 12px;" alt="">';
        }

        $subtitle = '';
        if (!empty($section['subtitle'])) {
            $subtitle = '<p style="margin: 15px 0 0; font-size: 18px; line-height: 1.6; color: ' . $this->mutedColor . ';">' . htmlspecialchars($section['subtitle']) . '</p>';
        }

        $cta = '';
        if (!empty($section['ctaText']) && !empty($section['ctaUrl'])) {
            $cta = '
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 30px auto 0;">
                <tr>
                    <td style="border-radius: 10px; background: linear-gradient(135deg, ' . $this->brandColor . ' 0%, ' . $this->brandColorDark . ' 100%);" class="button-primary">
                        <a href="' . htmlspecialchars($section['ctaUrl']) . '" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">' . htmlspecialchars($section['ctaText']) . '</a>
                    </td>
                </tr>
            </table>';
        }

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 40px 40px 30px; text-align: center;" class="mobile-padding">
                    ' . $image . '
                    <h1 style="margin: 0; font-size: 32px; font-weight: 800; color: ' . $this->textColor . '; line-height: 1.2; letter-spacing: -0.5px;" class="text-dark">' . htmlspecialchars($section['title']) . '</h1>
                    ' . $subtitle . '
                    ' . $cta . '
                </td>
            </tr>
        </table>';
    }

    private function renderText(array $section): string
    {
        $align = $section['align'] ?? 'left';
        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 0 40px 20px; text-align: ' . $align . ';" class="mobile-padding">
                    <p style="margin: 0; font-size: 16px; line-height: 1.8; color: ' . $this->textColor . ';" class="text-dark">' . $section['content'] . '</p>
                </td>
            </tr>
        </table>';
    }

    private function renderHeading(array $section): string
    {
        $align = $section['align'] ?? 'left';
        $level = $section['level'] ?? 2;
        $sizes = [1 => 28, 2 => 24, 3 => 20, 4 => 18];
        $size = $sizes[$level] ?? 20;

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 20px 40px 10px; text-align: ' . $align . ';" class="mobile-padding">
                    <h' . $level . ' style="margin: 0; font-size: ' . $size . 'px; font-weight: 700; color: ' . $this->textColor . '; line-height: 1.3;" class="text-dark">' . htmlspecialchars($section['text']) . '</h' . $level . '>
                </td>
            </tr>
        </table>';
    }

    private function renderButton(array $section): string
    {
        $align = $section['align'] ?? 'center';
        $style = $section['style'] ?? 'primary';

        $colors = [
            'primary' => ['bg' => $this->brandColor, 'text' => '#ffffff', 'class' => 'button-primary'],
            'secondary' => ['bg' => '#f3f4f6', 'text' => $this->textColor, 'class' => 'button-secondary'],
            'success' => ['bg' => $this->successColor, 'text' => '#ffffff', 'class' => 'button-success'],
            'accent' => ['bg' => $this->accentColor, 'text' => '#ffffff', 'class' => 'button-primary']
        ];
        $color = $colors[$style] ?? $colors['primary'];

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 15px 40px 25px; text-align: ' . $align . ';" class="mobile-padding">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                        <tr>
                            <td style="border-radius: 10px; background-color: ' . $color['bg'] . ';" class="' . $color['class'] . '">
                                <a href="' . htmlspecialchars($section['url']) . '" style="display: inline-block; padding: 14px 28px; font-size: 15px; font-weight: 600; color: ' . $color['text'] . '; text-decoration: none; border-radius: 10px;">' . htmlspecialchars($section['text']) . '</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
    }

    private function renderImage(array $section): string
    {
        $maxWidth = $section['maxWidth'] ?? 600;
        $img = '<img src="' . htmlspecialchars($section['url']) . '" width="' . $maxWidth . '" alt="' . htmlspecialchars($section['alt'] ?? '') . '" style="width: 100%; max-width: ' . $maxWidth . 'px; height: auto; display: block; border-radius: 12px;">';

        if (!empty($section['link'])) {
            $img = '<a href="' . htmlspecialchars($section['link']) . '">' . $img . '</a>';
        }

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 15px 40px;" class="mobile-padding">
                    ' . $img . '
                </td>
            </tr>
        </table>';
    }

    private function renderDivider(array $section): string
    {
        $style = $section['style'] ?? 'solid';
        $borderStyle = $style === 'dashed' ? 'dashed' : 'solid';

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 20px 40px;" class="mobile-padding">
                    <hr style="border: 0; border-top: 1px ' . $borderStyle . ' #e5e7eb; margin: 0;">
                </td>
            </tr>
        </table>';
    }

    private function renderSpacer(array $section): string
    {
        $height = $section['height'] ?? 30;
        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="height: ' . $height . 'px; font-size: 1px; line-height: 1px;">&nbsp;</td>
            </tr>
        </table>';
    }

    private function renderFeatureGrid(array $section): string
    {
        $features = $section['features'] ?? [];
        $count = count($features);
        $width = $count === 2 ? '50%' : '33.333%';

        $html = '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 20px 30px;" class="mobile-padding">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>';

        foreach ($features as $feature) {
            $html .= '
                            <td width="' . $width . '" style="padding: 10px; text-align: center; vertical-align: top;" class="stack-column">
                                <div style="font-size: 36px; margin-bottom: 12px;">' . ($feature['icon'] ?? '✨') . '</div>
                                <h3 style="margin: 0 0 8px; font-size: 16px; font-weight: 700; color: ' . $this->textColor . ';">' . htmlspecialchars($feature['title'] ?? '') . '</h3>
                                <p style="margin: 0; font-size: 14px; line-height: 1.5; color: ' . $this->mutedColor . ';">' . htmlspecialchars($feature['text'] ?? '') . '</p>
                            </td>';
        }

        $html .= '
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        return $html;
    }

    private function renderCard(array $section): string
    {
        $image = '';
        if (!empty($section['image'])) {
            $image = '<img src="' . htmlspecialchars($section['image']) . '" width="520" style="width: 100%; max-width: 520px; height: auto; display: block; border-radius: 10px 10px 0 0;" alt="">';
        }

        $cta = '';
        if (!empty($section['ctaText']) && !empty($section['ctaUrl'])) {
            $cta = '<a href="' . htmlspecialchars($section['ctaUrl']) . '" style="display: inline-block; margin-top: 15px; padding: 10px 20px; font-size: 14px; font-weight: 600; color: #ffffff; background-color: ' . $this->brandColor . '; text-decoration: none; border-radius: 8px;">' . htmlspecialchars($section['ctaText']) . '</a>';
        }

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 15px 40px;" class="mobile-padding">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f9fafb; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">
                        <tr>
                            <td>' . $image . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 25px;">
                                <h3 style="margin: 0 0 10px; font-size: 20px; font-weight: 700; color: ' . $this->textColor . ';">' . htmlspecialchars($section['title']) . '</h3>
                                <p style="margin: 0; font-size: 15px; line-height: 1.6; color: ' . $this->mutedColor . ';">' . htmlspecialchars($section['text']) . '</p>
                                ' . $cta . '
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
    }

    private function renderItemList(array $section): string
    {
        $title = '';
        if (!empty($section['title'])) {
            $title = '<h3 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: ' . $this->textColor . ';">' . htmlspecialchars($section['title']) . '</h3>';
        }

        $items = '';
        foreach ($section['items'] ?? [] as $item) {
            $image = '';
            if (!empty($item['image'])) {
                $image = '
                    <td width="80" style="padding-right: 15px; vertical-align: top;">
                        <img src="' . htmlspecialchars($item['image']) . '" width="80" height="80" style="width: 80px; height: 80px; border-radius: 10px; object-fit: cover;" alt="">
                    </td>';
            }

            $link = !empty($item['url']) ? '<a href="' . htmlspecialchars($item['url']) . '" style="color: ' . $this->brandColor . '; text-decoration: none; font-weight: 600;">' : '';
            $linkEnd = !empty($item['url']) ? '</a>' : '';

            $items .= '
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                ' . $image . '
                                <td style="vertical-align: top;">
                                    <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: ' . $this->textColor . ';">' . $link . htmlspecialchars($item['title'] ?? '') . $linkEnd . '</p>
                                    <p style="margin: 0 0 4px; font-size: 14px; color: ' . $this->mutedColor . ';">' . htmlspecialchars($item['subtitle'] ?? '') . '</p>
                                    <p style="margin: 0; font-size: 13px; color: ' . $this->accentColor . '; font-weight: 500;">' . htmlspecialchars($item['meta'] ?? '') . '</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>';
        }

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 20px 40px;" class="mobile-padding">
                    ' . $title . '
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        ' . $items . '
                    </table>
                </td>
            </tr>
        </table>';
    }

    private function renderQuote(array $section): string
    {
        $author = '';
        if (!empty($section['author'])) {
            $role = !empty($section['role']) ? '<br><span style="font-size: 13px; color: ' . $this->mutedColor . ';">' . htmlspecialchars($section['role']) . '</span>' : '';
            $author = '<p style="margin: 15px 0 0; font-size: 14px; font-weight: 600; color: ' . $this->textColor . ';">— ' . htmlspecialchars($section['author']) . $role . '</p>';
        }

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 20px 40px;" class="mobile-padding">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 12px; border-left: 4px solid ' . $this->brandColor . ';">
                        <tr>
                            <td style="padding: 25px 30px;">
                                <p style="margin: 0; font-size: 18px; font-style: italic; line-height: 1.6; color: ' . $this->textColor . ';">"' . htmlspecialchars($section['quote']) . '"</p>
                                ' . $author . '
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
    }

    private function renderSocialLinks(array $section): string
    {
        $icons = [
            'facebook' => '📘',
            'twitter' => '🐦',
            'instagram' => '📷',
            'linkedin' => '💼',
            'youtube' => '📺',
            'tiktok' => '🎵',
            'website' => '🌐'
        ];

        $links = '';
        foreach ($section['links'] ?? [] as $platform => $url) {
            $icon = $icons[strtolower($platform)] ?? '🔗';
            $links .= '<a href="' . htmlspecialchars($url) . '" style="display: inline-block; margin: 0 8px; font-size: 24px; text-decoration: none;">' . $icon . '</a>';
        }

        return '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 20px 40px; text-align: center;" class="mobile-padding">
                    ' . $links . '
                </td>
            </tr>
        </table>';
    }

    /**
     * Parse personalization tokens in content
     */
    public static function personalizeContent(string $content, array $recipient): string
    {
        $appUrl = rtrim(\Nexus\Core\Env::get('APP_URL') ?? '', '/');
        $basePath = \Nexus\Core\TenantContext::getBasePath();

        $tokens = [
            '{{first_name}}' => $recipient['first_name'] ?? $recipient['name'] ?? 'there',
            '{{last_name}}' => $recipient['last_name'] ?? '',
            '{{full_name}}' => trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')) ?: ($recipient['name'] ?? 'Friend'),
            '{{email}}' => $recipient['email'] ?? '',
            '{{date}}' => date('F j, Y'),
            '{{year}}' => date('Y'),
            '{{month}}' => date('F'),
            '{{day}}' => date('l'),
            '{{app_url}}' => $appUrl . $basePath
        ];

        foreach ($tokens as $token => $value) {
            $content = str_replace($token, htmlspecialchars($value), $content);
        }

        return $content;
    }

    /**
     * Process dynamic content blocks in newsletter content
     * Replaces [[block:type]] markers with dynamic content
     */
    public static function processDynamicBlocks(string $content, array $options = []): string
    {
        // Process [[recent_listings:N]] block
        $content = preg_replace_callback('/\[\[recent_listings:?(\d*)\]\]/i', function($matches) use ($options) {
            $limit = !empty($matches[1]) ? (int)$matches[1] : 5;
            return self::renderRecentListingsBlock($limit, $options);
        }, $content);

        // Process [[upcoming_events:N]] block
        $content = preg_replace_callback('/\[\[upcoming_events:?(\d*)\]\]/i', function($matches) use ($options) {
            $limit = !empty($matches[1]) ? (int)$matches[1] : 5;
            return self::renderUpcomingEventsBlock($limit, $options);
        }, $content);

        // Process [[member_spotlight]] block
        $content = preg_replace_callback('/\[\[member_spotlight\]\]/i', function($matches) use ($options) {
            return self::renderMemberSpotlightBlock($options);
        }, $content);

        // Process [[community_stats]] block
        $content = preg_replace_callback('/\[\[community_stats\]\]/i', function($matches) use ($options) {
            return self::renderCommunityStatsBlock($options);
        }, $content);

        // Process [[quick_links]] block
        $content = preg_replace_callback('/\[\[quick_links\]\]/i', function($matches) use ($options) {
            return self::renderQuickLinksBlock($options);
        }, $content);

        return $content;
    }

    /**
     * Render recent listings block
     */
    private static function renderRecentListingsBlock(int $limit = 5, array $options = []): string
    {
        try {
            // Get recent listings from database
            $listings = \Nexus\Models\Listing::getRecent($limit);

            if (empty($listings)) {
                return '<p style="color: #6b7280; font-style: italic;">No recent listings available.</p>';
            }

            $basePath = TenantContext::getBasePath();
            $appUrl = rtrim(Env::get('APP_URL') ?? '', '/');

            $html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">';

            foreach ($listings as $listing) {
                $title = htmlspecialchars($listing['title'] ?? 'Untitled');
                $category = htmlspecialchars($listing['category'] ?? '');
                $url = $appUrl . $basePath . '/listings/' . $listing['id'];

                $html .= '
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="vertical-align: top;">
                                    <a href="' . $url . '" style="color: #6366f1; text-decoration: none; font-weight: 600; font-size: 16px;">' . $title . '</a>
                                    <p style="margin: 4px 0 0; font-size: 14px; color: #6b7280;">' . $category . '</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>';
            }

            $html .= '</table>';
            $html .= '<p style="text-align: center; margin-top: 15px;"><a href="' . $appUrl . $basePath . '/listings" style="color: #6366f1; text-decoration: none; font-weight: 600;">View All Listings →</a></p>';

            return $html;
        } catch (\Exception $e) {
            error_log("Error rendering recent listings block: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Render upcoming events block
     */
    private static function renderUpcomingEventsBlock(int $limit = 5, array $options = []): string
    {
        try {
            // Try to get upcoming events if Events model exists
            if (!class_exists('\Nexus\Models\Event')) {
                return '<p style="color: #6b7280; font-style: italic;">Events feature not available.</p>';
            }

            $events = \Nexus\Models\Event::getUpcoming($limit);

            if (empty($events)) {
                return '<p style="color: #6b7280; font-style: italic;">No upcoming events.</p>';
            }

            $basePath = TenantContext::getBasePath();
            $appUrl = rtrim(Env::get('APP_URL') ?? '', '/');

            $html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">';

            foreach ($events as $event) {
                $title = htmlspecialchars($event['title'] ?? 'Untitled Event');
                $date = !empty($event['start_date']) ? date('M j, Y', strtotime($event['start_date'])) : '';
                $url = $appUrl . $basePath . '/events/' . $event['id'];

                $html .= '
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="60" style="vertical-align: top; padding-right: 15px;">
                                    <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; text-align: center; padding: 8px; border-radius: 8px;">
                                        <div style="font-size: 18px; font-weight: 700;">' . date('j', strtotime($event['start_date'] ?? 'now')) . '</div>
                                        <div style="font-size: 11px; text-transform: uppercase;">' . date('M', strtotime($event['start_date'] ?? 'now')) . '</div>
                                    </div>
                                </td>
                                <td style="vertical-align: top;">
                                    <a href="' . $url . '" style="color: #6366f1; text-decoration: none; font-weight: 600; font-size: 16px;">' . $title . '</a>
                                    <p style="margin: 4px 0 0; font-size: 13px; color: #f59e0b; font-weight: 500;">' . $date . '</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>';
            }

            $html .= '</table>';
            $html .= '<p style="text-align: center; margin-top: 15px;"><a href="' . $appUrl . $basePath . '/events" style="color: #6366f1; text-decoration: none; font-weight: 600;">View All Events →</a></p>';

            return $html;
        } catch (\Exception $e) {
            error_log("Error rendering upcoming events block: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Render member spotlight block (random featured member)
     */
    private static function renderMemberSpotlightBlock(array $options = []): string
    {
        try {
            // Get a random active member
            $sql = "SELECT id, first_name, last_name, bio, profile_image
                    FROM users
                    WHERE tenant_id = ? AND is_approved = 1 AND bio IS NOT NULL AND bio != ''
                    ORDER BY RAND()
                    LIMIT 1";

            $tenantId = TenantContext::getId();
            $member = \Nexus\Core\Database::query($sql, [$tenantId])->fetch();

            if (!$member) {
                return '';
            }

            $basePath = TenantContext::getBasePath();
            $appUrl = rtrim(Env::get('APP_URL') ?? '', '/');

            $name = htmlspecialchars(trim($member['first_name'] . ' ' . $member['last_name']));
            $bio = htmlspecialchars(substr($member['bio'] ?? '', 0, 150)) . (strlen($member['bio'] ?? '') > 150 ? '...' : '');
            $profileUrl = $appUrl . $basePath . '/members/' . $member['id'];

            $avatar = '';
            if (!empty($member['profile_image'])) {
                $avatar = '<img src="' . htmlspecialchars($member['profile_image']) . '" width="80" height="80" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-right: 20px;" alt="">';
            } else {
                $initials = strtoupper(substr($member['first_name'] ?? 'M', 0, 1));
                $avatar = '<div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; font-size: 32px; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-right: 20px;">' . $initials . '</div>';
            }

            return '
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 12px; margin: 20px 0;">
                <tr>
                    <td style="padding: 25px;">
                        <p style="margin: 0 0 15px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #6366f1; font-weight: 600;">✨ Member Spotlight</p>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="vertical-align: top; width: 100px;">
                                    ' . $avatar . '
                                </td>
                                <td style="vertical-align: top;">
                                    <h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #1f2937;">' . $name . '</h3>
                                    <p style="margin: 0 0 12px; font-size: 14px; color: #6b7280; line-height: 1.5;">' . $bio . '</p>
                                    <a href="' . $profileUrl . '" style="color: #6366f1; text-decoration: none; font-weight: 600; font-size: 14px;">View Profile →</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
        } catch (\Exception $e) {
            error_log("Error rendering member spotlight block: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Render community stats block
     */
    private static function renderCommunityStatsBlock(array $options = []): string
    {
        try {
            $tenantId = TenantContext::getId();

            // Get member count
            $memberCount = \Nexus\Core\Database::query("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND is_approved = 1", [$tenantId])->fetch()['count'] ?? 0;

            // Get listing count (if table exists)
            $listingCount = 0;
            try {
                $listingCount = \Nexus\Core\Database::query("SELECT COUNT(*) as count FROM listings WHERE tenant_id = ?", [$tenantId])->fetch()['count'] ?? 0;
            } catch (\Exception $e) {
                // Table may not exist
            }

            // Get transaction count (exchanges)
            $exchangeCount = 0;
            try {
                $exchangeCount = \Nexus\Core\Database::query("SELECT COUNT(*) as count FROM transactions WHERE tenant_id = ?", [$tenantId])->fetch()['count'] ?? 0;
            } catch (\Exception $e) {
                // Table may not exist
            }

            return '
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
                <tr>
                    <td style="text-align: center; padding: 10px; width: 33%;">
                        <div style="font-size: 32px; font-weight: 700; color: #6366f1;">' . number_format($memberCount) . '</div>
                        <div style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Members</div>
                    </td>
                    <td style="text-align: center; padding: 10px; width: 33%; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                        <div style="font-size: 32px; font-weight: 700; color: #f59e0b;">' . number_format($listingCount) . '</div>
                        <div style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Listings</div>
                    </td>
                    <td style="text-align: center; padding: 10px; width: 33%;">
                        <div style="font-size: 32px; font-weight: 700; color: #10b981;">' . number_format($exchangeCount) . '</div>
                        <div style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Exchanges</div>
                    </td>
                </tr>
            </table>';
        } catch (\Exception $e) {
            error_log("Error rendering community stats block: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Render quick links block
     */
    private static function renderQuickLinksBlock(array $options = []): string
    {
        $basePath = TenantContext::getBasePath();
        $appUrl = rtrim(Env::get('APP_URL') ?? '', '/');

        $links = [
            ['title' => 'Browse Listings', 'url' => $appUrl . $basePath . '/listings', 'icon' => '🔍'],
            ['title' => 'View Members', 'url' => $appUrl . $basePath . '/members', 'icon' => '👥'],
            ['title' => 'My Profile', 'url' => $appUrl . $basePath . '/profile', 'icon' => '👤'],
            ['title' => 'Settings', 'url' => $appUrl . $basePath . '/settings', 'icon' => '⚙️'],
        ];

        $html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">';

        foreach ($links as $link) {
            $html .= '
            <tr>
                <td style="padding: 8px 0;">
                    <a href="' . htmlspecialchars($link['url']) . '" style="text-decoration: none; display: flex; align-items: center; color: #374151;">
                        <span style="font-size: 18px; margin-right: 12px;">' . $link['icon'] . '</span>
                        <span style="font-size: 15px; font-weight: 500;">' . htmlspecialchars($link['title']) . '</span>
                    </a>
                </td>
            </tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Get available dynamic content blocks for documentation
     */
    public static function getAvailableBlocks(): array
    {
        return [
            [
                'name' => 'recent_listings',
                'syntax' => '[[recent_listings:5]]',
                'description' => 'Shows recent listings (number is optional, defaults to 5)'
            ],
            [
                'name' => 'upcoming_events',
                'syntax' => '[[upcoming_events:5]]',
                'description' => 'Shows upcoming events (number is optional, defaults to 5)'
            ],
            [
                'name' => 'member_spotlight',
                'syntax' => '[[member_spotlight]]',
                'description' => 'Shows a random featured member with bio'
            ],
            [
                'name' => 'community_stats',
                'syntax' => '[[community_stats]]',
                'description' => 'Shows member, listing, and exchange counts'
            ],
            [
                'name' => 'quick_links',
                'syntax' => '[[quick_links]]',
                'description' => 'Shows quick navigation links'
            ]
        ];
    }
}
