<?php
/**
 * Newsletter Create/Edit Form
 * Modern polished view with full layout integration and direct targeting options
 */

// Layout detection and header
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();
$isEdit = isset($newsletter);

$action = $isEdit
    ? $basePath . "/admin/newsletters/update/" . $newsletter['id']
    : $basePath . "/admin/newsletters/store";

$eligibleCount = $eligibleCount ?? 0;
$segments = $segments ?? [];
$groups = $groups ?? [];
$counties = $counties ?? \Nexus\Models\NewsletterSegment::getIrishCounties();
$towns = $towns ?? \Nexus\Models\NewsletterSegment::getIrishTowns();

// Hero settings for modern layout
$hTitle = $isEdit ? 'Edit Newsletter' : 'Create Newsletter';
$hSubtitle = $isEdit ? 'Update your campaign details and content' : 'Compose a new email campaign for your community';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Newsletter Admin';

// Get TinyMCE API key from .env
$tinymceApiKey = 'no-api-key';
$envPath = dirname(__DIR__, 3) . '/.env';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, 'TINYMCE_API_KEY=') === 0) {
            $tinymceApiKey = trim(substr($line, 16), '"\'');
            break;
        }
    }
}

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}
?>

<!-- TinyMCE Rich Text Editor -->
<script src="https://cdn.tiny.cloud/1/<?= htmlspecialchars($tinymceApiKey) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="newsletter-form-wrapper">
    <div style="max-width: 900px; margin: 0 auto;">

        <!-- Back Button -->
        <div style="margin-bottom: 20px;">
            <a href="<?= $basePath ?>/admin/newsletters" style="text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 5px; background: rgba(0,0,0,0.2); padding: 6px 14px; border-radius: 20px; backdrop-filter: blur(4px); font-size: 0.9rem; transition: background 0.2s;">
                &larr; Back to Newsletters
            </a>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <div style="width: 32px; height: 32px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-check" style="color: white;"></i>
                </div>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <div style="width: 32px; height: 32px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-xmark" style="color: white;"></i>
                </div>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <form action="<?= $action ?>" method="POST" id="newsletter-form">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Campaign Details Card -->
            <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
                <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 30px; border-bottom: 1px solid #e2e8f0;">
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-envelope" style="color: #f59e0b;"></i>
                        Campaign Details
                    </h3>
                </div>

                <div style="padding: 30px;">
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="font-weight: 600; color: #374151;">Subject Line *</label>
                            <button type="button" onclick="generateAISubject()" class="ai-gen-btn" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 500; display: flex; align-items: center; gap: 6px; transition: all 0.2s;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate with AI
                            </button>
                        </div>
                        <input type="text" name="subject" id="subject-input" required
                            value="<?= $isEdit ? htmlspecialchars($newsletter['subject']) : '' ?>"
                            class="nexus-input"
                            style="width: 100%; font-size: 1.1rem; padding: 14px 16px; border-radius: 10px; border: 2px solid #e5e7eb; transition: border-color 0.2s, box-shadow 0.2s;"
                            placeholder="Write an engaging subject line..."
                            maxlength="150"
                            oninput="updateSubjectCounter()">
                        <div id="ai-subject-suggestions" style="display: none; margin-top: 10px; background: #f5f3ff; border: 1px solid #c4b5fd; border-radius: 10px; padding: 12px;"></div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                            <small style="color: #6b7280;">This is what recipients will see in their inbox. Keep it compelling!</small>
                            <div id="subject-counter" style="font-size: 0.8rem; font-weight: 500;">
                                <span id="subject-length">0</span>/150
                                <span id="subject-quality" style="margin-left: 8px; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem;"></span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="font-weight: 600; color: #374151;">Preview Text</label>
                            <button type="button" onclick="generateAIPreview()" class="ai-gen-btn" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 500; display: flex; align-items: center; gap: 6px; transition: all 0.2s;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                            </button>
                        </div>
                        <input type="text" name="preview_text" id="preview-text-input"
                            value="<?= $isEdit ? htmlspecialchars($newsletter['preview_text'] ?? '') : '' ?>"
                            class="nexus-input"
                            style="width: 100%; padding: 12px 16px; border-radius: 10px; border: 2px solid #e5e7eb;"
                            placeholder="Brief preview shown after subject..." maxlength="255">
                        <small style="color: #6b7280; margin-top: 6px; display: block;">Optional. Appears after the subject line in most email clients.</small>
                    </div>

                    <!-- A/B Testing Toggle -->
                    <div style="padding-top: 20px; border-top: 1px solid #e5e7eb;">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 15px; background: #f9fafb; border-radius: 10px; transition: background 0.2s;">
                            <input type="checkbox" name="ab_test_enabled" id="ab-test-toggle" value="1"
                                <?= ($isEdit && !empty($newsletter['ab_test_enabled'])) ? 'checked' : '' ?>
                                style="width: 20px; height: 20px; accent-color: #6366f1;">
                            <div>
                                <span style="font-weight: 600; color: #111827;">Enable A/B Testing</span>
                                <span style="display: block; color: #6b7280; font-size: 0.85rem; margin-top: 2px;">Test two different subject lines to see which performs better</span>
                            </div>
                        </label>
                    </div>

                    <!-- A/B Test Settings (hidden by default) -->
                    <div id="ab-test-settings" style="display: <?= ($isEdit && !empty($newsletter['ab_test_enabled'])) ? 'block' : 'none' ?>; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 20%, #fef3c7 100%); padding: 25px; border-radius: 12px; margin-top: 20px; border: 1px solid #fcd34d;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #6366f1;">
                                    <i class="fa-solid fa-a" style="margin-right: 6px;"></i> Subject A (Original)
                                </label>
                                <input type="text" id="subject-a-display" disabled
                                    class="nexus-input"
                                    style="width: 100%; background: #e5e7eb; border-radius: 8px; padding: 10px 14px; color: #6b7280;"
                                    placeholder="Enter subject line above...">
                                <small style="color: #92400e;">Uses the main subject line</small>
                            </div>
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <label style="font-weight: 600; color: #f59e0b;">
                                        <i class="fa-solid fa-b" style="margin-right: 6px;"></i> Subject B (Variant)
                                    </label>
                                    <button type="button" onclick="generateABVariant()" class="ai-gen-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Variant
                                    </button>
                                </div>
                                <input type="text" name="subject_b" id="subject-b"
                                    value="<?= $isEdit ? htmlspecialchars($newsletter['subject_b'] ?? '') : '' ?>"
                                    class="nexus-input"
                                    style="width: 100%; border-radius: 8px; padding: 10px 14px;"
                                    placeholder="Alternative subject line...">
                                <small style="color: #92400e;">Test a different approach</small>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #92400e;">Audience Split</label>
                                <select name="ab_split_percentage" class="nexus-input" style="width: 100%; border-radius: 8px; padding: 10px 14px;">
                                    <option value="50" <?= ($isEdit && ($newsletter['ab_split_percentage'] ?? 50) == 50) ? 'selected' : '' ?>>50% / 50% (Equal)</option>
                                    <option value="30" <?= ($isEdit && ($newsletter['ab_split_percentage'] ?? 50) == 30) ? 'selected' : '' ?>>30% / 70%</option>
                                    <option value="20" <?= ($isEdit && ($newsletter['ab_split_percentage'] ?? 50) == 20) ? 'selected' : '' ?>>20% / 80%</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #92400e;">Winner Metric</label>
                                <select name="ab_winner_metric" class="nexus-input" style="width: 100%; border-radius: 8px; padding: 10px 14px;">
                                    <option value="opens" <?= ($isEdit && ($newsletter['ab_winner_metric'] ?? 'opens') == 'opens') ? 'selected' : '' ?>>Open Rate</option>
                                    <option value="clicks" <?= ($isEdit && ($newsletter['ab_winner_metric'] ?? 'opens') == 'clicks') ? 'selected' : '' ?>>Click Rate</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audience & Targeting Card -->
            <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
                <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 30px; border-bottom: 1px solid #e2e8f0;">
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-users" style="color: #6366f1;"></i>
                        Audience & Targeting
                    </h3>
                </div>

                <div style="padding: 30px;">
                    <?php
                    $currentAudience = $isEdit ? ($newsletter['target_audience'] ?? 'all_members') : 'all_members';
                    $audienceCounts = $audienceCounts ?? ['all_members' => 0, 'subscribers_only' => 0, 'both' => 0];
                    ?>

                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #374151;">Base Audience *</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                            <label class="audience-option" style="display: flex; align-items: flex-start; gap: 12px; padding: 16px; border: 2px solid <?= $currentAudience === 'all_members' ? '#6366f1' : '#e5e7eb' ?>; border-radius: 12px; cursor: pointer; background: <?= $currentAudience === 'all_members' ? '#f5f3ff' : 'white' ?>; transition: all 0.2s;">
                                <input type="radio" name="target_audience" value="all_members" <?= $currentAudience === 'all_members' ? 'checked' : '' ?> style="margin-top: 2px; accent-color: #6366f1;">
                                <div>
                                    <div style="font-weight: 600; color: #111827;">All Members</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;"><?= number_format($audienceCounts['all_members']) ?> approved members</div>
                                </div>
                            </label>

                            <label class="audience-option" style="display: flex; align-items: flex-start; gap: 12px; padding: 16px; border: 2px solid <?= $currentAudience === 'subscribers_only' ? '#6366f1' : '#e5e7eb' ?>; border-radius: 12px; cursor: pointer; background: <?= $currentAudience === 'subscribers_only' ? '#f5f3ff' : 'white' ?>; transition: all 0.2s;">
                                <input type="radio" name="target_audience" value="subscribers_only" <?= $currentAudience === 'subscribers_only' ? 'checked' : '' ?> style="margin-top: 2px; accent-color: #6366f1;">
                                <div>
                                    <div style="font-weight: 600; color: #111827;">Subscribers Only</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;"><?= number_format($audienceCounts['subscribers_only']) ?> email subscribers</div>
                                </div>
                            </label>

                            <label class="audience-option" style="display: flex; align-items: flex-start; gap: 12px; padding: 16px; border: 2px solid <?= $currentAudience === 'both' ? '#6366f1' : '#e5e7eb' ?>; border-radius: 12px; cursor: pointer; background: <?= $currentAudience === 'both' ? '#f5f3ff' : 'white' ?>; transition: all 0.2s;">
                                <input type="radio" name="target_audience" value="both" <?= $currentAudience === 'both' ? 'checked' : '' ?> style="margin-top: 2px; accent-color: #6366f1;">
                                <div>
                                    <div style="font-weight: 600; color: #111827;">Members + Subscribers</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;"><?= number_format($audienceCounts['both']) ?> combined</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Advanced Targeting Section -->
                    <div style="padding-top: 25px; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                            <div>
                                <h4 style="margin: 0; font-size: 1rem; font-weight: 600; color: #374151;">Advanced Targeting</h4>
                                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #6b7280;">Filter your audience by location or group membership</p>
                            </div>
                            <button type="button" id="toggle-targeting" onclick="toggleTargeting()"
                                style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 500; color: #374151; display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-plus" id="targeting-icon"></i>
                                <span id="targeting-btn-text">Add Filters</span>
                            </button>
                        </div>

                        <div id="targeting-section" style="display: none;">
                            <!-- Saved Segment Selection -->
                            <?php if (!empty($segments)): ?>
                            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #374151;">
                                    <i class="fa-solid fa-filter" style="color: #6366f1; margin-right: 6px;"></i>
                                    Use Saved Segment
                                </label>
                                <select name="segment_id" id="segment-select" class="nexus-input" style="width: 100%; padding: 12px 14px; border-radius: 8px;">
                                    <option value="">-- Select a segment or use filters below --</option>
                                    <?php foreach ($segments as $segment):
                                        $segmentCount = 0;
                                        try {
                                            $segmentCount = \Nexus\Models\NewsletterSegment::countMatchingUsers($segment['id']);
                                        } catch (\Exception $e) {
                                            // Ignore count errors
                                        }
                                    ?>
                                        <option value="<?= $segment['id'] ?>"
                                            data-count="<?= $segmentCount ?>"
                                            <?= ($isEdit && !empty($newsletter['segment_id']) && $newsletter['segment_id'] == $segment['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($segment['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="segment-count" style="display: none; margin-top: 12px; padding: 12px 16px; background: #dcfce7; border-radius: 8px; color: #166534; font-weight: 500;">
                                    <i class="fa-solid fa-check-circle" style="margin-right: 6px;"></i>
                                    <span id="segment-count-value">0</span> members match this segment
                                </div>
                            </div>
                            <?php endif; ?>

                            <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #93c5fd; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                <p style="margin: 0 0 15px; font-size: 0.9rem; color: #1e40af; font-weight: 500;">
                                    <i class="fa-solid fa-info-circle" style="margin-right: 6px;"></i>
                                    Or use Quick Filters below to target specific groups, counties, or towns
                                </p>

                                <!-- County Targeting -->
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #374151;">
                                        <i class="fa-solid fa-map" style="color: #10b981; margin-right: 6px;"></i>
                                        Target by County
                                    </label>
                                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 15px;">
                                        <input type="text" id="county-search" placeholder="Search counties..."
                                            style="width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px;"
                                            onkeyup="filterCounties(this.value)">
                                        <div id="counties-container" style="max-height: 180px; overflow-y: auto;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px;">
                                                <?php
                                                $selectedCounties = [];
                                                if ($isEdit && !empty($newsletter['target_counties'])) {
                                                    $selectedCounties = json_decode($newsletter['target_counties'], true) ?? [];
                                                }
                                                foreach ($counties as $county):
                                                ?>
                                                    <label class="county-option" data-county="<?= strtolower($county) ?>" style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px 10px; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                                                        <input type="checkbox" name="target_counties[]" value="<?= htmlspecialchars($county) ?>"
                                                            <?= in_array($county, $selectedCounties) ? 'checked' : '' ?>
                                                            style="accent-color: #10b981;">
                                                        <span style="font-size: 0.9rem; color: #374151;"><?= htmlspecialchars($county) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <small style="color: #6b7280; margin-top: 6px; display: block;">Select counties to target. Leave empty to send to all locations.</small>
                                </div>

                                <!-- Town Targeting -->
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #374151;">
                                        <i class="fa-solid fa-city" style="color: #8b5cf6; margin-right: 6px;"></i>
                                        Target by Town/City
                                    </label>
                                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 15px;">
                                        <input type="text" id="town-search" placeholder="Search towns..."
                                            style="width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px;"
                                            onkeyup="filterTowns(this.value)">
                                        <div id="towns-container" style="max-height: 180px; overflow-y: auto;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px;">
                                                <?php
                                                $selectedTowns = [];
                                                if ($isEdit && !empty($newsletter['target_towns'])) {
                                                    $selectedTowns = json_decode($newsletter['target_towns'], true) ?? [];
                                                }
                                                foreach ($towns as $town):
                                                ?>
                                                    <label class="town-option" data-town="<?= strtolower($town) ?>" style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px 10px; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                                                        <input type="checkbox" name="target_towns[]" value="<?= htmlspecialchars($town) ?>"
                                                            <?= in_array($town, $selectedTowns) ? 'checked' : '' ?>
                                                            style="accent-color: #8b5cf6;">
                                                        <span style="font-size: 0.9rem; color: #374151;"><?= htmlspecialchars($town) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <!-- Custom Town Input -->
                                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                                            <input type="text" name="custom_towns" placeholder="Or enter other towns (comma separated)..."
                                                value="<?= $isEdit ? htmlspecialchars($newsletter['custom_towns'] ?? '') : '' ?>"
                                                style="width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px;">
                                        </div>
                                    </div>
                                    <small style="color: #6b7280; margin-top: 6px; display: block;">Select towns or type custom ones. Leave empty to send to all locations.</small>
                                </div>

                                <!-- Group Targeting -->
                                <?php if (!empty($groups)): ?>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #374151;">
                                        <i class="fa-solid fa-user-group" style="color: #f59e0b; margin-right: 6px;"></i>
                                        Target by Group Membership
                                    </label>
                                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 15px;">
                                        <input type="text" id="group-search" placeholder="Search groups..."
                                            style="width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px;"
                                            onkeyup="filterGroups(this.value)">
                                        <div id="groups-container" style="max-height: 200px; overflow-y: auto;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                                                <?php
                                                $selectedGroups = [];
                                                if ($isEdit && !empty($newsletter['target_groups'])) {
                                                    $selectedGroups = json_decode($newsletter['target_groups'], true) ?? [];
                                                }
                                                foreach ($groups as $group):
                                                ?>
                                                    <label class="group-option" data-group="<?= strtolower(htmlspecialchars($group['name'])) ?>" style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px 14px; background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; transition: all 0.2s;">
                                                        <input type="checkbox" name="target_groups[]" value="<?= $group['id'] ?>"
                                                            <?= in_array($group['id'], $selectedGroups) ? 'checked' : '' ?>
                                                            style="accent-color: #f59e0b;">
                                                        <div>
                                                            <span style="font-weight: 500; color: #374151; display: block;"><?= htmlspecialchars($group['name']) ?></span>
                                                            <span style="font-size: 0.8rem; color: #6b7280;"><?= $group['member_count'] ?? 0 ?> members</span>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <small style="color: #6b7280; margin-top: 6px; display: block;">Select groups to target their members. Leave empty to send to all members.</small>
                                </div>
                                <?php else: ?>
                                <div style="background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center;">
                                    <i class="fa-solid fa-user-group" style="font-size: 1.5rem; color: #9ca3af; margin-bottom: 8px;"></i>
                                    <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">No groups available for targeting</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Scheduling -->
                    <div style="padding-top: 25px; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                            <div style="flex: 1; min-width: 280px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #374151;">
                                    <i class="fa-solid fa-clock" style="color: #f59e0b; margin-right: 6px;"></i>
                                    Schedule for Later (Optional)
                                </label>
                                <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                                    value="<?= ($isEdit && !empty($newsletter['scheduled_at'])) ? date('Y-m-d\TH:i', strtotime($newsletter['scheduled_at'])) : '' ?>"
                                    class="nexus-input" style="max-width: 300px; padding: 12px 14px; border-radius: 8px;"
                                    min="<?= date('Y-m-d\TH:i') ?>">
                                <small style="color: #6b7280; margin-top: 6px; display: block;">Leave empty to save as draft or send immediately.</small>
                            </div>

                            <!-- Send Time Recommendations -->
                            <div id="send-time-recommendations" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; border-radius: 12px; padding: 15px 20px; min-width: 280px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <i class="fa-solid fa-lightbulb" style="color: #16a34a;"></i>
                                    <span style="font-weight: 600; color: #166534; font-size: 0.9rem;">Best Times to Send</span>
                                    <a href="<?= $basePath ?>/admin/newsletters/send-time" target="_blank" style="margin-left: auto; color: #16a34a; font-size: 0.8rem; text-decoration: none;" title="View full analytics">
                                        <i class="fa-solid fa-chart-line"></i>
                                    </a>
                                </div>
                                <div id="recommended-times" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button type="button" onclick="applyRecommendedTime(9, 0)"
                                        class="send-time-btn"
                                        style="background: white; border: 1px solid #86efac; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; color: #166534; display: flex; align-items: center; gap: 5px; transition: all 0.2s;"
                                        onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='white'">
                                        <i class="fa-solid fa-sun" style="color: #f59e0b;"></i> 9:00 AM
                                    </button>
                                    <button type="button" onclick="applyRecommendedTime(10, 0)"
                                        class="send-time-btn"
                                        style="background: white; border: 1px solid #86efac; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; color: #166534; display: flex; align-items: center; gap: 5px; transition: all 0.2s;"
                                        onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='white'">
                                        10:00 AM
                                    </button>
                                    <button type="button" onclick="applyRecommendedTime(14, 0)"
                                        class="send-time-btn"
                                        style="background: white; border: 1px solid #86efac; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; color: #166534; display: flex; align-items: center; gap: 5px; transition: all 0.2s;"
                                        onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='white'">
                                        <i class="fa-solid fa-cloud-sun" style="color: #3b82f6;"></i> 2:00 PM
                                    </button>
                                </div>
                                <div style="color: #15803d; font-size: 0.75rem; margin-top: 8px;">
                                    <i class="fa-solid fa-info-circle"></i> Click a time to set it for scheduling
                                </div>
                            </div>
                        </div>

                        <?php if ($isEdit && !empty($newsletter['scheduled_at']) && $newsletter['status'] === 'scheduled'): ?>
                        <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 1px solid #fcd34d; padding: 14px 18px; border-radius: 10px; font-size: 0.9rem; color: #92400e; margin-top: 15px; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-calendar-check"></i>
                            <span><strong>Scheduled:</strong> Will be sent on <?= date('M j, Y \a\t g:i A', strtotime($newsletter['scheduled_at'])) ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Recurring Schedule Toggle -->
                        <div style="margin-top: 20px;">
                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 15px; background: #f9fafb; border-radius: 10px; transition: background 0.2s;">
                                <input type="checkbox" name="is_recurring" id="recurring-toggle" value="1"
                                    <?= ($isEdit && !empty($newsletter['is_recurring'])) ? 'checked' : '' ?>
                                    style="width: 20px; height: 20px; accent-color: #6366f1;"
                                    onchange="toggleRecurringOptions()">
                                <div>
                                    <span style="font-weight: 600; color: #111827;">
                                        <i class="fa-solid fa-repeat" style="color: #6366f1; margin-right: 6px;"></i>
                                        Make This a Recurring Newsletter
                                    </span>
                                    <span style="display: block; color: #6b7280; font-size: 0.85rem; margin-top: 2px;">
                                        Automatically send on a regular schedule (weekly digest, monthly updates, etc.)
                                    </span>
                                </div>
                            </label>
                        </div>

                        <!-- Recurring Options (hidden by default) -->
                        <div id="recurring-options" style="display: <?= ($isEdit && !empty($newsletter['is_recurring'])) ? 'block' : 'none' ?>; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); padding: 25px; border-radius: 12px; margin-top: 15px; border: 1px solid #93c5fd;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1e40af;">
                                        <i class="fa-solid fa-calendar-week" style="margin-right: 6px;"></i> Frequency
                                    </label>
                                    <select name="recurring_frequency" id="recurring-frequency" class="nexus-input" style="width: 100%; border-radius: 8px; padding: 10px 14px;" onchange="updateRecurringPreview()">
                                        <option value="">Select frequency...</option>
                                        <option value="daily" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'daily') ? 'selected' : '' ?>>Daily</option>
                                        <option value="weekly" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'weekly') ? 'selected' : '' ?>>Weekly</option>
                                        <option value="biweekly" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'biweekly') ? 'selected' : '' ?>>Every 2 Weeks</option>
                                        <option value="monthly" <?= ($isEdit && ($newsletter['recurring_frequency'] ?? '') === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1e40af;">
                                        <i class="fa-solid fa-clock" style="margin-right: 6px;"></i> Preferred Send Time
                                    </label>
                                    <input type="time" name="recurring_time" id="recurring-time"
                                        value="<?= ($isEdit && !empty($newsletter['recurring_time'])) ? $newsletter['recurring_time'] : '09:00' ?>"
                                        class="nexus-input" style="width: 100%; border-radius: 8px; padding: 10px 14px;"
                                        onchange="updateRecurringPreview()">
                                </div>
                            </div>

                            <!-- Day Selection (for weekly/biweekly) -->
                            <div id="recurring-day-select" style="display: none; margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #1e40af;">
                                    <i class="fa-solid fa-calendar-day" style="margin-right: 6px;"></i> Send On
                                </label>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php
                                    $days = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
                                    $selectedDay = ($isEdit && !empty($newsletter['recurring_day'])) ? $newsletter['recurring_day'] : 'mon';
                                    foreach ($days as $value => $label):
                                    ?>
                                    <label style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: <?= $selectedDay === $value ? '#3b82f6' : 'white' ?>; color: <?= $selectedDay === $value ? 'white' : '#374151' ?>; border-radius: 8px; cursor: pointer; border: 1px solid #bfdbfe; transition: all 0.2s;">
                                        <input type="radio" name="recurring_day" value="<?= $value ?>" <?= $selectedDay === $value ? 'checked' : '' ?> style="display: none;" onchange="this.parentElement.style.background='#3b82f6'; this.parentElement.style.color='white'; document.querySelectorAll('input[name=recurring_day]').forEach(r => { if(r !== this) { r.parentElement.style.background='white'; r.parentElement.style.color='#374151'; } }); updateRecurringPreview();">
                                        <?= $label ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Day of Month (for monthly) -->
                            <div id="recurring-monthday-select" style="display: none; margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1e40af;">
                                    <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i> Day of Month
                                </label>
                                <select name="recurring_day_of_month" id="recurring-day-of-month" class="nexus-input" style="max-width: 200px; border-radius: 8px; padding: 10px 14px;" onchange="updateRecurringPreview()">
                                    <?php for ($d = 1; $d <= 28; $d++): ?>
                                    <option value="<?= $d ?>" <?= ($isEdit && ($newsletter['recurring_day_of_month'] ?? 1) == $d) ? 'selected' : '' ?>><?= $d ?><?= $d == 1 ? 'st' : ($d == 2 ? 'nd' : ($d == 3 ? 'rd' : 'th')) ?></option>
                                    <?php endfor; ?>
                                    <option value="last" <?= ($isEdit && ($newsletter['recurring_day_of_month'] ?? '') === 'last') ? 'selected' : '' ?>>Last day of month</option>
                                </select>
                            </div>

                            <!-- Recurring End Date -->
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1e40af;">
                                    <i class="fa-solid fa-flag-checkered" style="margin-right: 6px;"></i> End Date (Optional)
                                </label>
                                <input type="date" name="recurring_end_date" id="recurring-end-date"
                                    value="<?= ($isEdit && !empty($newsletter['recurring_end_date'])) ? $newsletter['recurring_end_date'] : '' ?>"
                                    class="nexus-input" style="max-width: 200px; border-radius: 8px; padding: 10px 14px;"
                                    min="<?= date('Y-m-d') ?>">
                                <small style="color: #1e40af; margin-top: 6px; display: block;">Leave empty to continue indefinitely.</small>
                            </div>

                            <!-- Schedule Preview -->
                            <div id="recurring-preview" style="background: white; padding: 15px 20px; border-radius: 10px; border: 1px solid #bfdbfe;">
                                <div style="display: flex; align-items: center; gap: 10px; color: #1e40af;">
                                    <i class="fa-solid fa-info-circle"></i>
                                    <span id="recurring-preview-text">Select a frequency to see the schedule preview</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Newsletter Content Card -->
            <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
                <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 30px; border-bottom: 1px solid #e2e8f0;">
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-pen-fancy" style="color: #10b981;"></i>
                        Newsletter Content
                    </h3>
                </div>

                <div style="padding: 30px;">
                    <?php if (!$isEdit): ?>
                    <!-- Template Selector -->
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #374151;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #8b5cf6; margin-right: 6px;"></i>
                            Start with a Professional Template
                        </label>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" onclick="applyTemplate('blank')" class="template-btn active" data-template="blank"
                                style="padding: 12px 20px; border: 2px solid #6366f1; border-radius: 10px; background: #f5f3ff; cursor: pointer; font-size: 0.9rem; color: #4338ca; font-weight: 500; transition: all 0.2s;">
                                <i class="fa-solid fa-file" style="margin-right: 6px;"></i> Blank
                            </button>
                            <button type="button" onclick="applyTemplate('announcement')" class="template-btn" data-template="announcement"
                                style="padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 10px; background: white; cursor: pointer; font-size: 0.9rem; color: #374151; font-weight: 500; transition: all 0.2s;">
                                <i class="fa-solid fa-bullhorn" style="margin-right: 6px;"></i> Announcement
                            </button>
                            <button type="button" onclick="applyTemplate('update')" class="template-btn" data-template="update"
                                style="padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 10px; background: white; cursor: pointer; font-size: 0.9rem; color: #374151; font-weight: 500; transition: all 0.2s;">
                                <i class="fa-solid fa-newspaper" style="margin-right: 6px;"></i> Weekly Update
                            </button>
                            <button type="button" onclick="applyTemplate('event')" class="template-btn" data-template="event"
                                style="padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 10px; background: white; cursor: pointer; font-size: 0.9rem; color: #374151; font-weight: 500; transition: all 0.2s;">
                                <i class="fa-solid fa-calendar-star" style="margin-right: 6px;"></i> Event Invite
                            </button>
                            <button type="button" onclick="applyTemplate('welcome')" class="template-btn" data-template="welcome"
                                style="padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 10px; background: white; cursor: pointer; font-size: 0.9rem; color: #374151; font-weight: 500; transition: all 0.2s;">
                                <i class="fa-solid fa-hand-wave" style="margin-right: 6px;"></i> Welcome
                            </button>
                            <button type="button" onclick="applyTemplate('promotional')" class="template-btn" data-template="promotional"
                                style="padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 10px; background: white; cursor: pointer; font-size: 0.9rem; color: #374151; font-weight: 500; transition: all 0.2s;">
                                <i class="fa-solid fa-tags" style="margin-right: 6px;"></i> Promotional
                            </button>
                        </div>
                        <p style="color: #6b7280; font-size: 0.85rem; margin-top: 10px;">
                            <i class="fa-solid fa-info-circle" style="margin-right: 4px;"></i>
                            Templates include professional styling with gradients, buttons, and rich formatting
                        </p>
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <label style="font-weight: 600; color: #374151;">Content (HTML supported) *</label>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" onclick="generateAIContent()" class="ai-gen-btn" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 5px; transition: all 0.2s;">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Content
                                </button>
                                <button type="button" onclick="togglePreview()" id="preview-toggle" style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: #374151; display: flex; align-items: center; gap: 5px;">
                                    <i class="fa-solid fa-eye"></i> Preview
                                </button>
                            </div>
                        </div>

                        <!-- Quick Insert Toolbar -->
                        <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e5e7eb; border-bottom: none; border-radius: 10px 10px 0 0; padding: 10px 15px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                            <span style="font-size: 0.75rem; color: #6b7280; margin-right: 8px; font-weight: 500;">INSERT:</span>
                            <button type="button" onclick="insertElement('heading')" title="Insert Heading" style="background: white; border: 1px solid #e5e7eb; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #374151; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-heading" style="color: #6366f1;"></i> Heading
                            </button>
                            <button type="button" onclick="insertElement('paragraph')" title="Insert Paragraph" style="background: white; border: 1px solid #e5e7eb; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #374151; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-paragraph" style="color: #10b981;"></i> Paragraph
                            </button>
                            <button type="button" onclick="insertElement('button')" title="Insert CTA Button" style="background: white; border: 1px solid #e5e7eb; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #374151; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-square" style="color: #f59e0b;"></i> Button
                            </button>
                            <button type="button" onclick="insertElement('divider')" title="Insert Divider" style="background: white; border: 1px solid #e5e7eb; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #374151; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-minus" style="color: #94a3b8;"></i> Divider
                            </button>
                            <button type="button" onclick="insertElement('callout')" title="Insert Callout Box" style="background: white; border: 1px solid #e5e7eb; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #374151; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-quote-left" style="color: #8b5cf6;"></i> Callout
                            </button>
                            <button type="button" onclick="insertElement('list')" title="Insert List" style="background: white; border: 1px solid #e5e7eb; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #374151; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-list" style="color: #ec4899;"></i> List
                            </button>
                            <button type="button" onclick="triggerImageUpload()" title="Upload Image" style="background: white; border: 1px solid #e5e7eb; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #374151; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-image" style="color: #0ea5e9;"></i> Image
                            </button>
                            <input type="file" id="image-upload-input" accept="image/*" style="display: none;" onchange="handleImageUpload(this)">
                            <div style="border-left: 1px solid #d1d5db; height: 20px; margin: 0 6px;"></div>
                            <button type="button" onclick="insertToken('first_name')" title="Insert First Name" style="background: #dbeafe; border: 1px solid #93c5fd; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #1e40af; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-user"></i> Name
                            </button>
                            <div style="border-left: 1px solid #d1d5db; height: 20px; margin: 0 6px;"></div>
                            <span style="font-size: 0.75rem; color: #6b7280; margin-right: 4px; font-weight: 500;">DYNAMIC:</span>
                            <button type="button" onclick="insertDynamicBlock('recent_listings')" title="Recent Listings" style="background: #dcfce7; border: 1px solid #86efac; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #166534; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-list-check"></i> Listings
                            </button>
                            <button type="button" onclick="insertDynamicBlock('community_stats')" title="Community Stats" style="background: #dcfce7; border: 1px solid #86efac; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #166534; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-chart-simple"></i> Stats
                            </button>
                            <button type="button" onclick="insertDynamicBlock('member_spotlight')" title="Member Spotlight" style="background: #dcfce7; border: 1px solid #86efac; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; color: #166534; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-star"></i> Spotlight
                            </button>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 0;" id="editor-container">
                            <textarea name="content" id="content-editor"><?= $isEdit ? htmlspecialchars($newsletter['content']) : '' ?></textarea>
                        </div>

                        <script>
                        // Wait for TinyMCE to load (with fallback for local dev)
                        if (typeof tinymce !== 'undefined') {
                            tinymce.init({
                            selector: '#content-editor',
                            height: 500,
                            menubar: true,
                            // Prevent TinyMCE from converting URLs to relative paths
                            relative_urls: false,
                            remove_script_host: false,
                            convert_urls: false,
                            plugins: [
                                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
                            ],
                            toolbar: 'undo redo | styles | bold italic underline strikethrough | ' +
                                'alignleft aligncenter alignright alignjustify | ' +
                                'bullist numlist outdent indent | link image media | ' +
                                'forecolor backcolor | emoticons | removeformat | code fullscreen help',
                            style_formats: [
                                { title: 'Heading 1', block: 'h1' },
                                { title: 'Heading 2', block: 'h2' },
                                { title: 'Heading 3', block: 'h3' },
                                { title: 'Paragraph', block: 'p' },
                                { title: 'Blockquote', block: 'blockquote' },
                                { title: 'Code', inline: 'code' }
                            ],
                            content_style: `
                                body {
                                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                                    font-size: 16px;
                                    line-height: 1.6;
                                    color: #333;
                                    max-width: 600px;
                                    margin: 0 auto;
                                    padding: 20px;
                                }
                                h1, h2, h3 { color: #111827; margin-top: 0; }
                                a { color: #6366f1; }
                                img { max-width: 100%; height: auto; }
                                blockquote { border-left: 4px solid #6366f1; margin: 1em 0; padding-left: 1em; color: #666; }
                            `,
                            automatic_uploads: true,
                            file_picker_types: 'image',
                            images_upload_handler: function (blobInfo, progress) {
                                return new Promise((resolve, reject) => {
                                    // For now, convert to base64 (you can add server upload later)
                                    const reader = new FileReader();
                                    reader.onload = function() {
                                        resolve(reader.result);
                                    };
                                    reader.onerror = function() {
                                        reject('Image upload failed');
                                    };
                                    reader.readAsDataURL(blobInfo.blob());
                                });
                            },
                            setup: function(editor) {
                                editor.on('change', function() {
                                    editor.save();
                                    updatePreview();
                                });
                                editor.on('init', function() {
                                    // Add custom buttons for newsletter tokens
                                    updatePreview();
                                });
                            },
                            promotion: false,
                            branding: false
                        });
                        } else {
                            console.warn('[Newsletter Editor] TinyMCE not loaded - using plain textarea. This is normal on localhost without API key.');
                            // Show a notice to the user
                            const notice = document.createElement('div');
                            notice.style.cssText = 'background: #fef3c7; border: 1px solid #fbbf24; color: #92400e; padding: 12px; border-radius: 8px; margin-bottom: 12px;';
                            notice.innerHTML = '<strong>Note:</strong> Rich text editor (TinyMCE) is not available. Using plain textarea. Add TINYMCE_API_KEY to .env for rich text editing.';
                            document.getElementById('editor-container').insertBefore(notice, document.getElementById('content-editor'));
                        }
                        </script>

                        <!-- Live Preview Panel (hidden by default) -->
                        <div id="preview-panel" style="display: none; margin-top: 20px;">
                            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                                <div style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%); padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 12px; height: 12px; background: #ef4444; border-radius: 50%;"></div>
                                        <div style="width: 12px; height: 12px; background: #f59e0b; border-radius: 50%;"></div>
                                        <div style="width: 12px; height: 12px; background: #10b981; border-radius: 50%;"></div>
                                        <span style="color: #9ca3af; font-size: 0.85rem; margin-left: 10px;">Email Preview</span>
                                    </div>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" onclick="setPreviewMode('desktop')" id="preview-desktop" style="background: #374151; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: white; font-size: 0.8rem;" title="Desktop (600px)">
                                            <i class="fa-solid fa-desktop"></i>
                                        </button>
                                        <button type="button" onclick="setPreviewMode('tablet')" id="preview-tablet" style="background: transparent; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: #9ca3af; font-size: 0.8rem;" title="Tablet (768px)">
                                            <i class="fa-solid fa-tablet-screen-button"></i>
                                        </button>
                                        <button type="button" onclick="setPreviewMode('mobile')" id="preview-mobile" style="background: transparent; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: #9ca3af; font-size: 0.8rem;" title="Mobile (375px)">
                                            <i class="fa-solid fa-mobile-screen"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Email Client Preview Tabs -->
                                <div style="background: #1f2937; padding: 8px 20px; display: flex; gap: 6px; border-top: 1px solid #374151;">
                                    <span style="color: #9ca3af; font-size: 0.75rem; margin-right: 8px; align-self: center;">Client:</span>
                                    <button type="button" onclick="setClientPreview('raw')" id="client-raw" style="background: #374151; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: white; font-size: 0.75rem;" title="Raw HTML Preview">
                                        Raw
                                    </button>
                                    <button type="button" onclick="setClientPreview('gmail')" id="client-gmail" style="background: transparent; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: #9ca3af; font-size: 0.75rem;" title="Gmail Preview">
                                        <i class="fa-brands fa-google" style="margin-right: 3px;"></i> Gmail
                                    </button>
                                    <button type="button" onclick="setClientPreview('outlook')" id="client-outlook" style="background: transparent; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: #9ca3af; font-size: 0.75rem;" title="Outlook Preview">
                                        <i class="fa-brands fa-microsoft" style="margin-right: 3px;"></i> Outlook
                                    </button>
                                    <button type="button" onclick="setClientPreview('apple')" id="client-apple" style="background: transparent; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: #9ca3af; font-size: 0.75rem;" title="Apple Mail Preview">
                                        <i class="fa-brands fa-apple" style="margin-right: 3px;"></i> Apple
                                    </button>
                                    <button type="button" onclick="setClientPreview('mobile')" id="client-mobile-sim" style="background: transparent; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; color: #9ca3af; font-size: 0.75rem;" title="Mobile Phone Preview">
                                        <i class="fa-solid fa-mobile-screen" style="margin-right: 3px;"></i> Phone
                                    </button>
                                </div>
                                <div style="background: #e5e7eb; padding: 20px; display: flex; justify-content: center;">
                                    <iframe id="preview-frame" style="width: 100%; max-width: 620px; height: 500px; border: none; background: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); padding: 20px; border-radius: 12px; font-size: 0.9rem; color: #4b5563; border: 1px solid #e5e7eb;">
                            <strong style="display: block; margin-bottom: 10px; color: #374151;">
                                <i class="fa-solid fa-lightbulb" style="color: #f59e0b; margin-right: 6px;"></i>
                                Formatting Tips:
                            </strong>
                            <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                                <li>Use <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">&lt;h2&gt;</code> for section headings</li>
                                <li>Use <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">&lt;p&gt;</code> for paragraphs</li>
                                <li>Use <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">&lt;a href="..."&gt;</code> for clickable links</li>
                                <li>Use <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">&lt;img src="..." alt="..."&gt;</code> for images</li>
                            </ul>
                        </div>
                        <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); padding: 20px; border-radius: 12px; font-size: 0.9rem; color: #1e40af; border: 1px solid #bfdbfe;">
                            <strong style="display: block; margin-bottom: 10px; color: #1e40af;">
                                <i class="fa-solid fa-user-tag" style="color: #3b82f6; margin-right: 6px;"></i>
                                Personalization Tokens:
                            </strong>
                            <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                                <li><code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px;">{{first_name}}</code> - Recipient's first name</li>
                                <li><code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px;">{{last_name}}</code> - Recipient's last name</li>
                                <li><code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px;">{{full_name}}</code> - Full name</li>
                                <li><code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px;">{{email}}</code> - Email address</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Card -->
            <div class="nexus-card" style="padding: 25px 30px; border-radius: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
                            <button type="button" onclick="confirmDeleteNewsletter()" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-trash"></i> Delete Newsletter
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <?php if ($isEdit): ?>
                            <a href="<?= $basePath ?>/admin/newsletters/preview/<?= $newsletter['id'] ?>" target="_blank"
                                style="background: #f1f5f9; color: #475569; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                                <i class="fa-solid fa-eye"></i> Preview
                            </a>

                            <?php if ($newsletter['status'] !== 'sent'): ?>
                                <button type="button" id="send-test-btn" onclick="sendTestEmail()"
                                    style="background: #f1f5f9; color: #475569; padding: 12px 20px; border-radius: 10px; border: none; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                                    <i class="fa-solid fa-paper-plane"></i> Send Test
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a href="<?= $basePath ?>/admin/newsletters"
                            style="background: #f1f5f9; color: #475569; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                            Cancel
                        </a>

                        <button type="submit"
                            style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4); transition: all 0.2s;">
                            <i class="fa-solid fa-save"></i> <?= $isEdit ? 'Save Changes' : 'Create Draft' ?>
                        </button>

                        <?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
                            <button type="button" onclick="confirmSend()"
                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); transition: all 0.2s;">
                                <i class="fa-solid fa-rocket"></i> Send Now (<span id="send-count"><?= number_format($eligibleCount) ?></span>)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <div id="recipient-count" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 0.9rem; color: #6b7280; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-users" style="color: #6366f1;"></i>
                        This newsletter will be sent to <strong style="color: #111827; margin: 0 4px;"><?= number_format($eligibleCount) ?></strong> eligible recipients.
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
        <!-- Delete Form (outside main form to avoid nesting) -->
        <form id="delete-newsletter-form" action="<?= $basePath ?>/admin/newsletters/delete" method="POST" style="display: none;">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="id" value="<?= $newsletter['id'] ?>">
        </form>
        <script>
        function confirmDeleteNewsletter() {
            if (confirm('Are you sure you want to delete this newsletter?\n\nThis action cannot be undone.')) {
                document.getElementById('delete-newsletter-form').submit();
            }
        }
        </script>
        <?php endif; ?>
    </div>
