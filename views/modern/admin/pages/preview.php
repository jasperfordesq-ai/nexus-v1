<?php
/**
 * Page Preview Template
 * Shows a page preview with header banner indicating preview mode
 * Path: views/modern/admin-legacy/pages/preview.php
 */

use Nexus\Core\HtmlSanitizer;

$pageTitle = $page['title'] ?? 'Page Preview';
$pageContent = $page['content'] ?? '';

// Sanitize content
if (class_exists('Nexus\\Core\\HtmlSanitizer')) {
    $pageContent = HtmlSanitizer::sanitize($pageContent);
}

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }

        /* Preview Banner */
        .preview-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .preview-banner-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .preview-banner i {
            font-size: 1.2rem;
        }

        .preview-banner-title {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .preview-banner-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .preview-banner-actions {
            display: flex;
            gap: 10px;
        }

        .preview-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .preview-btn-edit {
            background: white;
            color: #d97706;
        }

        .preview-btn-edit:hover {
            background: #fef3c7;
        }

        .preview-btn-close {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .preview-btn-close:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Page status badges */
        .preview-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 15px;
        }

        .status-draft {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .status-published {
            background: #22c55e;
            color: white;
        }

        .status-scheduled {
            background: #3b82f6;
            color: white;
        }

        /* Preview Frame */
        .preview-frame {
            margin-top: 60px;
            background: white;
            min-height: calc(100vh - 60px);
        }

        /* Page Content Container */
        .preview-content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 24px;
        }

        .preview-page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .preview-page-content {
            color: #334155;
            line-height: 1.7;
        }

        /* Content styling - supports GrapesJS output */
        .preview-page-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .preview-page-content h1,
        .preview-page-content h2,
        .preview-page-content h3,
        .preview-page-content h4,
        .preview-page-content h5,
        .preview-page-content h6 {
            color: #1e293b;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
        }

        .preview-page-content p {
            margin-bottom: 1rem;
        }

        .preview-page-content a {
            color: #6366f1;
            text-decoration: none;
        }

        .preview-page-content a:hover {
            text-decoration: underline;
        }

        .preview-page-content ul,
        .preview-page-content ol {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        .preview-page-content li {
            margin-bottom: 0.5rem;
        }

        .preview-page-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .preview-page-content th,
        .preview-page-content td {
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        .preview-page-content th {
            background: #f1f5f9;
            font-weight: 600;
        }

        .preview-page-content blockquote {
            border-left: 4px solid #6366f1;
            margin: 1.5rem 0;
            padding: 1rem 1.5rem;
            background: rgba(99, 102, 241, 0.05);
            border-radius: 0 8px 8px 0;
        }

        .preview-page-content pre,
        .preview-page-content code {
            font-family: 'Fira Code', 'Monaco', monospace;
            background: #f1f5f9;
            border-radius: 6px;
        }

        .preview-page-content code {
            padding: 2px 6px;
            font-size: 0.9em;
        }

        .preview-page-content pre {
            padding: 1rem;
            overflow-x: auto;
        }

        /* Smart Block placeholders in preview */
        .preview-page-content [data-smart-type] {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px dashed #0ea5e9;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            margin: 20px 0;
        }

        .preview-page-content [data-smart-type]::before {
            content: "Smart Block: " attr(data-smart-type);
            display: block;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 8px;
        }

        .preview-page-content [data-smart-type]::after {
            content: "This block will display dynamic content when published";
            display: block;
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .preview-banner {
                flex-direction: column;
                gap: 12px;
                padding: 12px 15px;
            }

            .preview-banner-actions {
                width: 100%;
                justify-content: center;
            }

            .preview-frame {
                margin-top: 110px;
            }

            .preview-content-wrapper {
                padding: 30px 16px;
            }

            .preview-page-title {
                font-size: 1.8rem;
            }

            .preview-status {
                margin-left: 0;
                margin-top: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Preview Banner -->
    <div class="preview-banner">
        <div class="preview-banner-left">
            <i class="fas fa-eye"></i>
            <div>
                <div class="preview-banner-title">Preview Mode</div>
                <div class="preview-banner-subtitle"><?= htmlspecialchars($pageTitle) ?></div>
            </div>
            <?php
            $isPublished = !empty($page['is_published']);
            $isScheduled = !empty($page['publish_at']) && strtotime($page['publish_at']) > time();
            ?>
            <?php if ($isScheduled): ?>
                <span class="preview-status status-scheduled">
                    <i class="fas fa-clock"></i> Scheduled
                </span>
            <?php elseif ($isPublished): ?>
                <span class="preview-status status-published">
                    <i class="fas fa-check"></i> Published
                </span>
            <?php else: ?>
                <span class="preview-status status-draft">
                    <i class="fas fa-file-alt"></i> Draft
                </span>
            <?php endif; ?>
        </div>
        <div class="preview-banner-actions">
            <a href="<?= $basePath ?>/admin-legacy/pages/builder/<?= $page['id'] ?>" class="preview-btn preview-btn-edit">
                <i class="fas fa-edit"></i> Edit Page
            </a>
            <a href="<?= $basePath ?>/admin-legacy/pages" class="preview-btn preview-btn-close">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>

    <!-- Preview Frame -->
    <div class="preview-frame">
        <div class="preview-content-wrapper">
            <h1 class="preview-page-title"><?= htmlspecialchars($pageTitle) ?></h1>
            <div class="preview-page-content">
                <?= $pageContent ?>
            </div>
        </div>
    </div>
</body>
</html>
