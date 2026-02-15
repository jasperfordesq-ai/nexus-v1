<?php
/**
 * Newsletter Create/Edit Form - Gold Standard Admin UI
 * Holographic Glassmorphism Dark Theme
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$isEdit = isset($newsletter);

$action = $isEdit
    ? $basePath . "/admin-legacy/newsletters/update/" . $newsletter['id']
    : $basePath . "/admin-legacy/newsletters/store";

$eligibleCount = $eligibleCount ?? 0;
$segments = $segments ?? [];
$groups = $groups ?? [];
$counties = $counties ?? \Nexus\Models\NewsletterSegment::getIrishCounties();
$towns = $towns ?? \Nexus\Models\NewsletterSegment::getIrishTowns();
$audienceCounts = $audienceCounts ?? ['all_members' => 0, 'subscribers_only' => 0, 'both' => 0];
$savedTemplates = $savedTemplates ?? \Nexus\Models\NewsletterTemplate::getAll(true, true);

// Admin page configuration
$adminPageTitle = $isEdit ? 'Edit Newsletter' : 'Create Newsletter';
$adminPageSubtitle = $isEdit ? 'Update your campaign' : 'Compose a new email campaign';
$adminPageIcon = 'fa-solid fa-envelope-open-text';

// Get TinyMCE API key from environment or .env file
$tinymceApiKey = getenv('TINYMCE_API_KEY') ?: 'no-api-key';
if ($tinymceApiKey === 'no-api-key') {
    $envPath = dirname(__DIR__, 4) . '/.env';
    if (file_exists($envPath)) {
        $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            if (strpos($line, 'TINYMCE_API_KEY=') === 0) {
                $tinymceApiKey = trim(substr($line, 16), '"\'');
                break;
            }
        }
    }
}

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- TinyMCE Rich Text Editor -->
<script src="https://cdn.tiny.cloud/1/<?= htmlspecialchars($tinymceApiKey) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<style>
    .form-wrapper {
        padding: 0 40px 60px;
        position: relative;
        z-index: 10;
    }

    .form-container {
        max-width: 900px;
        margin: 0 auto;
    }

    /* Back link */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.9rem;
        margin-bottom: 24px;
        padding: 8px 16px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #a5b4fc;
    }

    /* Flash messages */
    .flash-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.15) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #6ee7b7;
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .flash-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.15) 100%);
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #fca5a5;
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .flash-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .flash-icon.success { background: #10b981; }
    .flash-icon.error { background: #ef4444; }

    /* Glass Card */
    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(20px);
        margin-bottom: 24px;
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);
        padding: 20px 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-header-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .card-header-icon.amber { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }
    .card-header-icon.purple { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
    .card-header-icon.green { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }

    .card-title {
        margin: 0;
        font-size: 1.1rem;
        color: #ffffff;
        font-weight: 700;
    }

    .card-body {
        padding: 30px;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 24px;
    }

    .form-label {
        display: block;
        margin-bottom: 10px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
    }

    .form-label-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .form-input {
        width: 100%;
        padding: 14px 16px;
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        color: #ffffff;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: rgba(99, 102, 241, 0.5);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    .form-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .form-input.large {
        font-size: 1.1rem;
    }

    .form-hint {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        margin-top: 8px;
    }

    /* AI Generate Button */
    .btn-ai {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        border: none;
        padding: 8px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
    }

    .btn-ai:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
    }

    .btn-ai.amber {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    /* Subject Counter */
    .subject-counter {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .quality-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    /* AI Suggestions */
    .ai-suggestions {
        display: none;
        margin-top: 12px;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 10px;
        padding: 15px;
    }

    /* Toggle Checkbox */
    .toggle-box {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        padding: 16px 20px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .toggle-box:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .toggle-box input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: #6366f1;
    }

    .toggle-label {
        font-weight: 600;
        color: #ffffff;
    }

    .toggle-desc {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        margin-top: 2px;
    }

    /* A/B Test Panel */
    .ab-panel {
        background: linear-gradient(135deg, rgba(251, 191, 36, 0.15) 0%, rgba(245, 158, 11, 0.1) 100%);
        border: 1px solid rgba(251, 191, 36, 0.3);
        padding: 25px;
        border-radius: 12px;
        margin-top: 20px;
    }

    .ab-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .ab-variant-label {
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .ab-variant-label.a { color: #a5b4fc; }
    .ab-variant-label.b { color: #fcd34d; }

    .ab-hint {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.8rem;
        margin-top: 4px;
    }

    /* Audience Options */
    .audience-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }

    .audience-option {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: rgba(255, 255, 255, 0.03);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .audience-option:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .audience-option.selected {
        border-color: rgba(99, 102, 241, 0.5);
        background: rgba(99, 102, 241, 0.1);
    }

    .audience-option input[type="radio"] {
        margin-top: 2px;
        accent-color: #6366f1;
    }

    .audience-name {
        font-weight: 600;
        color: #ffffff;
    }

    .audience-count {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Targeting Section */
    .targeting-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .targeting-info h4 {
        margin: 0;
        color: #ffffff;
        font-size: 1rem;
    }

    .targeting-info p {
        margin: 4px 0 0;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .btn-toggle {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        padding: 10px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .targeting-content {
        display: none;
    }

    .targeting-content.visible {
        display: block;
    }

    /* Filter Box */
    .filter-box {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .filter-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        margin-bottom: 12px;
        color: rgba(255, 255, 255, 0.9);
    }

    .filter-label i.green { color: #6ee7b7; }
    .filter-label i.purple { color: #c4b5fd; }
    .filter-label i.amber { color: #fcd34d; }
    .filter-label i.blue { color: #93c5fd; }

    .filter-search {
        width: 100%;
        padding: 10px 14px;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #ffffff;
        margin-bottom: 12px;
    }

    .filter-search::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .filter-grid {
        max-height: 180px;
        overflow-y: auto;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 8px;
    }

    .filter-option {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 6px;
        transition: background 0.2s;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
    }

    .filter-option:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .filter-option input {
        accent-color: #6366f1;
    }

    /* Group Options */
    .group-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }

    .group-option {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 12px 14px;
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .group-option:hover {
        background: rgba(245, 158, 11, 0.15);
    }

    .group-name {
        font-weight: 500;
        color: #ffffff;
    }

    .group-count {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Scheduling */
    .schedule-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 20px;
    }

    .schedule-input-wrapper {
        flex: 1;
        min-width: 280px;
    }

    .schedule-tips {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 12px;
        padding: 15px 20px;
        min-width: 280px;
    }

    .tips-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: #6ee7b7;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .time-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .time-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        padding: 8px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
        color: #6ee7b7;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
    }

    .time-btn:hover {
        background: rgba(16, 185, 129, 0.2);
    }

    .time-btn i { color: #fcd34d; }

    /* Recurring Panel */
    .recurring-panel {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.1) 100%);
        border: 1px solid rgba(59, 130, 246, 0.3);
        padding: 25px;
        border-radius: 12px;
        margin-top: 15px;
    }

    .recurring-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .recurring-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #93c5fd;
    }

    .day-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .day-btn {
        padding: 8px 14px;
        border-radius: 8px;
        cursor: pointer;
        border: 1px solid rgba(59, 130, 246, 0.3);
        background: rgba(255, 255, 255, 0.05);
        color: rgba(255, 255, 255, 0.7);
        transition: all 0.3s ease;
    }

    .day-btn.active {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border-color: transparent;
    }

    .recurring-preview {
        background: rgba(0, 0, 0, 0.2);
        padding: 15px 20px;
        border-radius: 10px;
        margin-top: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #93c5fd;
    }

    /* Template Buttons */
    .template-grid {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .template-btn {
        padding: 12px 20px;
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.03);
        cursor: pointer;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .template-btn:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .template-btn.active {
        border-color: rgba(99, 102, 241, 0.5);
        background: rgba(99, 102, 241, 0.15);
        color: #a5b4fc;
    }

    /* Quick Insert Toolbar */
    .insert-toolbar {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-bottom: none;
        border-radius: 10px 10px 0 0;
        padding: 12px 15px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .toolbar-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        font-weight: 500;
        margin-right: 4px;
    }

    .insert-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.7);
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
    }

    .insert-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }

    .insert-btn i.purple { color: #a5b4fc; }
    .insert-btn i.green { color: #6ee7b7; }
    .insert-btn i.amber { color: #fcd34d; }
    .insert-btn i.pink { color: #f9a8d4; }
    .insert-btn i.blue { color: #93c5fd; }
    .insert-btn i.gray { color: #94a3b8; }

    .insert-btn.dynamic {
        background: rgba(16, 185, 129, 0.15);
        border-color: rgba(16, 185, 129, 0.3);
        color: #6ee7b7;
    }

    .toolbar-divider {
        width: 1px;
        height: 20px;
        background: rgba(255, 255, 255, 0.15);
        margin: 0 4px;
    }

    /* Preview Panel */
    .preview-panel {
        display: none;
        margin-top: 20px;
    }

    .preview-container {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        overflow: hidden;
    }

    .preview-header {
        background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
        padding: 12px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .preview-dots {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .preview-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }

    .preview-dot.red { background: #ef4444; }
    .preview-dot.yellow { background: #f59e0b; }
    .preview-dot.green { background: #10b981; }

    .preview-title {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        margin-left: 10px;
    }

    .preview-mode-btns {
        display: flex;
        gap: 6px;
    }

    .preview-mode-btn {
        background: transparent;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .preview-mode-btn.active {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }

    .preview-clients {
        background: #1f2937;
        padding: 8px 20px;
        display: flex;
        gap: 6px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .preview-client-btn {
        background: transparent;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.75rem;
        transition: all 0.2s;
    }

    .preview-client-btn.active {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }

    .preview-frame-container {
        background: #374151;
        padding: 20px;
        display: flex;
        justify-content: center;
    }

    .preview-frame {
        width: 100%;
        max-width: 620px;
        height: 500px;
        border: none;
        background: white;
        border-radius: 8px;
    }

    /* Tips Boxes */
    .tips-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .tips-box {
        padding: 20px;
        border-radius: 12px;
        font-size: 0.9rem;
    }

    .tips-box.gray {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.6);
    }

    .tips-box.blue {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.1) 100%);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #93c5fd;
    }

    .tips-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        margin-bottom: 12px;
        color: rgba(255, 255, 255, 0.8);
    }

    .tips-title i.amber { color: #fcd34d; }
    .tips-title i.blue { color: #93c5fd; }

    .tips-box ul {
        margin: 0;
        padding-left: 20px;
        line-height: 1.8;
    }

    .tips-box code {
        background: rgba(0, 0, 0, 0.3);
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.85rem;
    }

    /* Action Buttons */
    .actions-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .btn-delete {
        background: none;
        border: none;
        color: #fca5a5;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: color 0.2s;
    }

    .btn-delete:hover {
        color: #f87171;
    }

    .actions-buttons {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.7);
        padding: 12px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }

    .btn-primary {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
    }

    .btn-send {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-send:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
    }

    .recipient-info {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.5);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .recipient-info i { color: #a5b4fc; }
    .recipient-info strong { color: #ffffff; margin: 0 4px; }

    /* Send Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 35px;
        border-radius: 20px;
        max-width: 480px;
        width: 90%;
    }

    .modal-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .modal-icon i {
        font-size: 1.5rem;
        color: white;
    }

    .modal-title {
        margin: 0 0 10px;
        font-size: 1.3rem;
        color: #ffffff;
        text-align: center;
    }

    .modal-text {
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 20px;
        text-align: center;
        line-height: 1.6;
    }

    .modal-text strong { color: #ffffff; }
    .modal-text .count { color: #6ee7b7; }

    .modal-warning {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
    }

    .modal-info {
        background: rgba(234, 179, 8, 0.15);
        border: 1px solid rgba(234, 179, 8, 0.3);
        color: #fde047;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
    }

    .modal-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    /* TinyMCE Dark Theme Override */
    .tox-tinymce {
        border-radius: 0 0 10px 10px !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }

    @media (max-width: 768px) {
        .form-wrapper {
            padding: 0 20px 40px;
        }

        .card-body {
            padding: 20px;
        }

        .ab-grid,
        .recurring-grid,
        .tips-grid {
            grid-template-columns: 1fr;
        }

        .schedule-row {
            flex-direction: column;
        }

        .actions-row {
            flex-direction: column;
            align-items: stretch;
        }

        .actions-buttons {
            flex-direction: column;
        }
    }
</style>

<div class="form-wrapper">
    <div class="form-container">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
        </a>

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="flash-success">
                <div class="flash-icon success"><i class="fa-solid fa-check" style="color: white;"></i></div>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="flash-error">
                <div class="flash-icon error"><i class="fa-solid fa-xmark" style="color: white;"></i></div>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <form action="<?= $action ?>" method="POST" id="newsletter-form">
            <?= Csrf::input() ?>

            <!-- Campaign Details Card -->
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-header-icon amber"><i class="fa-solid fa-envelope"></i></div>
                    <h3 class="card-title">Campaign Details</h3>
                </div>

                <div class="card-body">
                    <!-- Subject Line -->
                    <div class="form-group">
                        <div class="form-label-row">
                            <label class="form-label" style="margin: 0;">Subject Line *</label>
                            <button type="button" onclick="generateAISubject()" class="btn-ai">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate with AI
                            </button>
                        </div>
                        <input type="text" name="subject" id="subject-input" required
                            value="<?= $isEdit ? htmlspecialchars($newsletter['subject']) : '' ?>"
                            class="form-input large"
                            placeholder="Write an engaging subject line..."
                            maxlength="150"
                            oninput="updateSubjectCounter()">
                        <div id="ai-subject-suggestions" class="ai-suggestions"></div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                            <span class="form-hint">This is what recipients will see in their inbox. Keep it compelling!</span>
                            <div class="subject-counter">
                                <span id="subject-length">0</span>/150
                                <span id="subject-quality" class="quality-badge"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Text -->
                    <div class="form-group">
                        <div class="form-label-row">
                            <label class="form-label" style="margin: 0;">Preview Text</label>
                            <button type="button" onclick="generateAIPreview()" class="btn-ai">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                            </button>
                        </div>
                        <input type="text" name="preview_text" id="preview-text-input"
                            value="<?= $isEdit ? htmlspecialchars($newsletter['preview_text'] ?? '') : '' ?>"
                            class="form-input"
                            placeholder="Brief preview shown after subject..." maxlength="255">
                        <span class="form-hint">Optional. Appears after the subject line in most email clients.</span>
                    </div>

                    <!-- A/B Testing Toggle -->
                    <div style="padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <label class="toggle-box">
                            <input type="checkbox" name="ab_test_enabled" id="ab-test-toggle" value="1"
                                <?= ($isEdit && !empty($newsletter['ab_test_enabled'])) ? 'checked' : '' ?>>
                            <div>
                                <span class="toggle-label">Enable A/B Testing</span>
                                <span class="toggle-desc">Test two different subject lines to see which performs better</span>
                            </div>
                        </label>
                    </div>

                    <!-- A/B Test Settings -->
                    <div id="ab-test-settings" class="ab-panel" style="display: <?= ($isEdit && !empty($newsletter['ab_test_enabled'])) ? 'block' : 'none' ?>;">
                        <div class="ab-grid" style="margin-bottom: 20px;">
                            <div>
                                <label class="ab-variant-label a">
                                    <i class="fa-solid fa-a"></i> Subject A (Original)
                                </label>
                                <input type="text" id="subject-a-display" disabled
                                    class="form-input"
                                    style="background: rgba(0,0,0,0.3); color: rgba(255,255,255,0.5);"
                                    placeholder="Enter subject line above...">
                                <div class="ab-hint">Uses the main subject line</div>
                            </div>
                            <div>
                                <div class="form-label-row" style="margin-bottom: 8px;">
                                    <label class="ab-variant-label b" style="margin: 0;">
                                        <i class="fa-solid fa-b"></i> Subject B (Variant)
                                    </label>
                                    <button type="button" onclick="generateABVariant()" class="btn-ai amber" style="padding: 4px 10px; font-size: 0.75rem;">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Variant
                                    </button>
                                </div>
                                <input type="text" name="subject_b" id="subject-b"
                                    value="<?= $isEdit ? htmlspecialchars($newsletter['subject_b'] ?? '') : '' ?>"
                                    class="form-input"
                                    placeholder="Alternative subject line...">
                                <div class="ab-hint">Test a different approach</div>
                            </div>
                        </div>

                        <div class="ab-grid">
                            <div>
                                <label class="ab-variant-label" style="color: rgba(255,255,255,0.7);">Audience Split</label>
                                <select name="ab_split_percentage" class="form-input">
                                    <option value="50" <?= ($isEdit && ($newsletter['ab_split_percentage'] ?? 50) == 50) ? 'selected' : '' ?>>50% / 50% (Equal)</option>
                                    <option value="30" <?= ($isEdit && ($newsletter['ab_split_percentage'] ?? 50) == 30) ? 'selected' : '' ?>>30% / 70%</option>
                                    <option value="20" <?= ($isEdit && ($newsletter['ab_split_percentage'] ?? 50) == 20) ? 'selected' : '' ?>>20% / 80%</option>
                                </select>
                            </div>
                            <div>
                                <label class="ab-variant-label" style="color: rgba(255,255,255,0.7);">Winner Metric</label>
                                <select name="ab_winner_metric" class="form-input">
                                    <option value="opens" <?= ($isEdit && ($newsletter['ab_winner_metric'] ?? 'opens') == 'opens') ? 'selected' : '' ?>>Open Rate</option>
                                    <option value="clicks" <?= ($isEdit && ($newsletter['ab_winner_metric'] ?? 'opens') == 'clicks') ? 'selected' : '' ?>>Click Rate</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audience & Targeting Card -->
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-header-icon purple"><i class="fa-solid fa-users"></i></div>
                    <h3 class="card-title">Audience & Targeting</h3>
                </div>

                <div class="card-body">
                    <?php $currentAudience = $isEdit ? ($newsletter['target_audience'] ?? 'all_members') : 'all_members'; ?>

                    <div class="form-group">
                        <label class="form-label">Base Audience *</label>
                        <div class="audience-grid">
                            <label class="audience-option <?= $currentAudience === 'all_members' ? 'selected' : '' ?>">
                                <input type="radio" name="target_audience" value="all_members" <?= $currentAudience === 'all_members' ? 'checked' : '' ?>>
                                <div>
                                    <div class="audience-name">All Members</div>
                                    <div class="audience-count"><?= number_format($audienceCounts['all_members']) ?> approved members</div>
                                </div>
                            </label>

                            <label class="audience-option <?= $currentAudience === 'subscribers_only' ? 'selected' : '' ?>">
                                <input type="radio" name="target_audience" value="subscribers_only" <?= $currentAudience === 'subscribers_only' ? 'checked' : '' ?>>
                                <div>
                                    <div class="audience-name">Subscribers Only</div>
                                    <div class="audience-count"><?= number_format($audienceCounts['subscribers_only']) ?> email subscribers</div>
                                </div>
                            </label>

                            <label class="audience-option <?= $currentAudience === 'both' ? 'selected' : '' ?>">
                                <input type="radio" name="target_audience" value="both" <?= $currentAudience === 'both' ? 'checked' : '' ?>>
                                <div>
                                    <div class="audience-name">Members + Subscribers</div>
                                    <div class="audience-count"><?= number_format($audienceCounts['both']) ?> combined</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Advanced Targeting -->
                    <div style="padding-top: 25px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <div class="targeting-toggle">
                            <div class="targeting-info">
                                <h4>Advanced Targeting</h4>
                                <p>Filter your audience by location or group membership</p>
                            </div>
                            <button type="button" id="toggle-targeting" onclick="toggleTargeting()" class="btn-toggle">
                                <i class="fa-solid fa-plus" id="targeting-icon"></i>
                                <span id="targeting-btn-text">Add Filters</span>
                            </button>
                        </div>

                        <div id="targeting-section" class="targeting-content">
                            <!-- Saved Segments -->
                            <?php if (!empty($segments)): ?>
                            <div class="filter-box">
                                <label class="filter-label">
                                    <i class="fa-solid fa-filter blue"></i>
                                    Use Saved Segment
                                </label>
                                <select name="segment_id" id="segment-select" class="form-input">
                                    <option value="">-- Select a segment or use filters below --</option>
                                    <?php foreach ($segments as $segment): ?>
                                        <option value="<?= $segment['id'] ?>"
                                            <?= ($isEdit && !empty($newsletter['segment_id']) && $newsletter['segment_id'] == $segment['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($segment['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <!-- County Targeting -->
                            <div class="filter-box">
                                <label class="filter-label">
                                    <i class="fa-solid fa-map green"></i>
                                    Target by County
                                </label>
                                <input type="text" id="county-search" placeholder="Search counties..." class="filter-search" onkeyup="filterCounties(this.value)">
                                <div class="filter-grid">
                                    <?php
                                    $selectedCounties = [];
                                    if ($isEdit && !empty($newsletter['target_counties'])) {
                                        $selectedCounties = json_decode($newsletter['target_counties'], true) ?? [];
                                    }
                                    foreach ($counties as $county):
                                    ?>
                                        <label class="filter-option county-option" data-county="<?= strtolower($county) ?>">
                                            <input type="checkbox" name="target_counties[]" value="<?= htmlspecialchars($county) ?>"
                                                <?= in_array($county, $selectedCounties) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($county) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <span class="form-hint">Select counties to target. Leave empty to send to all locations.</span>
                            </div>

                            <!-- Town Targeting -->
                            <div class="filter-box">
                                <label class="filter-label">
                                    <i class="fa-solid fa-city purple"></i>
                                    Target by Town/City
                                </label>
                                <input type="text" id="town-search" placeholder="Search towns..." class="filter-search" onkeyup="filterTowns(this.value)">
                                <div class="filter-grid">
                                    <?php
                                    $selectedTowns = [];
                                    if ($isEdit && !empty($newsletter['target_towns'])) {
                                        $selectedTowns = json_decode($newsletter['target_towns'], true) ?? [];
                                    }
                                    foreach ($towns as $town):
                                    ?>
                                        <label class="filter-option town-option" data-town="<?= strtolower($town) ?>">
                                            <input type="checkbox" name="target_towns[]" value="<?= htmlspecialchars($town) ?>"
                                                <?= in_array($town, $selectedTowns) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($town) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.1);">
                                    <input type="text" name="custom_towns" placeholder="Or enter other towns (comma separated)..."
                                        value="<?= $isEdit ? htmlspecialchars($newsletter['custom_towns'] ?? '') : '' ?>"
                                        class="form-input">
                                </div>
                            </div>

                            <!-- Group Targeting -->
                            <?php if (!empty($groups)): ?>
                            <div class="filter-box">
                                <label class="filter-label">
                                    <i class="fa-solid fa-user-group amber"></i>
                                    Target by Group Membership
                                </label>
                                <input type="text" id="group-search" placeholder="Search groups..." class="filter-search" onkeyup="filterGroups(this.value)">
                                <div class="group-grid">
                                    <?php
                                    $selectedGroups = [];
                                    if ($isEdit && !empty($newsletter['target_groups'])) {
                                        $selectedGroups = json_decode($newsletter['target_groups'], true) ?? [];
                                    }
                                    foreach ($groups as $group):
                                    ?>
                                        <label class="group-option" data-group="<?= strtolower(htmlspecialchars($group['name'])) ?>">
                                            <input type="checkbox" name="target_groups[]" value="<?= $group['id'] ?>"
                                                <?= in_array($group['id'], $selectedGroups) ? 'checked' : '' ?>
                                                style="accent-color: #f59e0b;">
                                            <div>
                                                <span class="group-name"><?= htmlspecialchars($group['name']) ?></span>
                                                <span class="group-count"><?= $group['member_count'] ?? 0 ?> members</span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Scheduling -->
                    <div style="padding-top: 25px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <div class="schedule-row">
                            <div class="schedule-input-wrapper">
                                <label class="form-label">
                                    <i class="fa-solid fa-clock" style="color: #fcd34d; margin-right: 6px;"></i>
                                    Schedule for Later (Optional)
                                </label>
                                <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                                    value="<?= ($isEdit && !empty($newsletter['scheduled_at'])) ? date('Y-m-d\TH:i', strtotime($newsletter['scheduled_at'])) : '' ?>"
                                    class="form-input" style="max-width: 300px;"
                                    min="<?= date('Y-m-d\TH:i') ?>">
                                <span class="form-hint">Leave empty to save as draft or send immediately.</span>
                            </div>

                            <div class="schedule-tips">
                                <div class="tips-header">
                                    <i class="fa-solid fa-lightbulb"></i>
                                    Best Times to Send
                                </div>
                                <div class="time-buttons" id="recommended-times">
                                    <button type="button" onclick="applyRecommendedTime(9, 0)" class="time-btn">
                                        <i class="fa-solid fa-sun"></i> 9:00 AM
                                    </button>
                                    <button type="button" onclick="applyRecommendedTime(10, 0)" class="time-btn">
                                        10:00 AM
                                    </button>
                                    <button type="button" onclick="applyRecommendedTime(14, 0)" class="time-btn">
                                        <i class="fa-solid fa-cloud-sun"></i> 2:00 PM
                                    </button>
                                </div>
                                <div style="color: rgba(255,255,255,0.5); font-size: 0.75rem; margin-top: 8px;">
                                    <i class="fa-solid fa-info-circle"></i> Click a time to set it
                                </div>
                            </div>
                        </div>

                        <!-- Recurring Toggle -->
                        <div style="margin-top: 20px;">
                            <label class="toggle-box">
                                <input type="checkbox" name="is_recurring" id="recurring-toggle" value="1"
                                    <?= ($isEdit && !empty($newsletter['is_recurring'])) ? 'checked' : '' ?>
                                    onchange="toggleRecurringOptions()">
                                <div>
                                    <span class="toggle-label">
                                        <i class="fa-solid fa-repeat" style="color: #a5b4fc; margin-right: 6px;"></i>
                                        Make This a Recurring Newsletter
                                    </span>
                                    <span class="toggle-desc">Automatically send on a regular schedule</span>
                                </div>
                            </label>
                        </div>

                        <!-- Recurring Options -->
                        <div id="recurring-options" class="recurring-panel" style="display: <?= ($isEdit && !empty($newsletter['is_recurring'])) ? 'block' : 'none' ?>;">
                            <div class="recurring-grid" style="margin-bottom: 20px;">
                                <div>
                                    <label class="recurring-label">
                                        <i class="fa-solid fa-calendar-week" style="margin-right: 6px;"></i> Frequency
                                    </label>
                                    <select name="recurring_frequency" id="recurring-frequency" class="form-input" onchange="updateRecurringPreview()">
                                        <option value="">Select frequency...</option>
                                        <option value="daily" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'daily') ? 'selected' : '' ?>>Daily</option>
                                        <option value="weekly" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'weekly') ? 'selected' : '' ?>>Weekly</option>
                                        <option value="biweekly" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'biweekly') ? 'selected' : '' ?>>Every 2 Weeks</option>
                                        <option value="monthly" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="recurring-label">
                                        <i class="fa-solid fa-clock" style="margin-right: 6px;"></i> Preferred Send Time
                                    </label>
                                    <input type="time" name="recurring_time" id="recurring-time"
                                        value="<?= ($isEdit && !empty($newsletter['recurring_time'])) ? $newsletter['recurring_time'] : '09:00' ?>"
                                        class="form-input" onchange="updateRecurringPreview()">
                                </div>
                            </div>

                            <!-- Day Selection -->
                            <div id="recurring-day-select" style="display: none; margin-bottom: 20px;">
                                <label class="recurring-label">
                                    <i class="fa-solid fa-calendar-day" style="margin-right: 6px;"></i> Send On
                                </label>
                                <div class="day-buttons">
                                    <?php
                                    $days = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
                                    $selectedDay = ($isEdit && !empty($newsletter['recurring_day'])) ? $newsletter['recurring_day'] : 'mon';
                                    foreach ($days as $value => $label):
                                    ?>
                                    <label class="day-btn <?= $selectedDay === $value ? 'active' : '' ?>">
                                        <input type="radio" name="recurring_day" value="<?= $value ?>" <?= $selectedDay === $value ? 'checked' : '' ?> style="display: none;" onchange="updateDayButtons(); updateRecurringPreview();">
                                        <?= $label ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Day of Month -->
                            <div id="recurring-monthday-select" style="display: none; margin-bottom: 20px;">
                                <label class="recurring-label">
                                    <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i> Day of Month
                                </label>
                                <select name="recurring_day_of_month" id="recurring-day-of-month" class="form-input" style="max-width: 200px;" onchange="updateRecurringPreview()">
                                    <?php for ($d = 1; $d <= 28; $d++): ?>
                                    <option value="<?= $d ?>" <?= ($isEdit && ($newsletter['recurring_day_of_month'] ?? 1) == $d) ? 'selected' : '' ?>><?= $d ?><?= $d == 1 ? 'st' : ($d == 2 ? 'nd' : ($d == 3 ? 'rd' : 'th')) ?></option>
                                    <?php endfor; ?>
                                    <option value="last" <?= ($isEdit && ($newsletter['recurring_day_of_month'] ?? '') === 'last') ? 'selected' : '' ?>>Last day</option>
                                </select>
                            </div>

                            <!-- End Date -->
                            <div style="margin-bottom: 15px;">
                                <label class="recurring-label">
                                    <i class="fa-solid fa-flag-checkered" style="margin-right: 6px;"></i> End Date (Optional)
                                </label>
                                <input type="date" name="recurring_end_date" id="recurring-end-date"
                                    value="<?= ($isEdit && !empty($newsletter['recurring_end_date'])) ? $newsletter['recurring_end_date'] : '' ?>"
                                    class="form-input" style="max-width: 200px;"
                                    min="<?= date('Y-m-d') ?>">
                                <span class="form-hint">Leave empty to continue indefinitely.</span>
                            </div>

                            <div class="recurring-preview">
                                <i class="fa-solid fa-info-circle"></i>
                                <span id="recurring-preview-text">Select a frequency to see the schedule preview</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Newsletter Content Card -->
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-header-icon green"><i class="fa-solid fa-pen-fancy"></i></div>
                    <h3 class="card-title">Newsletter Content</h3>
                </div>

                <div class="card-body">
                    <?php if (!$isEdit): ?>
                    <!-- Template Selector -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #c4b5fd; margin-right: 6px;"></i>
                            Start with a Template
                        </label>

                        <?php if (!empty($savedTemplates)): ?>
                        <!-- Saved Templates Dropdown -->
                        <div style="margin-bottom: 16px;">
                            <select id="template-selector" onchange="loadSavedTemplate(this.value)" class="form-input" style="max-width: 400px;">
                                <option value="">-- Choose from Template Library --</option>
                                <?php
                                $starterTemplates = array_filter($savedTemplates, fn($t) => $t['category'] === 'starter');
                                $customTemplates = array_filter($savedTemplates, fn($t) => $t['category'] !== 'starter');
                                ?>
                                <?php if (!empty($starterTemplates)): ?>
                                <optgroup label="Starter Templates">
                                    <?php foreach ($starterTemplates as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($customTemplates)): ?>
                                <optgroup label="Your Templates">
                                    <?php foreach ($customTemplates as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Quick Template Buttons -->
                        <div class="template-grid">
                            <button type="button" onclick="applyTemplate('blank')" class="template-btn active" data-template="blank">
                                <i class="fa-solid fa-file"></i> Blank
                            </button>
                            <button type="button" onclick="applyTemplate('announcement')" class="template-btn" data-template="announcement">
                                <i class="fa-solid fa-bullhorn"></i> Announcement
                            </button>
                            <button type="button" onclick="applyTemplate('update')" class="template-btn" data-template="update">
                                <i class="fa-solid fa-newspaper"></i> Weekly Update
                            </button>
                            <button type="button" onclick="applyTemplate('event')" class="template-btn" data-template="event">
                                <i class="fa-solid fa-calendar-star"></i> Event Invite
                            </button>
                            <button type="button" onclick="applyTemplate('welcome')" class="template-btn" data-template="welcome">
                                <i class="fa-solid fa-hand-wave"></i> Welcome
                            </button>
                            <button type="button" onclick="applyTemplate('promotional')" class="template-btn" data-template="promotional">
                                <i class="fa-solid fa-tags"></i> Promotional
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <div class="form-label-row">
                            <label class="form-label" style="margin: 0;">Content (HTML supported) *</label>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" onclick="generateAIContent()" class="btn-ai">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Content
                                </button>
                                <button type="button" onclick="togglePreview()" id="preview-toggle" class="btn-secondary" style="padding: 8px 14px;">
                                    <i class="fa-solid fa-eye"></i> Preview
                                </button>
                            </div>
                        </div>

                        <!-- Quick Insert Toolbar -->
                        <div class="insert-toolbar">
                            <span class="toolbar-label">INSERT:</span>
                            <button type="button" onclick="insertElement('heading')" class="insert-btn" title="Insert Heading">
                                <i class="fa-solid fa-heading purple"></i> Heading
                            </button>
                            <button type="button" onclick="insertElement('paragraph')" class="insert-btn" title="Insert Paragraph">
                                <i class="fa-solid fa-paragraph green"></i> Paragraph
                            </button>
                            <button type="button" onclick="insertElement('button')" class="insert-btn" title="Insert CTA Button">
                                <i class="fa-solid fa-square amber"></i> Button
                            </button>
                            <button type="button" onclick="insertElement('divider')" class="insert-btn" title="Insert Divider">
                                <i class="fa-solid fa-minus gray"></i> Divider
                            </button>
                            <button type="button" onclick="insertElement('callout')" class="insert-btn" title="Insert Callout">
                                <i class="fa-solid fa-quote-left purple"></i> Callout
                            </button>
                            <button type="button" onclick="insertElement('list')" class="insert-btn" title="Insert List">
                                <i class="fa-solid fa-list pink"></i> List
                            </button>
                            <button type="button" onclick="triggerImageUpload()" class="insert-btn" title="Upload Image">
                                <i class="fa-solid fa-image blue"></i> Image
                            </button>
                            <input type="file" id="image-upload-input" accept="image/*" style="display: none;" onchange="handleImageUpload(this)">
                            <div class="toolbar-divider"></div>
                            <button type="button" onclick="insertToken('first_name')" class="insert-btn" style="background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3); color: #93c5fd;" title="Insert First Name">
                                <i class="fa-solid fa-user"></i> Name
                            </button>
                            <div class="toolbar-divider"></div>
                            <span class="toolbar-label">DYNAMIC:</span>
                            <button type="button" onclick="insertDynamicBlock('recent_listings')" class="insert-btn dynamic" title="Recent Listings">
                                <i class="fa-solid fa-list-check"></i> Listings
                            </button>
                            <button type="button" onclick="insertDynamicBlock('community_stats')" class="insert-btn dynamic" title="Community Stats">
                                <i class="fa-solid fa-chart-simple"></i> Stats
                            </button>
                            <button type="button" onclick="insertDynamicBlock('member_spotlight')" class="insert-btn dynamic" title="Member Spotlight">
                                <i class="fa-solid fa-star"></i> Spotlight
                            </button>
                        </div>

                        <textarea name="content" id="content-editor"><?= $isEdit ? htmlspecialchars($newsletter['content']) : '' ?></textarea>

                        <!-- Preview Panel -->
                        <div id="preview-panel" class="preview-panel">
                            <div class="preview-container">
                                <div class="preview-header">
                                    <div class="preview-dots">
                                        <div class="preview-dot red"></div>
                                        <div class="preview-dot yellow"></div>
                                        <div class="preview-dot green"></div>
                                        <span class="preview-title">Email Preview</span>
                                    </div>
                                    <div class="preview-mode-btns">
                                        <button type="button" onclick="setPreviewMode('desktop')" id="preview-desktop" class="preview-mode-btn active" title="Desktop">
                                            <i class="fa-solid fa-desktop"></i>
                                        </button>
                                        <button type="button" onclick="setPreviewMode('tablet')" id="preview-tablet" class="preview-mode-btn" title="Tablet">
                                            <i class="fa-solid fa-tablet-screen-button"></i>
                                        </button>
                                        <button type="button" onclick="setPreviewMode('mobile')" id="preview-mobile" class="preview-mode-btn" title="Mobile">
                                            <i class="fa-solid fa-mobile-screen"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="preview-clients">
                                    <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem; margin-right: 8px; align-self: center;">Client:</span>
                                    <button type="button" onclick="setClientPreview('raw')" id="client-raw" class="preview-client-btn active">Raw</button>
                                    <button type="button" onclick="setClientPreview('gmail')" id="client-gmail" class="preview-client-btn"><i class="fa-brands fa-google"></i> Gmail</button>
                                    <button type="button" onclick="setClientPreview('outlook')" id="client-outlook" class="preview-client-btn"><i class="fa-brands fa-microsoft"></i> Outlook</button>
                                    <button type="button" onclick="setClientPreview('apple')" id="client-apple" class="preview-client-btn"><i class="fa-brands fa-apple"></i> Apple</button>
                                </div>
                                <div class="preview-frame-container">
                                    <iframe id="preview-frame" class="preview-frame"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tips-grid">
                        <div class="tips-box gray">
                            <div class="tips-title">
                                <i class="fa-solid fa-lightbulb amber"></i>
                                Formatting Tips:
                            </div>
                            <ul>
                                <li>Use <code>&lt;h2&gt;</code> for section headings</li>
                                <li>Use <code>&lt;p&gt;</code> for paragraphs</li>
                                <li>Use <code>&lt;a href="..."&gt;</code> for links</li>
                                <li>Use <code>&lt;img&gt;</code> for images</li>
                            </ul>
                        </div>
                        <div class="tips-box blue">
                            <div class="tips-title">
                                <i class="fa-solid fa-user-tag blue"></i>
                                Personalization Tokens:
                            </div>
                            <ul>
                                <li><code>{{first_name}}</code> - First name</li>
                                <li><code>{{last_name}}</code> - Last name</li>
                                <li><code>{{full_name}}</code> - Full name</li>
                                <li><code>{{email}}</code> - Email address</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="glass-card" style="padding: 25px 30px;">
                <div class="actions-row">
                    <div>
                        <?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
                            <button type="button" onclick="confirmDeleteNewsletter()" class="btn-delete">
                                <i class="fa-solid fa-trash"></i> Delete Newsletter
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="actions-buttons">
                        <?php if ($isEdit): ?>
                            <a href="<?= $basePath ?>/admin-legacy/newsletters/preview/<?= $newsletter['id'] ?>" target="_blank" class="btn-secondary">
                                <i class="fa-solid fa-eye"></i> Preview
                            </a>

                            <?php if ($newsletter['status'] !== 'sent'): ?>
                                <button type="button" id="send-test-btn" onclick="sendTestEmail()" class="btn-secondary">
                                    <i class="fa-solid fa-paper-plane"></i> Send Test
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="btn-secondary">
                            Cancel
                        </a>

                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-save"></i> <?= $isEdit ? 'Save Changes' : 'Create Draft' ?>
                        </button>

                        <?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
                            <button type="button" onclick="confirmSend()" class="btn-send">
                                <i class="fa-solid fa-rocket"></i> Send Now (<span id="send-count"><?= number_format($eligibleCount) ?></span>)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <div class="recipient-info">
                        <i class="fa-solid fa-users"></i>
                        This newsletter will be sent to <strong><?= number_format($eligibleCount) ?></strong> eligible recipients.
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
        <!-- Delete Form -->
        <form id="delete-newsletter-form" action="<?= $basePath ?>/admin-legacy/newsletters/delete" method="POST" style="display: none;">
            <?= Csrf::input() ?>
            <input type="hidden" name="id" value="<?= $newsletter['id'] ?>">
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
<!-- Send Confirmation Modal -->
<div id="send-modal" class="modal" role="dialog" aria-modal="true"-overlay">
    <div class="modal" role="dialog" aria-modal="true"-content">
        <div style="text-align: center; margin-bottom: 25px;">
            <div class="modal" role="dialog" aria-modal="true"-icon">
                <i class="fa-solid fa-rocket"></i>
            </div>
            <h3 class="modal" role="dialog" aria-modal="true"-title">Send Newsletter Now?</h3>
        </div>

        <p class="modal" role="dialog" aria-modal="true"-text">
            This will immediately send "<strong><?= htmlspecialchars($newsletter['subject']) ?></strong>"
            to <strong class="count" id="modal-count"><?= number_format($eligibleCount) ?></strong> recipients.
        </p>

        <div class="modal" role="dialog" aria-modal="true"-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>This action cannot be undone.</span>
        </div>

        <div class="modal" role="dialog" aria-modal="true"-info">
            <i class="fa-solid fa-info-circle"></i>
            <span>Your changes will be saved before sending.</span>
        </div>

        <div class="modal" role="dialog" aria-modal="true"-buttons">
            <button onclick="closeModal()" class="btn-secondary">Cancel</button>
            <button type="button" id="send-now-btn" onclick="saveAndSend()" class="btn-send">
                <i class="fa-solid fa-paper-plane"></i> Yes, Send Now
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const basePath = '<?= $basePath ?>';
const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
<?php if ($isEdit): ?>
const newsletterId = <?= $newsletter['id'] ?>;
<?php endif; ?>

// Audience counts
const audienceCounts = {
    'all_members': <?= (int)$audienceCounts['all_members'] ?>,
    'subscribers_only': <?= (int)$audienceCounts['subscribers_only'] ?>,
    'both': <?= (int)$audienceCounts['both'] ?>
};

// Initialize TinyMCE
tinymce.init({
    selector: '#content-editor',
    height: 500,
    menubar: true,
    relative_urls: false,
    remove_script_host: false,
    convert_urls: false,
    skin: 'oxide-dark',
    content_css: 'dark',
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
    ],
    toolbar: 'undo redo | styles | bold italic underline strikethrough | ' +
        'alignleft aligncenter alignright alignjustify | ' +
        'bullist numlist outdent indent | link image media | ' +
        'forecolor backcolor | emoticons | removeformat | code fullscreen help',
    content_style: `
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }
        h1, h2, h3 { color: #111827; margin-top: 0; }
        a { color: #6366f1; }
        img { max-width: 100%; height: auto; }
    `,
    setup: function(editor) {
        editor.on('change', function() {
            editor.save();
            updatePreview();
        });
    },
    promotion: false,
    branding: false
});

// A/B Test toggle
document.getElementById('ab-test-toggle')?.addEventListener('change', function() {
    document.getElementById('ab-test-settings').style.display = this.checked ? 'block' : 'none';
});

// Sync subject to A/B display
document.getElementById('subject-input')?.addEventListener('input', function() {
    const display = document.getElementById('subject-a-display');
    if (display) display.value = this.value;
});

// Initialize subject display
document.addEventListener('DOMContentLoaded', function() {
    const subjectInput = document.getElementById('subject-input');
    const display = document.getElementById('subject-a-display');
    if (subjectInput && display) display.value = subjectInput.value;
    updateSubjectCounter();
});

// Subject counter
function updateSubjectCounter() {
    const input = document.getElementById('subject-input');
    const lengthSpan = document.getElementById('subject-length');
    const qualitySpan = document.getElementById('subject-quality');
    if (!input || !lengthSpan) return;

    const len = input.value.length;
    lengthSpan.textContent = len;

    if (qualitySpan) {
        if (len === 0) {
            qualitySpan.textContent = '';
            qualitySpan.style.background = 'transparent';
        } else if (len < 20) {
            qualitySpan.textContent = 'Too short';
            qualitySpan.style.background = 'rgba(245, 158, 11, 0.3)';
            qualitySpan.style.color = '#fcd34d';
        } else if (len >= 20 && len <= 60) {
            qualitySpan.textContent = 'Optimal';
            qualitySpan.style.background = 'rgba(16, 185, 129, 0.3)';
            qualitySpan.style.color = '#6ee7b7';
        } else if (len > 60 && len <= 100) {
            qualitySpan.textContent = 'Good';
            qualitySpan.style.background = 'rgba(59, 130, 246, 0.3)';
            qualitySpan.style.color = '#93c5fd';
        } else {
            qualitySpan.textContent = 'May truncate';
            qualitySpan.style.background = 'rgba(239, 68, 68, 0.3)';
            qualitySpan.style.color = '#fca5a5';
        }
    }
}

// Targeting toggle
function toggleTargeting() {
    const section = document.getElementById('targeting-section');
    const icon = document.getElementById('targeting-icon');
    const btnText = document.getElementById('targeting-btn-text');

    if (section.classList.contains('visible')) {
        section.classList.remove('visible');
        icon.className = 'fa-solid fa-plus';
        btnText.textContent = 'Add Filters';
    } else {
        section.classList.add('visible');
        icon.className = 'fa-solid fa-minus';
        btnText.textContent = 'Hide Filters';
    }
}

// Filter functions
function filterCounties(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.county-option').forEach(el => {
        el.style.display = el.dataset.county.includes(q) ? 'flex' : 'none';
    });
}

function filterTowns(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.town-option').forEach(el => {
        el.style.display = el.dataset.town.includes(q) ? 'flex' : 'none';
    });
}

function filterGroups(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.group-option').forEach(el => {
        el.style.display = el.dataset.group.includes(q) ? 'flex' : 'none';
    });
}

// Audience option styling
document.querySelectorAll('input[name="target_audience"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.audience-option').forEach(opt => opt.classList.remove('selected'));
        this.closest('.audience-option').classList.add('selected');
        updateAudienceDisplay();
    });
});

function updateAudienceDisplay() {
    const selected = document.querySelector('input[name="target_audience"]:checked');
    if (!selected) return;
    const count = audienceCounts[selected.value] || 0;
    const sendCount = document.getElementById('send-count');
    const modalCount = document.getElementById('modal-count');
    if (sendCount) sendCount.textContent = count.toLocaleString();
    if (modalCount) modalCount.textContent = count.toLocaleString();
}

// Recommended send times
function applyRecommendedTime(hour, minute) {
    const input = document.getElementById('scheduled_at');
    if (!input) return;

    const date = new Date();
    if (date.getHours() >= hour) date.setDate(date.getDate() + 1);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const h = String(hour).padStart(2, '0');
    const m = String(minute).padStart(2, '0');

    input.value = `${year}-${month}-${day}T${h}:${m}`;
    input.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.3)';
    setTimeout(() => input.style.boxShadow = '', 1500);
}

// Recurring options
function toggleRecurringOptions() {
    const toggle = document.getElementById('recurring-toggle');
    const options = document.getElementById('recurring-options');
    if (toggle && options) {
        options.style.display = toggle.checked ? 'block' : 'none';
        if (toggle.checked) updateRecurringPreview();
    }
}

function updateRecurringPreview() {
    const frequency = document.getElementById('recurring-frequency')?.value;
    const time = document.getElementById('recurring-time')?.value || '09:00';
    const previewText = document.getElementById('recurring-preview-text');
    const daySelect = document.getElementById('recurring-day-select');
    const monthDaySelect = document.getElementById('recurring-monthday-select');

    if (!previewText) return;

    // Format time
    const [h, m] = time.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    const formattedTime = `${displayHour}:${m} ${ampm}`;

    // Show/hide selectors
    if (daySelect) daySelect.style.display = (frequency === 'weekly' || frequency === 'biweekly') ? 'block' : 'none';
    if (monthDaySelect) monthDaySelect.style.display = frequency === 'monthly' ? 'block' : 'none';

    // Build preview
    const dayNames = {mon: 'Monday', tue: 'Tuesday', wed: 'Wednesday', thu: 'Thursday', fri: 'Friday', sat: 'Saturday', sun: 'Sunday'};
    let preview = 'Select a frequency to see the schedule preview';

    switch (frequency) {
        case 'daily':
            preview = `This newsletter will be sent every day at ${formattedTime}`;
            break;
        case 'weekly':
            const selectedDay = document.querySelector('input[name="recurring_day"]:checked')?.value || 'mon';
            preview = `This newsletter will be sent every ${dayNames[selectedDay]} at ${formattedTime}`;
            break;
        case 'biweekly':
            const biDay = document.querySelector('input[name="recurring_day"]:checked')?.value || 'mon';
            preview = `This newsletter will be sent every other ${dayNames[biDay]} at ${formattedTime}`;
            break;
        case 'monthly':
            const dom = document.getElementById('recurring-day-of-month')?.value || '1';
            const dayDisplay = dom === 'last' ? 'the last day' : `the ${getOrdinal(dom)}`;
            preview = `This newsletter will be sent on ${dayDisplay} of each month at ${formattedTime}`;
            break;
    }

    previewText.textContent = preview;
}

function getOrdinal(n) {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

function updateDayButtons() {
    document.querySelectorAll('.day-btn').forEach(btn => {
        const radio = btn.querySelector('input[type="radio"]');
        if (radio.checked) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

// Template buttons
function applyTemplate(type) {
    document.querySelectorAll('.template-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.template === type) btn.classList.add('active');
    });

    // Reset the dropdown when clicking quick templates
    const dropdown = document.getElementById('template-selector');
    if (dropdown) dropdown.value = '';

    // Template content would be inserted here
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        // Could fetch template content from server
    }
}

// Load saved template from library
function loadSavedTemplate(templateId) {
    if (!templateId) return;

    // Clear quick template selection
    document.querySelectorAll('.template-btn').forEach(btn => btn.classList.remove('active'));

    // Fetch template data from server
    const url = '<?= $basePath ?>/admin-legacy/newsletters/load-template/' + templateId;
    console.log('Loading template from:', url);

    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Template data received:', data);
            if (data.success && data.template) {
                // Fill in subject
                const subjectField = document.getElementById('subject');
                if (subjectField && data.template.subject) {
                    subjectField.value = data.template.subject;
                    console.log('Subject set:', data.template.subject);
                }

                // Fill in preview text
                const previewField = document.getElementById('preview_text');
                if (previewField && data.template.preview_text) {
                    previewField.value = data.template.preview_text;
                    console.log('Preview text set');
                }

                // Fill in content - try both TinyMCE and textarea
                const contentField = document.getElementById('content-editor');
                const content = data.template.content || '';
                console.log('Content to set:', content.substring(0, 100) + '...');

                // Always set the textarea value first (for form submission)
                if (contentField) {
                    contentField.value = content;
                    console.log('Content set via textarea');
                }

                // Then try TinyMCE if available
                try {
                    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
                        tinymce.get('content-editor').setContent(content);
                        console.log('Content set via TinyMCE');
                    }
                } catch (e) {
                    console.log('TinyMCE setContent failed:', e);
                }

                // Update preview if visible
                if (typeof previewVisible !== 'undefined' && previewVisible) updatePreview();

                // Show success feedback
                showToast('Template loaded successfully', 'success');
            } else {
                console.error('Template load failed:', data);
                showToast(data.error || 'Failed to load template', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading template:', error);
            showToast('Failed to load template: ' + error.message, 'error');
        });
}

// Simple toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'toast-notification toast-' + type;
    toast.innerHTML = '<i class="fa-solid fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-size: 14px; z-index: 9999; animation: slideIn 0.3s ease;';
    toast.style.background = type === 'success' ? 'linear-gradient(135deg, #10b981, #059669)' : 'linear-gradient(135deg, #ef4444, #dc2626)';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Preview panel
let previewVisible = false;
function togglePreview() {
    const panel = document.getElementById('preview-panel');
    const btn = document.getElementById('preview-toggle');
    previewVisible = !previewVisible;

    if (previewVisible) {
        panel.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide Preview';
        btn.style.background = 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)';
        btn.style.color = 'white';
        btn.style.borderColor = 'transparent';
        updatePreview();
    } else {
        panel.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Preview';
        btn.style.background = '';
        btn.style.color = '';
        btn.style.borderColor = '';
    }
}

function updatePreview() {
    const frame = document.getElementById('preview-frame');
    if (!frame || !previewVisible) return;

    let content = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        content = tinymce.get('content-editor').getContent();
    } else {
        content = document.getElementById('content-editor')?.value || '';
    }

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; color: #333; }
        h1, h2, h3 { color: #111; }
        a { color: #6366f1; }
        img { max-width: 100%; }
    </style></head><body>${content}</body></html>`;

    frame.srcdoc = html;
}

function setPreviewMode(mode) {
    const frame = document.getElementById('preview-frame');
    const widths = { desktop: '620px', tablet: '768px', mobile: '375px' };

    frame.style.maxWidth = widths[mode] || '620px';

    document.querySelectorAll('.preview-mode-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('preview-' + mode)?.classList.add('active');
}

function setClientPreview(client) {
    document.querySelectorAll('.preview-client-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('client-' + client)?.classList.add('active');
    // Could apply client-specific styling here
    updatePreview();
}

// Insert functions
function insertElement(type) {
    const elements = {
        heading: '<h2>Section Heading</h2>',
        paragraph: '<p>Your text here...</p>',
        button: '<p style="text-align: center;"><a href="#" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Click Here</a></p>',
        divider: '<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">',
        callout: '<blockquote style="border-left: 4px solid #6366f1; padding-left: 16px; margin: 16px 0; color: #666;">Important note or quote here...</blockquote>',
        list: '<ul><li>Item one</li><li>Item two</li><li>Item three</li></ul>'
    };

    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').insertContent(elements[type] || '');
    }
}

function insertToken(token) {
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').insertContent(`{{${token}}}`);
    }
}

function insertDynamicBlock(type) {
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').insertContent(`{{${type}}}`);
    }
}

function triggerImageUpload() {
    document.getElementById('image-upload-input')?.click();
}

function handleImageUpload(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
                tinymce.get('content-editor').insertContent(`<img src="${e.target.result}" alt="Image" style="max-width: 100%;" loading="lazy">`);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

<?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
// Modal functions
function confirmSend() {
    document.getElementById('send-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('send-modal').style.display = 'none';
}

function confirmDeleteNewsletter() {
    if (confirm('Are you sure you want to delete this newsletter?\n\nThis action cannot be undone.')) {
        document.getElementById('delete-newsletter-form').submit();
    }
}

function saveAndSend() {
    const btn = document.getElementById('send-now-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving & Sending...';

    const form = document.getElementById('newsletter-form');
    const formData = new FormData(form);
    formData.append('send_after_save', '1');

    fetch(`${basePath}/admin-legacy/newsletters/update/<?= $newsletter['id'] ?>`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        window.location.href = `${basePath}/admin-legacy/newsletters/send-direct/<?= $newsletter['id'] ?>`;
    })
    .catch(error => {
        alert('Error saving: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Yes, Send Now';
    });
}

function sendTestEmail() {
    const btn = document.getElementById('send-test-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

    const form = document.getElementById('newsletter-form');
    const formData = new FormData(form);

    fetch(`${basePath}/admin-legacy/newsletters/send-test/<?= $newsletter['id'] ?>`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.success ? data.message : 'Error: ' + (data.error || 'Failed'));
    })
    .catch(error => alert('Error: ' + error.message))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Test';
    });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
<?php endif; ?>

// AI Generation placeholders (implement with your AI endpoint)
async function generateAISubject() {
    const btn = event.target.closest('button');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const response = await fetch(`${basePath}/api/ai/generate/newsletter`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'subject', context: {} })
        });
        const data = await response.json();
        if (data.success && data.content) {
            document.getElementById('subject-input').value = data.content;
            updateSubjectCounter();
        }
    } catch (e) {
        console.error('AI generation failed:', e);
    } finally {
        btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate with AI';
        btn.disabled = false;
    }
}

async function generateAIPreview() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const subject = document.getElementById('subject-input')?.value || '';
        const response = await fetch(`${basePath}/api/ai/generate/newsletter`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'preview', context: { subject } })
        });
        const data = await response.json();
        if (data.success && data.content) {
            document.getElementById('preview-text-input').value = data.content;
        }
    } catch (e) {
        console.error('AI generation failed:', e);
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

async function generateABVariant() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const subject = document.getElementById('subject-input')?.value || '';
        const response = await fetch(`${basePath}/api/ai/generate/newsletter`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'subject_ab', context: { subject } })
        });
        const data = await response.json();
        if (data.success && data.content) {
            document.getElementById('subject-b').value = data.content;
        }
    } catch (e) {
        console.error('AI generation failed:', e);
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

async function generateAIContent() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    try {
        const subject = document.getElementById('subject-input')?.value || '';
        const preview = document.getElementById('preview-text-input')?.value || '';
        const response = await fetch(`${basePath}/api/ai/generate/newsletter`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'content', context: { subject, topic: preview || subject } })
        });
        const data = await response.json();
        if (data.success && data.content) {
            if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
                tinymce.get('content-editor').setContent(data.content);
            }
            updatePreview();
        }
    } catch (e) {
        console.error('AI generation failed:', e);
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