</div>

<?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
<!-- Send Confirmation Modal -->
<div id="send-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; padding: 35px; border-radius: 20px; max-width: 480px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fa-solid fa-rocket" style="font-size: 1.5rem; color: white;"></i>
            </div>
            <h3 style="margin: 0 0 10px; font-size: 1.3rem; color: #111827;">Send Newsletter Now?</h3>
        </div>

        <p style="color: #4b5563; margin-bottom: 20px; text-align: center; line-height: 1.6;">
            This will immediately send "<strong><?= htmlspecialchars($newsletter['subject']) ?></strong>"
            to <strong id="modal-count" style="color: #10b981;"><?= number_format($eligibleCount) ?></strong> recipients.
        </p>

        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 14px 18px; border-radius: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #dc2626;"></i>
            <span style="color: #991b1b; font-size: 0.9rem;">This action cannot be undone.</span>
        </div>

        <div id="save-reminder" style="background: #fef9c3; border: 1px solid #fde047; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-info-circle" style="color: #ca8a04;"></i>
            <span style="color: #854d0e; font-size: 0.9rem;">Your changes will be saved before sending.</span>
        </div>

        <div style="display: flex; gap: 12px; justify-content: center;">
            <button onclick="closeModal()"
                style="background: #f1f5f9; color: #475569; padding: 12px 24px; border-radius: 10px; border: none; font-weight: 500; cursor: pointer;">
                Cancel
            </button>
            <button type="button" id="send-now-btn" onclick="saveAndSend()"
                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 28px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">
                <i class="fa-solid fa-paper-plane" style="margin-right: 6px;"></i> Yes, Send Now
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
console.log('Newsletter form.php script loaded - v2025-12-31');

// ============================================
// SEND TIME OPTIMIZATION
// ============================================

// Apply recommended send time to the scheduler
function applyRecommendedTime(hour, minute) {
    var scheduledAt = document.getElementById('scheduled_at');
    if (!scheduledAt) return;

    // Get tomorrow's date (recommend scheduling for at least tomorrow)
    var date = new Date();
    date.setDate(date.getDate() + 1);

    // If the hour has already passed today, use tomorrow; otherwise use today
    var now = new Date();
    if (now.getHours() < hour) {
        date = now;
    }

    // Format the date as YYYY-MM-DDTHH:MM
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    var formattedHour = String(hour).padStart(2, '0');
    var formattedMinute = String(minute).padStart(2, '0');

    scheduledAt.value = year + '-' + month + '-' + day + 'T' + formattedHour + ':' + formattedMinute;

    // Highlight the input briefly
    scheduledAt.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.3)';
    setTimeout(function() {
        scheduledAt.style.boxShadow = '';
    }, 1500);
}

// Load personalized send time recommendations from API
function loadSendTimeRecommendations() {
    var container = document.getElementById('recommended-times');
    if (!container) return;

    fetch('<?= $basePath ?>/admin/newsletters/send-time-recommendations')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.data && data.data.has_data && data.data.recommendations) {
                // Build personalized recommendation buttons
                var html = '';
                var recs = data.data.recommendations.slice(0, 3);
                var icons = ['fa-star', 'fa-sun', 'fa-clock'];
                var colors = ['#f59e0b', '#6366f1', '#3b82f6'];

                for (var i = 0; i < recs.length; i++) {
                    var rec = recs[i];
                    html += '<button type="button" onclick="applyRecommendedTime(' + rec.hour + ', 0)" ' +
                        'class="send-time-btn" ' +
                        'style="background: white; border: 1px solid #86efac; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; color: #166534; display: flex; align-items: center; gap: 5px; transition: all 0.2s;" ' +
                        'onmouseover="this.style.background=\'#dcfce7\'" onmouseout="this.style.background=\'white\'" ' +
                        'title="' + rec.percentage + '% of engagement">' +
                        '<i class="fa-solid ' + icons[i] + '" style="color: ' + colors[i] + ';"></i> ' + rec.time +
                        '</button>';
                }

                container.innerHTML = html;
            }
        })
        .catch(function(err) {
            console.log('Could not load send time recommendations:', err);
        });
}

// Load recommendations on page load
document.addEventListener('DOMContentLoaded', loadSendTimeRecommendations);

// Targeting section toggle
function toggleTargeting() {
    var section = document.getElementById('targeting-section');
    var icon = document.getElementById('targeting-icon');
    var btnText = document.getElementById('targeting-btn-text');

    if (!section) {
        return;
    }

    if (section.style.display === 'none' || section.style.display === '') {
        section.style.display = 'block';
        if (icon) icon.className = 'fa-solid fa-minus';
        if (btnText) btnText.textContent = 'Hide Filters';
    } else {
        section.style.display = 'none';
        if (icon) icon.className = 'fa-solid fa-plus';
        if (btnText) btnText.textContent = 'Add Filters';
    }
}

// Town search filter
function filterTowns(query) {
    var q = query.toLowerCase();
    var options = document.querySelectorAll('.town-option');
    for (var i = 0; i < options.length; i++) {
        var el = options[i];
        var town = el.getAttribute('data-town') || '';
        if (town.indexOf(q) !== -1) {
            el.style.display = 'flex';
        } else {
            el.style.display = 'none';
        }
    }
}

function filterCounties(query) {
    var q = query.toLowerCase();
    var options = document.querySelectorAll('.county-option');
    for (var i = 0; i < options.length; i++) {
        var el = options[i];
        var county = el.getAttribute('data-county') || '';
        if (county.indexOf(q) !== -1) {
            el.style.display = 'flex';
        } else {
            el.style.display = 'none';
        }
    }
}

function filterGroups(query) {
    var q = query.toLowerCase();
    var options = document.querySelectorAll('.group-option');
    for (var i = 0; i < options.length; i++) {
        var el = options[i];
        var group = el.getAttribute('data-group') || '';
        if (group.indexOf(q) !== -1) {
            el.style.display = 'flex';
        } else {
            el.style.display = 'none';
        }
    }
}

// ============================================
// RECURRING SCHEDULE FUNCTIONS
// ============================================

function toggleRecurringOptions() {
    var toggle = document.getElementById('recurring-toggle');
    var options = document.getElementById('recurring-options');
    if (toggle && options) {
        options.style.display = toggle.checked ? 'block' : 'none';
        if (toggle.checked) {
            updateRecurringPreview();
        }
    }
}

function updateRecurringPreview() {
    var frequency = document.getElementById('recurring-frequency');
    var time = document.getElementById('recurring-time');
    var daySelect = document.getElementById('recurring-day-select');
    var monthDaySelect = document.getElementById('recurring-monthday-select');
    var previewText = document.getElementById('recurring-preview-text');

    if (!frequency || !previewText) return;

    var freq = frequency.value;
    var timeVal = time ? time.value : '09:00';

    // Format time for display
    var timeParts = timeVal.split(':');
    var hours = parseInt(timeParts[0]);
    var minutes = timeParts[1];
    var ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    var formattedTime = hours + ':' + minutes + ' ' + ampm;

    // Show/hide day selectors based on frequency
    if (daySelect) daySelect.style.display = (freq === 'weekly' || freq === 'biweekly') ? 'block' : 'none';
    if (monthDaySelect) monthDaySelect.style.display = (freq === 'monthly') ? 'block' : 'none';

    // Build preview text
    var preview = '';
    switch (freq) {
        case 'daily':
            preview = 'This newsletter will be sent every day at ' + formattedTime;
            break;
        case 'weekly':
            var selectedDay = document.querySelector('input[name="recurring_day"]:checked');
            var dayName = selectedDay ? getDayName(selectedDay.value) : 'Monday';
            preview = 'This newsletter will be sent every ' + dayName + ' at ' + formattedTime;
            break;
        case 'biweekly':
            var selectedDay = document.querySelector('input[name="recurring_day"]:checked');
            var dayName = selectedDay ? getDayName(selectedDay.value) : 'Monday';
            preview = 'This newsletter will be sent every other ' + dayName + ' at ' + formattedTime;
            break;
        case 'monthly':
            var dayOfMonth = document.getElementById('recurring-day-of-month');
            var day = dayOfMonth ? dayOfMonth.value : '1';
            var dayDisplay = day === 'last' ? 'the last day' : 'the ' + getOrdinal(day);
            preview = 'This newsletter will be sent on ' + dayDisplay + ' of each month at ' + formattedTime;
            break;
        default:
            preview = 'Select a frequency to see the schedule preview';
    }

    previewText.textContent = preview;
}

function getDayName(abbr) {
    var days = {
        'mon': 'Monday', 'tue': 'Tuesday', 'wed': 'Wednesday',
        'thu': 'Thursday', 'fri': 'Friday', 'sat': 'Saturday', 'sun': 'Sunday'
    };
    return days[abbr] || 'Monday';
}

function getOrdinal(n) {
    var s = ['th', 'st', 'nd', 'rd'];
    var v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

// Initialize recurring options on page load
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('recurring-toggle');
    if (toggle && toggle.checked) {
        updateRecurringPreview();
    }
});

// ============================================
// CONFIGURATION
// ============================================

// Audience counts for dynamic updates
var audienceCounts = {
    'all_members': <?= isset($audienceCounts['all_members']) ? (int)$audienceCounts['all_members'] : 0 ?>,
    'subscribers_only': <?= isset($audienceCounts['subscribers_only']) ? (int)$audienceCounts['subscribers_only'] : 0 ?>,
    'both': <?= isset($audienceCounts['both']) ? (int)$audienceCounts['both'] : 0 ?>
};

// Tenant name for previews
var tenantName = 'Newsletter';

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function hasActiveTargeting() {
    // Check if any targeting filters are selected
    var hasCounties = document.querySelectorAll('input[name="target_counties[]"]:checked').length > 0;
    var hasTowns = document.querySelectorAll('input[name="target_towns[]"]:checked').length > 0;
    var hasGroups = document.querySelectorAll('input[name="target_groups[]"]:checked').length > 0;
    return hasCounties || hasTowns || hasGroups;
}

function updateAudienceDisplay() {
    const selected = document.querySelector('input[name="target_audience"]:checked');
    if (!selected) return;

    const baseCount = audienceCounts[selected.value] || 0;
    const hasFilters = hasActiveTargeting();

    // Update send button count - show "filtered" indicator when targeting is active
    const sendCount = document.getElementById('send-count');
    if (sendCount) {
        if (hasFilters) {
            sendCount.innerHTML = '<i class="fa-solid fa-filter" style="font-size: 0.8em;"></i> Filtered';
        } else {
            sendCount.textContent = formatNumber(baseCount);
        }
    }

    // Update modal count
    const modalCount = document.getElementById('modal-count');
    if (modalCount) {
        if (hasFilters) {
            modalCount.innerHTML = 'filtered subset of ' + formatNumber(baseCount);
        } else {
            modalCount.textContent = formatNumber(baseCount);
        }
    }

    // Update hidden field
    const modalTargetAudience = document.getElementById('modal-target-audience');
    if (modalTargetAudience) modalTargetAudience.value = selected.value;

    // Update radio button styles
    document.querySelectorAll('input[name="target_audience"]').forEach(function(radio) {
        const label = radio.closest('label');
        if (radio.checked) {
            label.style.borderColor = '#6366f1';
            label.style.background = '#f5f3ff';
        } else {
            label.style.borderColor = '#e5e7eb';
            label.style.background = 'white';
        }
    });
}

// Listen for audience changes
document.querySelectorAll('input[name="target_audience"]').forEach(function(radio) {
    radio.addEventListener('change', updateAudienceDisplay);
});

// Listen for targeting filter changes (counties, towns, groups)
document.querySelectorAll('input[name="target_counties[]"], input[name="target_towns[]"], input[name="target_groups[]"]').forEach(function(checkbox) {
    checkbox.addEventListener('change', updateAudienceDisplay);
});

// Initial update on page load
document.addEventListener('DOMContentLoaded', updateAudienceDisplay);

<?php if ($isEdit && $newsletter['status'] !== 'sent'): ?>
function confirmSend() {
    document.getElementById('send-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('send-modal').style.display = 'none';
}

function saveAndSend() {
    const btn = document.getElementById('send-now-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving & Sending...';

    const form = document.getElementById('newsletter-form');
    const formData = new FormData(form);
    // Add flag to indicate we want to send after saving
    formData.append('send_after_save', '1');

    fetch('<?= $basePath ?>/admin/newsletters/update/<?= $newsletter['id'] ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // After save, redirect to send endpoint
        window.location.href = '<?= $basePath ?>/admin/newsletters/send-direct/<?= $newsletter['id'] ?>';
    })
    .catch(error => {
        alert('Error saving: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane" style="margin-right: 6px;"></i> Yes, Send Now';
    });
}

function sendTestEmail() {
    const btn = document.getElementById('send-test-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

    const form = document.getElementById('newsletter-form');
    const formData = new FormData(form);

    fetch('<?= $basePath ?>/admin/newsletters/send-test/<?= $newsletter['id'] ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
        } else {
            alert('Error: ' + (data.error || 'Failed to send test email'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Test';
    });
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
<?php endif; ?>

// Subject line character counter with quality indicator
function updateSubjectCounter() {
    const input = document.getElementById('subject-input');
    const lengthSpan = document.getElementById('subject-length');
    const qualitySpan = document.getElementById('subject-quality');
    if (!input || !lengthSpan) return;

    const len = input.value.length;
    lengthSpan.textContent = len;

    // Update counter color based on length
    const counter = document.getElementById('subject-counter');
    if (len > 100) {
        counter.style.color = '#dc2626';
    } else if (len > 60) {
        counter.style.color = '#f59e0b';
    } else {
        counter.style.color = '#6b7280';
    }

    // Quality indicator
    if (qualitySpan) {
        if (len === 0) {
            qualitySpan.textContent = '';
            qualitySpan.style.background = 'transparent';
        } else if (len < 20) {
            qualitySpan.textContent = 'Too short';
            qualitySpan.style.background = '#fef3c7';
            qualitySpan.style.color = '#92400e';
        } else if (len >= 20 && len <= 60) {
            qualitySpan.textContent = 'Optimal';
            qualitySpan.style.background = '#d1fae5';
            qualitySpan.style.color = '#065f46';
        } else if (len > 60 && len <= 100) {
            qualitySpan.textContent = 'Good';
            qualitySpan.style.background = '#dbeafe';
            qualitySpan.style.color = '#1e40af';
        } else {
            qualitySpan.textContent = 'May truncate';
            qualitySpan.style.background = '#fee2e2';
            qualitySpan.style.color = '#991b1b';
        }
    }
}

// Initialize subject counter on load
document.addEventListener('DOMContentLoaded', updateSubjectCounter);

// Preview panel toggle
let previewVisible = false;
function togglePreview() {
    const panel = document.getElementById('preview-panel');
    const btn = document.getElementById('preview-toggle');
    previewVisible = !previewVisible;

    if (previewVisible) {
        panel.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide Preview';
        btn.style.background = '#6366f1';
        btn.style.color = 'white';
        btn.style.borderColor = '#6366f1';
        updatePreview();
    } else {
        panel.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Preview';
        btn.style.background = '#f3f4f6';
        btn.style.color = '#374151';
        btn.style.borderColor = '#e5e7eb';
    }
}

// Preview mode (desktop/tablet/mobile)
function setPreviewMode(mode) {
    const frame = document.getElementById('preview-frame');
    const desktopBtn = document.getElementById('preview-desktop');
    const tabletBtn = document.getElementById('preview-tablet');
    const mobileBtn = document.getElementById('preview-mobile');

    // Reset all buttons
    [desktopBtn, tabletBtn, mobileBtn].forEach(btn => {
        if (btn) {
            btn.style.background = 'transparent';
            btn.style.color = '#9ca3af';
        }
    });

    // Set active button and frame size
    if (mode === 'mobile') {
        frame.style.maxWidth = '375px';
        frame.style.height = '667px'; // iPhone size
        if (mobileBtn) {
            mobileBtn.style.background = '#374151';
            mobileBtn.style.color = 'white';
        }
    } else if (mode === 'tablet') {
        frame.style.maxWidth = '768px';
        frame.style.height = '550px';
        if (tabletBtn) {
            tabletBtn.style.background = '#374151';
            tabletBtn.style.color = 'white';
        }
    } else {
        // Desktop (email standard width)
        frame.style.maxWidth = '620px';
        frame.style.height = '500px';
        if (desktopBtn) {
            desktopBtn.style.background = '#374151';
            desktopBtn.style.color = 'white';
        }
    }
}

// ============================================
// EMAIL CLIENT PREVIEW
// ============================================
var currentClientPreview = 'raw';

function setClientPreview(client) {
    currentClientPreview = client;

    // Update button states
    const buttons = ['client-raw', 'client-gmail', 'client-outlook', 'client-apple', 'client-mobile-sim'];
    buttons.forEach(function(btnId) {
        const btn = document.getElementById(btnId);
        if (btn) {
            if (btnId === 'client-' + client || (client === 'mobile' && btnId === 'client-mobile-sim')) {
                btn.style.background = '#374151';
                btn.style.color = 'white';
            } else {
                btn.style.background = 'transparent';
                btn.style.color = '#9ca3af';
            }
        }
    });

    // If raw, use local preview; otherwise fetch from server
    if (client === 'raw') {
        updatePreview();
    } else {
        loadClientPreview(client);
    }
}

function loadClientPreview(client) {
    <?php if ($isEdit): ?>
    const newsletterId = <?= $newsletter['id'] ?>;
    const frame = document.getElementById('preview-frame');

    if (!frame) return;

    // Show loading state
    frame.contentDocument.body.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-family: sans-serif; color: #6b7280;"><div style="text-align: center;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px;"></i><br>Loading ' + client + ' preview...</div></div>';

    fetch('<?= $basePath ?>/admin/newsletters/client-preview/' + newsletterId + '?client=' + client)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.html) {
                frame.contentDocument.open();
                frame.contentDocument.write(data.html);
                frame.contentDocument.close();

                // Adjust frame size for mobile simulation
                if (client === 'mobile') {
                    frame.style.maxWidth = '420px';
                    frame.style.height = '750px';
                } else {
                    frame.style.maxWidth = '800px';
                    frame.style.height = '600px';
                }
            } else {
                frame.contentDocument.body.innerHTML = '<div style="padding: 20px; color: #dc2626;">Error loading preview: ' + (data.error || 'Unknown error') + '</div>';
            }
        })
        .catch(function(err) {
            frame.contentDocument.body.innerHTML = '<div style="padding: 20px; color: #dc2626;">Error loading preview: ' + err.message + '</div>';
        });
    <?php else: ?>
    // For new newsletters, show a message that they need to save first
    const frame = document.getElementById('preview-frame');
    if (frame && frame.contentDocument) {
        frame.contentDocument.body.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-family: sans-serif; color: #6b7280; text-align: center; padding: 40px;"><div><i class="fa-solid fa-save" style="font-size: 3rem; margin-bottom: 15px; color: #d1d5db;"></i><br><strong>Save First</strong><br><span style="font-size: 0.9rem;">Save your newsletter to preview how it looks in different email clients.</span></div></div>';
    }
    <?php endif; ?>
}

// Image upload handling
function triggerImageUpload() {
    document.getElementById('image-upload-input').click();
}

function handleImageUpload(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];

    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file.');
        return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Image size must be less than 5MB.');
        return;
    }

    // Show loading indicator
    const uploadBtn = document.querySelector('[onclick="triggerImageUpload()"]');
    const originalHTML = uploadBtn.innerHTML;
    uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color: #0ea5e9;"></i> Uploading...';
    uploadBtn.disabled = true;

    // Create FormData and upload
    const formData = new FormData();
    formData.append('files', file);

    fetch(basePath + '/api/upload', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.data && data.data.length > 0) {
            const imageUrl = data.data[0];
            insertImageHtml(imageUrl, file.name);
        } else if (data.error) {
            alert('Upload failed: ' + data.error);
        } else {
            alert('Upload failed. Please try again.');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        alert('Upload failed. Please check your connection and try again.');
    })
    .finally(() => {
        // Reset button
        uploadBtn.innerHTML = originalHTML;
        uploadBtn.disabled = false;
        input.value = ''; // Clear file input
    });
}

function insertImageHtml(url, altText) {
    const imageHtml = `<div style="text-align: center; margin: 20px 0;">
    <img src="${url}" alt="${altText || 'Newsletter image'}" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
</div>`;

    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').insertContent(imageHtml);
    } else {
        // Fallback for plain textarea
        const editor = document.getElementById('content-editor');
        if (editor) {
            editor.value += imageHtml;
        }
    }
    updatePreview();
}

// Quick insert elements
const insertElements = {
    heading: `<h2 style="color: #1f2937; font-size: 22px; font-weight: 700; margin: 25px 0 15px; line-height: 1.3;">Your Heading Here</h2>`,

    paragraph: `<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 16px;">
Your paragraph text goes here. Write something engaging for your readers.
</p>`,

    button: `<div style="text-align: center; margin: 30px 0;">
    <a href="https://your-link-here.com" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; font-weight: 600; font-size: 16px; padding: 14px 28px; border-radius: 10px; text-decoration: none;">Button Text</a>
</div>`,

    divider: `<hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 25px 0;">`,

    callout: `<div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 12px; padding: 20px 25px; margin: 20px 0; border-left: 4px solid #6366f1;">
    <p style="margin: 0; font-size: 15px; color: #4b5563; line-height: 1.6;">
        <strong style="color: #4f46e5;">Note:</strong> Your callout message here. Use this to highlight important information.
    </p>
</div>`,

    list: `<ul style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 20px; padding-left: 24px;">
    <li style="margin-bottom: 8px;">First item</li>
    <li style="margin-bottom: 8px;">Second item</li>
    <li style="margin-bottom: 8px;">Third item</li>
</ul>`
};

function insertElement(type) {
    const element = insertElements[type];
    if (!element) return;

    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').insertContent(element);
    } else {
        // Fallback for plain textarea
        const editor = document.getElementById('content-editor');
        if (editor) {
            editor.value += element;
        }
    }
    updatePreview();
}

function insertToken(token) {
    const tokenText = '{{' + token + '}}';

    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').insertContent(tokenText);
    } else {
        // Fallback for plain textarea
        const editor = document.getElementById('content-editor');
        if (editor) {
            editor.value += tokenText;
        }
    }
    updatePreview();
}

function insertDynamicBlock(blockType) {
    let blockText = '';

    switch (blockType) {
        case 'recent_listings':
            blockText = '[[recent_listings:5]]';
            break;
        case 'upcoming_events':
            blockText = '[[upcoming_events:5]]';
            break;
        case 'member_spotlight':
            blockText = '[[member_spotlight]]';
            break;
        case 'community_stats':
            blockText = '[[community_stats]]';
            break;
        case 'quick_links':
            blockText = '[[quick_links]]';
            break;
        default:
            blockText = '[[' + blockType + ']]';
    }

    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').insertContent('<p>' + blockText + '</p>');
    } else {
        // Fallback for plain textarea
        const editor = document.getElementById('content-editor');
        if (editor) {
            editor.value += '\n' + blockText + '\n';
        }
    }
    updatePreview();
}

// Live preview with debounce
let previewTimeout;
function updatePreviewDebounced() {
    clearTimeout(previewTimeout);
    previewTimeout = setTimeout(updatePreview, 300);
}

function updatePreview() {
    const previewFrame = document.getElementById('preview-frame');
    if (!previewFrame || !previewVisible) return;

    // Get content from TinyMCE if available, otherwise from textarea
    let content = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        content = tinymce.get('content-editor').getContent();
    } else {
        content = document.getElementById('content-editor').value;
    }
    const subject = document.getElementById('subject-input')?.value || 'Newsletter Preview';

    const html = '<!DOCTYPE html>' +
'<html>' +
'<head>' +
'    <style>' +
'        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }' +
'        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }' +
'        .header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 30px; text-align: center; color: white; }' +
'        .header h1 { margin: 0; font-size: 24px; font-weight: 700; }' +
'        .content { padding: 40px 30px; color: #374151; line-height: 1.8; font-size: 16px; }' +
'        .content h1, .content h2, .content h3 { color: #1f2937; }' +
'        .content h2 { font-size: 22px; margin-top: 24px; margin-bottom: 12px; }' +
'        .content h3 { font-size: 18px; margin-top: 20px; margin-bottom: 10px; }' +
'        .content p { margin: 0 0 16px 0; }' +
'        .content a { color: #6366f1; }' +
'        .content img { max-width: 100%; border-radius: 8px; }' +
'        .content ul, .content ol { padding-left: 24px; }' +
'        .content li { margin-bottom: 8px; }' +
'        .footer { background: #f9fafb; padding: 20px; text-align: center; color: #9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb; }' +
'    </style>' +
'</head>' +
'<body>' +
'    <div class="container">' +
'        <div class="header"><h1>' + tenantName + '</h1></div>' +
'        <div class="content">' + content.replace(/\{\{first_name\}\}/g, 'John').replace(/\{\{last_name\}\}/g, 'Doe').replace(/\{\{full_name\}\}/g, 'John Doe').replace(/\{\{email\}\}/g, 'john@example.com') + '</div>' +
'        <div class="footer">Preview Mode - Links are disabled</div>' +
'    </div>' +
'</body>' +
'</html>';

    previewFrame.srcdoc = html;
}

// A/B Testing toggle
const abTestToggle = document.getElementById('ab-test-toggle');
const abTestSettings = document.getElementById('ab-test-settings');
const subjectInput = document.querySelector('input[name="subject"]');
const subjectADisplay = document.getElementById('subject-a-display');

if (abTestToggle) {
    abTestToggle.addEventListener('change', function() {
        abTestSettings.style.display = this.checked ? 'block' : 'none';
        if (this.checked && subjectADisplay) {
            subjectADisplay.value = subjectInput.value;
        }
    });
}

// Sync subject A display with main subject input
if (subjectInput && subjectADisplay) {
    subjectInput.addEventListener('input', function() {
        subjectADisplay.value = this.value;
    });
    subjectADisplay.value = subjectInput.value;
}

// Segment selector
const segmentSelect = document.getElementById('segment-select');
const segmentCountDiv = document.getElementById('segment-count');
const segmentCountValue = document.getElementById('segment-count-value');

if (segmentSelect) {
    segmentSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (this.value && selectedOption.dataset.count !== undefined) {
            segmentCountValue.textContent = formatNumber(parseInt(selectedOption.dataset.count) || 0);
            segmentCountDiv.style.display = 'block';
        } else {
            segmentCountDiv.style.display = 'none';
        }
    });

    // Trigger change on load if segment is selected
    if (segmentSelect.value) {
        segmentSelect.dispatchEvent(new Event('change'));
    }
}

// Show targeting section if filters were previously applied
<?php if ($isEdit && (
    !empty($selectedCounties ?? []) ||
    !empty($selectedTowns ?? []) ||
    !empty($selectedGroups ?? []) ||
    !empty($newsletter['segment_id'] ?? null)
)): ?>
document.addEventListener('DOMContentLoaded', function() {
    toggleTargeting();
});
<?php endif; ?>

// ============================================
// LIVE RECIPIENT COUNT & PREVIEW
// ============================================

var liveCountTimeout = null;

function updateLiveRecipientCount() {
    // Clear any pending timeout
    if (liveCountTimeout) clearTimeout(liveCountTimeout);

    // Debounce to avoid too many requests
    liveCountTimeout = setTimeout(function() {
        fetchLiveRecipientCount();
    }, 300);
}

function fetchLiveRecipientCount() {
    var formData = new FormData();

    // Get target audience
    var audienceRadio = document.querySelector('input[name="target_audience"]:checked');
    formData.append('target_audience', audienceRadio ? audienceRadio.value : 'all_members');

    // Get selected counties
    document.querySelectorAll('input[name="target_counties[]"]:checked').forEach(function(cb) {
        formData.append('target_counties[]', cb.value);
    });

    // Get selected towns
    document.querySelectorAll('input[name="target_towns[]"]:checked').forEach(function(cb) {
        formData.append('target_towns[]', cb.value);
    });

    // Get custom towns
    var customTowns = document.querySelector('input[name="custom_towns"]');
    if (customTowns && customTowns.value) {
        formData.append('custom_towns', customTowns.value);
    }

    // Get selected groups
    document.querySelectorAll('input[name="target_groups[]"]:checked').forEach(function(cb) {
        formData.append('target_groups[]', cb.value);
    });

    fetch('<?= $basePath ?>/admin/newsletters/get-recipient-count', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            updateCountDisplays(data.count, data.filtered);
        }
    })
    .catch(function(error) {
        console.error('Error fetching recipient count:', error);
    });
}

function updateCountDisplays(count, isFiltered) {
    var sendCount = document.getElementById('send-count');
    var modalCount = document.getElementById('modal-count');
    var recipientCountDiv = document.getElementById('recipient-count');

    var formattedCount = formatNumber(count);

    if (sendCount) {
        if (isFiltered) {
            sendCount.innerHTML = formattedCount + ' <i class="fa-solid fa-filter" style="font-size: 0.75em; opacity: 0.7;"></i>';
        } else {
            sendCount.textContent = formattedCount;
        }
    }

    if (modalCount) {
        modalCount.textContent = formattedCount;
        if (isFiltered) {
            modalCount.style.color = '#f59e0b';
        } else {
            modalCount.style.color = '#10b981';
        }
    }

    if (recipientCountDiv) {
        var filterNote = isFiltered ? ' (filtered by targeting)' : '';
        recipientCountDiv.innerHTML = '<i class="fa-solid fa-users" style="color: #6366f1;"></i> ' +
            'This newsletter will be sent to <strong style="color: #111827; margin: 0 4px;">' + formattedCount + '</strong> eligible recipients' + filterNote + '.' +
            ' <button type="button" onclick="showRecipientPreview()" style="background: none; border: none; color: #6366f1; cursor: pointer; font-weight: 500; text-decoration: underline; font-size: inherit;">Preview list</button>';
    }
}

// Attach live count updates to targeting filter changes
document.addEventListener('DOMContentLoaded', function() {
    // Audience radio buttons
    document.querySelectorAll('input[name="target_audience"]').forEach(function(radio) {
        radio.addEventListener('change', updateLiveRecipientCount);
    });

    // Targeting checkboxes
    document.querySelectorAll('input[name="target_counties[]"], input[name="target_towns[]"], input[name="target_groups[]"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', updateLiveRecipientCount);
    });

    // Custom towns input
    var customTownsInput = document.querySelector('input[name="custom_towns"]');
    if (customTownsInput) {
        customTownsInput.addEventListener('input', updateLiveRecipientCount);
    }

    // Initial fetch
    <?php if ($isEdit): ?>
    fetchLiveRecipientCount();
    <?php endif; ?>
});

// ============================================
// RECIPIENT PREVIEW MODAL
// ============================================

function showRecipientPreview() {
    // Create modal if it doesn't exist
    var modal = document.getElementById('recipient-preview-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'recipient-preview-modal';
        modal.style.cssText = 'display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(4px);';
        modal.innerHTML = `
            <div style="background: white; padding: 30px; border-radius: 20px; max-width: 600px; width: 90%; max-height: 80vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; font-size: 1.2rem; color: #111827;">
                        <i class="fa-solid fa-users" style="color: #6366f1; margin-right: 8px;"></i>
                        Recipient Preview
                    </h3>
                    <button onclick="closeRecipientPreview()" style="background: none; border: none; font-size: 1.5rem; color: #9ca3af; cursor: pointer;">&times;</button>
                </div>
                <div id="recipient-preview-info" style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; color: #0369a1;">
                    Loading...
                </div>
                <div id="recipient-preview-list" style="flex: 1; overflow-y: auto; max-height: 400px;">
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem;"></i>
                        <p>Loading recipients...</p>
                    </div>
                </div>
                <div id="recipient-preview-pagination" style="display: none; padding-top: 15px; border-top: 1px solid #e5e7eb; margin-top: 15px; text-align: center;">
                    <button onclick="loadMoreRecipients()" id="load-more-btn" style="background: #f1f5f9; color: #475569; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 500;">
                        Load More
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    modal.style.display = 'flex';
    loadRecipientPreview(0);
}

var currentPreviewOffset = 0;
var totalPreviewRecipients = 0;

function loadRecipientPreview(offset) {
    currentPreviewOffset = offset;

    var formData = new FormData();
    formData.append('offset', offset);
    formData.append('limit', 50);

    // Get target audience
    var audienceRadio = document.querySelector('input[name="target_audience"]:checked');
    formData.append('target_audience', audienceRadio ? audienceRadio.value : 'all_members');

    // Get selected counties
    document.querySelectorAll('input[name="target_counties[]"]:checked').forEach(function(cb) {
        formData.append('target_counties[]', cb.value);
    });

    // Get selected towns
    document.querySelectorAll('input[name="target_towns[]"]:checked').forEach(function(cb) {
        formData.append('target_towns[]', cb.value);
    });

    // Get custom towns
    var customTowns = document.querySelector('input[name="custom_towns"]');
    if (customTowns && customTowns.value) {
        formData.append('custom_towns', customTowns.value);
    }

    // Get selected groups
    document.querySelectorAll('input[name="target_groups[]"]:checked').forEach(function(cb) {
        formData.append('target_groups[]', cb.value);
    });

    fetch('<?= $basePath ?>/admin/newsletters/preview-recipients', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            totalPreviewRecipients = data.total;
            renderRecipientPreview(data.recipients, data.total, data.showing, offset, data.filtered);
        } else {
            document.getElementById('recipient-preview-list').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc2626;"><i class="fa-solid fa-exclamation-circle"></i> ' + (data.error || 'Error loading recipients') + '</div>';
        }
    })
    .catch(function(error) {
        document.getElementById('recipient-preview-list').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc2626;"><i class="fa-solid fa-exclamation-circle"></i> Error loading recipients</div>';
    });
}

function renderRecipientPreview(recipients, total, showing, offset, filtered) {
    var infoDiv = document.getElementById('recipient-preview-info');
    var listDiv = document.getElementById('recipient-preview-list');
    var paginationDiv = document.getElementById('recipient-preview-pagination');

    var filterNote = filtered ? ' <span style="color: #f59e0b;">(filtered by targeting)</span>' : '';
    infoDiv.innerHTML = '<strong>' + formatNumber(total) + '</strong> total recipients' + filterNote + '. Showing ' + (offset + 1) + '-' + (offset + showing) + '.';

    if (recipients.length === 0) {
        listDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280;"><i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i><p>No recipients match your targeting criteria.</p></div>';
        paginationDiv.style.display = 'none';
        return;
    }

    var html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">';
    html += '<thead><tr style="background: #f8fafc; border-bottom: 2px solid #e5e7eb;"><th style="padding: 10px; text-align: left;">Name</th><th style="padding: 10px; text-align: left;">Email</th><th style="padding: 10px; text-align: center;">Type</th></tr></thead>';
    html += '<tbody>';

    recipients.forEach(function(r) {
        var typeColor = r.type === 'member' ? '#10b981' : '#8b5cf6';
        var typeBg = r.type === 'member' ? '#dcfce7' : '#f3e8ff';
        html += '<tr style="border-bottom: 1px solid #f1f5f9;">';
        html += '<td style="padding: 10px;">' + escapeHtml(r.name || '-') + '</td>';
        html += '<td style="padding: 10px; color: #6b7280;">' + escapeHtml(r.email) + '</td>';
        html += '<td style="padding: 10px; text-align: center;"><span style="background: ' + typeBg + '; color: ' + typeColor + '; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 500;">' + r.type + '</span></td>';
        html += '</tr>';
    });

    html += '</tbody></table>';
    listDiv.innerHTML = html;

    // Show/hide pagination
    if (offset + showing < total) {
        paginationDiv.style.display = 'block';
    } else {
        paginationDiv.style.display = 'none';
    }
}

function loadMoreRecipients() {
    loadRecipientPreview(currentPreviewOffset + 50);
}

function closeRecipientPreview() {
    var modal = document.getElementById('recipient-preview-modal');
    if (modal) modal.style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close recipient preview modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRecipientPreview();
    }
});
</script>

<?php if (!$isEdit): ?>
<script>
// Professional newsletter templates with rich HTML formatting
const templates = {
    blank: '',

    announcement: `<h2 style="color: #1f2937; font-size: 24px; font-weight: 700; margin: 0 0 20px; line-height: 1.3;">Big News!</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 20px;">
Hello {{first_name}},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 20px;">
We're excited to share some important news with our community...
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 20px;">
[Add your announcement details here. Be clear, concise, and explain why this matters to your readers.]
</p>

<div style="text-align: center; margin: 35px 0;">
    <a href="#" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; font-weight: 600; font-size: 16px; padding: 16px 32px; border-radius: 10px; text-decoration: none;">Learn More</a>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 20px;">
As always, we're here if you have any questions.
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0;">
Best wishes,<br>
<strong>The Team</strong>
</p>`,

    update: `<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 25px;">
Hi {{first_name}},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 30px;">
Here's what's happening in your community this week:
</p>

<!-- Section: Highlights -->
<div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 12px; padding: 25px; margin-bottom: 25px; border-left: 4px solid #6366f1;">
    <h3 style="color: #4f46e5; font-size: 18px; font-weight: 700; margin: 0 0 15px;">✨ This Week's Highlights</h3>
    <ul style="color: #374151; font-size: 15px; line-height: 1.8; margin: 0; padding-left: 20px;">
        <li>Highlight one goes here</li>
        <li>Highlight two goes here</li>
        <li>Highlight three goes here</li>
    </ul>
</div>

<!-- Section: New Items -->
<h3 style="color: #1f2937; font-size: 18px; font-weight: 700; margin: 25px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #f3f4f6;">🎁 New This Week</h3>

<div style="padding: 15px 0; border-bottom: 1px solid #f3f4f6;">
    <p style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: #1f2937;">Item Title Here</p>
    <p style="margin: 0; font-size: 14px; color: #6b7280;">Brief description of the item</p>
</div>

<div style="padding: 15px 0; border-bottom: 1px solid #f3f4f6;">
    <p style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: #1f2937;">Another Item Title</p>
    <p style="margin: 0; font-size: 14px; color: #6b7280;">Brief description of the item</p>
</div>

<!-- Section: Events -->
<h3 style="color: #1f2937; font-size: 18px; font-weight: 700; margin: 30px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #f3f4f6;">📅 Upcoming Events</h3>

<div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 10px; padding: 18px; margin-bottom: 15px;">
    <p style="margin: 0 0 5px; font-size: 16px; font-weight: 600; color: #1f2937;">Event Name</p>
    <p style="margin: 0; font-size: 14px; color: #92400e; font-weight: 500;">📍 Location • 🕐 Date & Time</p>
</div>

<!-- CTA -->
<div style="text-align: center; margin: 35px 0;">
    <a href="#" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; font-weight: 600; font-size: 16px; padding: 16px 32px; border-radius: 10px; text-decoration: none;">View All Updates</a>
</div>

<p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0; text-align: center;">
Have something to share? <a href="#" style="color: #6366f1;">Post it on the platform</a>
</p>`,

    event: `<div style="text-align: center; margin-bottom: 30px;">
    <span style="display: inline-block; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 8px 16px; border-radius: 20px;">You're Invited</span>
</div>

<h2 style="color: #1f2937; font-size: 28px; font-weight: 800; margin: 0 0 15px; line-height: 1.2; text-align: center;">Event Name Here</h2>

<p style="color: #6b7280; font-size: 16px; line-height: 1.6; margin: 0 0 30px; text-align: center;">
A brief, compelling description of what this event is about and why it's not to be missed.
</p>

<!-- Event Details Card -->
<div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e5e7eb; border-radius: 16px; padding: 30px; margin-bottom: 30px;">
    <div style="margin-bottom: 15px;">
        <span style="font-size: 24px; margin-right: 15px;">📅</span>
        <span style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Date:</span>
        <span style="font-size: 16px; font-weight: 600; color: #1f2937; margin-left: 10px;">Saturday, January 15, 2025</span>
    </div>
    <div style="margin-bottom: 15px;">
        <span style="font-size: 24px; margin-right: 15px;">🕐</span>
        <span style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Time:</span>
        <span style="font-size: 16px; font-weight: 600; color: #1f2937; margin-left: 10px;">2:00 PM - 5:00 PM</span>
    </div>
    <div>
        <span style="font-size: 24px; margin-right: 15px;">📍</span>
        <span style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Location:</span>
        <span style="font-size: 16px; font-weight: 600; color: #1f2937; margin-left: 10px;">Venue Name, Address</span>
    </div>
</div>

<!-- What to Expect -->
<h3 style="color: #1f2937; font-size: 18px; font-weight: 700; margin: 0 0 15px;">What to Expect</h3>
<ul style="color: #374151; font-size: 15px; line-height: 1.8; margin: 0 0 30px; padding-left: 20px;">
    <li>Point one about the event</li>
    <li>Point two about the event</li>
    <li>Point three about the event</li>
</ul>

<!-- CTA -->
<div style="text-align: center; margin: 35px 0;">
    <a href="#" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-weight: 600; font-size: 16px; padding: 18px 40px; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);">RSVP Now - Save Your Spot</a>
</div>

<p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0; text-align: center;">
Spots are limited! Reserve yours today.
</p>`,

    welcome: `<div style="text-align: center; margin-bottom: 25px;">
    <span style="font-size: 48px;">👋</span>
</div>

<h2 style="color: #1f2937; font-size: 28px; font-weight: 800; margin: 0 0 20px; line-height: 1.2; text-align: center;">Welcome to the Community!</h2>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 20px;">
Hi {{first_name}},
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 25px;">
We're absolutely thrilled to have you join us! You're now part of a vibrant community of like-minded people.
</p>

<!-- Quick Start -->
<h3 style="color: #1f2937; font-size: 18px; font-weight: 700; margin: 30px 0 20px;">🚀 Get Started in 3 Steps</h3>

<div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #10b981;">
    <p style="margin: 0; font-size: 15px; color: #065f46;">
        <strong style="color: #047857;">Step 1:</strong> Complete your profile to connect with others
    </p>
</div>

<div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #3b82f6;">
    <p style="margin: 0; font-size: 15px; color: #1e40af;">
        <strong style="color: #1d4ed8;">Step 2:</strong> Browse what others are offering and requesting
    </p>
</div>

<div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #f59e0b;">
    <p style="margin: 0; font-size: 15px; color: #92400e;">
        <strong style="color: #b45309;">Step 3:</strong> Post your first listing or join a group
    </p>
</div>

<!-- CTA -->
<div style="text-align: center; margin: 35px 0;">
    <a href="#" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; font-weight: 600; font-size: 16px; padding: 16px 32px; border-radius: 10px; text-decoration: none;">Complete Your Profile</a>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 30px 0 20px;">
If you have any questions, just reply to this email — we're always happy to help!
</p>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0;">
Welcome aboard! 🎊<br>
<strong>The Team</strong>
</p>`,

    promotional: `<div style="text-align: center; margin-bottom: 25px;">
    <span style="display: inline-block; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 8px 16px; border-radius: 20px;">Limited Time Offer</span>
</div>

<h2 style="color: #1f2937; font-size: 32px; font-weight: 800; margin: 0 0 15px; line-height: 1.2; text-align: center;">Your Special Offer</h2>

<p style="color: #6b7280; font-size: 18px; line-height: 1.6; margin: 0 0 30px; text-align: center;">
Don't miss out on this exclusive opportunity!
</p>

<!-- Offer Box -->
<div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px dashed #f59e0b; border-radius: 16px; padding: 35px; text-align: center; margin-bottom: 30px;">
    <p style="color: #92400e; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 10px; font-weight: 600;">Your Exclusive Offer</p>
    <p style="color: #1f2937; font-size: 48px; font-weight: 800; margin: 0 0 10px; line-height: 1;">50% OFF</p>
    <p style="color: #78350f; font-size: 16px; margin: 0;">Use code: <strong style="background: white; padding: 4px 12px; border-radius: 6px; font-family: monospace;">SPECIAL50</strong></p>
</div>

<p style="color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 20px;">
Describe the offer in more detail here. Explain what they get, why it's valuable, and what makes this offer special.
</p>

<!-- Urgency -->
<div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border-radius: 10px; padding: 15px 20px; margin-bottom: 30px; text-align: center;">
    <p style="margin: 0; font-size: 15px; color: #dc2626; font-weight: 600;">
        ⏰ Offer expires in 3 days — Don't wait!
    </p>
</div>

<!-- CTA -->
<div style="text-align: center; margin: 35px 0;">
    <a href="#" style="display: inline-block; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: #ffffff; font-weight: 700; font-size: 18px; padding: 18px 40px; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.4);">Claim Your Offer Now</a>
</div>`
};

function applyTemplate(templateName) {
    let currentContent = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        currentContent = tinymce.get('content-editor').getContent().trim();
    } else {
        currentContent = document.getElementById('content-editor').value.trim();
    }

    if (currentContent && templateName !== 'blank') {
        if (!confirm('This will replace your current content. Continue?')) {
            return;
        }
    }

    const newContent = templates[templateName] || '';
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').setContent(newContent);
    } else {
        document.getElementById('content-editor').value = newContent;
    }

    // Update button styles
    document.querySelectorAll('.template-btn').forEach(function(btn) {
        if (btn.dataset.template === templateName) {
            btn.style.borderColor = '#6366f1';
            btn.style.background = '#f5f3ff';
            btn.style.color = '#4338ca';
        } else {
            btn.style.borderColor = '#e5e7eb';
            btn.style.background = 'white';
            btn.style.color = '#374151';
        }
    });

    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        tinymce.get('content-editor').focus();
    } else {
        document.getElementById('content-editor').focus();
    }
    updatePreview();
}

// Live preview functionality (duplicate - keeping for compatibility)
function updatePreview() {
    const previewFrame = document.getElementById('preview-frame');
    if (!previewFrame) return;

    // Get content from TinyMCE if available, otherwise from textarea
    let content = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        content = tinymce.get('content-editor').getContent();
    } else {
        content = document.getElementById('content-editor')?.value || '';
    }
    const subject = document.querySelector('input[name="subject"]').value || 'Newsletter Preview';

    const html = '<!DOCTYPE html>' +
'<html>' +
'<head>' +
'    <style>' +
'        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }' +
'        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }' +
'        .header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 30px; text-align: center; color: white; }' +
'        .header h1 { margin: 0; font-size: 24px; }' +
'        .content { padding: 40px 30px; color: #374151; line-height: 1.8; font-size: 16px; }' +
'        .content h2 { color: #1f2937; font-size: 22px; margin-top: 24px; margin-bottom: 12px; }' +
'        .content h3 { color: #1f2937; font-size: 18px; margin-top: 20px; margin-bottom: 10px; }' +
'        .content p { margin: 0 0 16px 0; }' +
'        .content a { color: #6366f1; }' +
'        .content img { max-width: 100%; border-radius: 8px; }' +
'        .footer { background: #f9fafb; padding: 20px; text-align: center; color: #9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb; }' +
'    </style>' +
'</head>' +
'<body>' +
'    <div class="container">' +
'        <div class="header"><h1>' + tenantName + '</h1></div>' +
'        <div class="content">' + content.replace(/{{first_name}}/g, 'John').replace(/{{last_name}}/g, 'Doe').replace(/{{full_name}}/g, 'John Doe').replace(/{{email}}/g, 'john@example.com') + '</div>' +
'        <div class="footer">Preview Mode - Links are disabled</div>' +
'    </div>' +
'</body>' +
'</html>';

    previewFrame.srcdoc = html;
}

// Initialize preview updates
document.addEventListener('DOMContentLoaded', function() {
    const editor = document.getElementById('content-editor');
    if (editor) {
        editor.addEventListener('input', debounce(updatePreview, 300));
    }
    updatePreview();
});

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}
</script>
<?php endif; ?>

<!-- AI Generation Functions -->
<script>
const basePath = '<?= $basePath ?>';

// Generate AI Subject Lines
async function generateAISubject() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    // Gather context
    const previewText = document.getElementById('preview-text-input')?.value || '';
    const templateBtn = document.querySelector('.template-btn.active, .template-btn[style*="border-color: rgb(99, 102, 241)"]');
    const template = templateBtn?.dataset?.template || '';

    try {
        const response = await fetch(basePath + '/api/ai/generate/newsletter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'subject',
                context: {
                    topic: previewText,
                    template: template,
                    audience: 'community members'
                }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            // Parse the numbered list and show suggestions
            const suggestions = data.content.split('\n').filter(line => line.trim());
            showSubjectSuggestions(suggestions);
        } else {
            alert('Error: ' + (data.error || 'Could not generate suggestions'));
        }
    } catch (error) {
        console.error('AI Subject generation error:', error);
        alert('Failed to generate suggestions. Please try again.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

function showSubjectSuggestions(suggestions) {
    const container = document.getElementById('ai-subject-suggestions');
    if (!container) return;

    let html = '<div style="font-size: 0.85rem; font-weight: 600; color: #4f46e5; margin-bottom: 10px;">✨ AI Suggestions (click to use):</div>';
    suggestions.forEach((suggestion, i) => {
        // Clean up the suggestion (remove numbering if present)
        const cleanSuggestion = suggestion.replace(/^\d+[\.\)]\s*/, '').trim();
        if (cleanSuggestion) {
            html += `<button type="button" onclick="useSubjectSuggestion(this)" data-subject="${cleanSuggestion.replace(/"/g, '&quot;')}"
                style="display: block; width: 100%; text-align: left; background: white; border: 1px solid #e9d5ff; padding: 10px 14px; border-radius: 8px; margin-bottom: 8px; cursor: pointer; color: #374151; font-size: 0.9rem; transition: all 0.2s;"
                onmouseover="this.style.borderColor='#8b5cf6'; this.style.background='#faf5ff';"
                onmouseout="this.style.borderColor='#e9d5ff'; this.style.background='white';">
                ${cleanSuggestion}
            </button>`;
        }
    });
    container.innerHTML = html;
    container.style.display = 'block';
}

function useSubjectSuggestion(btn) {
    const subject = btn.dataset.subject;
    const input = document.getElementById('subject-input');
    if (input) {
        input.value = subject;
        input.dispatchEvent(new Event('input'));
        document.getElementById('ai-subject-suggestions').style.display = 'none';
    }
}

// Generate AI Preview Text
async function generateAIPreview() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const subject = document.getElementById('subject-input')?.value || '';

    try {
        const response = await fetch(basePath + '/api/ai/generate/newsletter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'preview',
                context: { subject: subject }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            const input = document.getElementById('preview-text-input');
            if (input) {
                input.value = data.content.replace(/^\d+[\.\)]\s*/, '').trim();
            }
        } else {
            alert('Error: ' + (data.error || 'Could not generate preview text'));
        }
    } catch (error) {
        console.error('AI Preview generation error:', error);
        alert('Failed to generate preview text.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

// Generate A/B Test Variant
async function generateABVariant() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const subject = document.getElementById('subject-input')?.value || '';

    try {
        const response = await fetch(basePath + '/api/ai/generate/newsletter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'subject_ab',
                context: { subject: subject }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            const input = document.getElementById('subject-b');
            if (input) {
                input.value = data.content.replace(/^\d+[\.\)]\s*/, '').trim();
            }
        } else {
            alert('Error: ' + (data.error || 'Could not generate variant'));
        }
    } catch (error) {
        console.error('AI A/B Variant generation error:', error);
        alert('Failed to generate variant.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

// Generate AI Newsletter Content
// Uses existing content as a prompt/framework if present
async function generateAIContent() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    const subject = document.getElementById('subject-input')?.value || '';
    const previewText = document.getElementById('preview-text-input')?.value || '';
    const templateBtn = document.querySelector('.template-btn.active, .template-btn[style*="border-color: rgb(99, 102, 241)"]');
    const template = templateBtn?.dataset?.template || 'general';

    // Get existing content from TinyMCE or textarea - this can be used as a prompt/framework
    let existingContent = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
        existingContent = tinymce.get('content-editor').getContent({ format: 'text' }); // Plain text version
    } else {
        const textarea = document.getElementById('content-editor');
        if (textarea) existingContent = textarea.value;
    }

    // Strip HTML to get just the text for prompt purposes
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = existingContent;
    const textContent = tempDiv.textContent || tempDiv.innerText || '';

    try {
        const response = await fetch(basePath + '/api/ai/generate/newsletter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'content',
                context: {
                    subject: subject,
                    topic: previewText || subject,
                    template: template,
                    audience: 'community members',
                    tone: 'friendly and engaging',
                    existing_content: textContent.trim() // Pass existing content as prompt framework
                }
            })
        });

        const data = await response.json();
        if (data.success && data.content) {
            // Set content in TinyMCE
            if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
                tinymce.get('content-editor').setContent(data.content);
            } else {
                const textarea = document.getElementById('content-editor');
                if (textarea) textarea.value = data.content;
            }
            updatePreview();
        } else {
            alert('Error: ' + (data.error || 'Could not generate content'));
        }
    } catch (error) {
        console.error('AI Content generation error:', error);
        alert('Failed to generate content. Please try again.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}
</script>

<style>
    .newsletter-form-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    /* Desktop spacing */
    @media (min-width: 601px) {
        .newsletter-form-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile responsiveness */
    @media (max-width: 600px) {
        .newsletter-form-wrapper {
            padding: 120px 15px 100px 15px;
        }

        .newsletter-form-wrapper .nexus-card {
            border-radius: 12px;
        }

        .newsletter-form-wrapper [style*="padding: 30px"] {
            padding: 20px !important;
        }
    }
</style>

<?php
else {
    require __DIR__ . '/../../layouts/modern/footer.php';
}
?>
