<?php
/**
 * Newsletter Segment Create/Edit Form - Gold Standard Admin UI
 * Holographic Glassmorphism Design
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$isEdit = isset($segment);
$action = $isEdit
    ? $basePath . "/admin-legacy/newsletters/segments/update/" . $segment['id']
    : $basePath . "/admin-legacy/newsletters/segments/store";

$fields = $fields ?? [];
$groups = $groups ?? [];
$counties = $counties ?? [];
$towns = $towns ?? [];
$existingConditions = ($isEdit && !empty($segment['rules']['conditions'])) ? $segment['rules']['conditions'] : [];
$matchType = ($isEdit && !empty($segment['rules']['match'])) ? $segment['rules']['match'] : 'all';

// Admin header configuration
$adminPageTitle = $isEdit ? 'Edit Segment' : 'Create Segment';
$adminPageSubtitle = 'Define rules to target specific groups of members';
$adminPageIcon = 'fa-solid fa-filter';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="segment-form-container">
    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash-error">
            <div class="flash-icon error">
                <i class="fa-solid fa-xmark"></i>
            </div>
            <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash-success">
            <div class="flash-icon success">
                <i class="fa-solid fa-check"></i>
            </div>
            <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- Navigation -->
    <div class="nav-bar">
        <a href="<?= $basePath ?>/admin-legacy/newsletters/segments" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Segments
        </a>
    </div>

    <?php if (!$isEdit): ?>
    <!-- Smart Segment Suggestions -->
    <div id="smart-suggestions-section" class="glass-card suggestions-card">
        <div class="suggestions-header">
            <div class="suggestions-icon">
                <i class="fa-solid fa-lightbulb"></i>
            </div>
            <div>
                <h3>Smart Suggestions</h3>
                <p>AI-powered segment recommendations based on your member data</p>
            </div>
        </div>

        <div id="suggestions-container" class="suggestions-grid">
            <div class="suggestions-loading">
                <i class="fa-solid fa-spinner fa-spin"></i> Loading suggestions...
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form action="<?= $action ?>" method="POST" id="segment-form">
        <?= \Nexus\Core\Csrf::input() ?>

        <!-- Basic Info Card -->
        <div class="glass-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fa-solid fa-info-circle"></i>
                </div>
                <h2>Segment Details</h2>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Segment Name <span class="required">*</span>
                </label>
                <input type="text"
                       name="name"
                       required
                       value="<?= $isEdit ? htmlspecialchars($segment['name']) : '' ?>"
                       class="form-input"
                       style="max-width: 450px;"
                       placeholder="e.g., Dublin Members, Active Sellers">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description"
                          class="form-textarea"
                          rows="3"
                          placeholder="Brief description of who this segment targets..."><?= $isEdit ? htmlspecialchars($segment['description'] ?? '') : '' ?></textarea>
            </div>

            <?php if ($isEdit): ?>
            <div class="form-group">
                <label class="toggle-label">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           class="toggle-checkbox"
                           <?= $segment['is_active'] ? 'checked' : '' ?>>
                    <span class="toggle-switch"></span>
                    <span class="toggle-text">
                        <strong>Active</strong>
                        <small>Only active segments appear in newsletter targeting</small>
                    </span>
                </label>
            </div>
            <?php endif; ?>
        </div>

        <!-- Targeting Rules Card -->
        <div class="glass-card">
            <div class="card-header">
                <div class="card-icon rules">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <h2>Targeting Rules</h2>
                <div class="match-selector">
                    <span class="match-label">Match</span>
                    <select name="match" class="match-select">
                        <option value="all" <?= $matchType === 'all' ? 'selected' : '' ?>>ALL rules (AND)</option>
                        <option value="any" <?= $matchType === 'any' ? 'selected' : '' ?>>ANY rule (OR)</option>
                    </select>
                </div>
            </div>

            <!-- Rules Container -->
            <div id="rules-container"></div>

            <!-- Add Rule Button -->
            <button type="button" id="add-rule-btn" class="add-rule-btn">
                <i class="fa-solid fa-plus"></i> Add Rule
            </button>

            <!-- Preview Section -->
            <div class="preview-section">
                <button type="button" id="preview-btn" class="preview-btn">
                    <i class="fa-solid fa-eye"></i> Preview Matching Members
                </button>
                <span id="preview-result" class="preview-result"></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-save"></i>
                <?= $isEdit ? 'Update Segment' : 'Create Segment' ?>
            </button>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/segments" class="btn-cancel">
                Cancel
            </a>
            <?php if ($isEdit): ?>
            <button type="button" class="btn-danger" onclick="confirmDelete()">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" role="dialog" aria-modal="true"-overlay" style="display: none;">
    <div class="modal" role="dialog" aria-modal="true"-content">
        <div class="modal" role="dialog" aria-modal="true"-icon danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Delete Segment?</h3>
        <p>Are you sure you want to delete "<strong><?= htmlspecialchars($segment['name'] ?? '') ?></strong>"? This action cannot be undone.</p>
        <div class="modal" role="dialog" aria-modal="true"-actions">
            <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <form action="<?= $basePath ?>/admin-legacy/newsletters/segments/delete/<?= $segment['id'] ?>" method="POST" style="display: inline;">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" class="btn-danger">
                    <i class="fa-solid fa-trash"></i> Delete Segment
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Rule Template -->
<template id="rule-template">
    <div class="rule-row">
        <button type="button" class="remove-rule" title="Remove rule">&times;</button>

        <div class="rule-grid">
            <!-- Field Select -->
            <div class="rule-field">
                <label class="rule-label">Field</label>
                <select name="rule_field[]" class="rule-select field-select">
                    <option value="">Select field...</option>
                    <optgroup label="Engagement (Algorithm)">
                        <option value="activity_score">Activity Score</option>
                        <option value="community_rank">CommunityRank</option>
                        <option value="login_recency">Login Recency</option>
                        <option value="transaction_count">Transaction Count</option>
                    </optgroup>
                    <optgroup label="Email Engagement">
                        <option value="email_open_rate">Email Open Rate (%)</option>
                        <option value="email_click_rate">Email Click Rate (%)</option>
                        <option value="newsletters_received">Newsletters Received</option>
                        <option value="email_engagement_level">Email Engagement Level</option>
                    </optgroup>
                    <optgroup label="Geographic">
                        <option value="county">County</option>
                        <option value="town">Town/City</option>
                        <option value="geo_radius">Area (radius)</option>
                        <option value="location">Location Text</option>
                    </optgroup>
                    <optgroup label="Groups">
                        <option value="group_membership">Group Membership</option>
                    </optgroup>
                    <optgroup label="Profile">
                        <option value="profile_type">Profile Type</option>
                        <option value="role">User Role</option>
                    </optgroup>
                    <optgroup label="Activity">
                        <option value="created_at">Member Since</option>
                        <option value="has_listings">Has Listings</option>
                        <option value="listing_count">Listing Count</option>
                    </optgroup>
                </select>
            </div>

            <!-- Operator Select -->
            <div class="rule-field operator-container">
                <label class="rule-label">Operator</label>
                <select name="rule_operator[]" class="rule-select operator-select">
                    <option value="equals">equals</option>
                </select>
            </div>

            <!-- Value Input -->
            <div class="rule-field value-container">
                <label class="rule-label">Value</label>
                <input type="text" name="rule_value[]" class="rule-input value-input" placeholder="Enter value...">
            </div>
        </div>

        <!-- Special field containers (hidden by default) -->
        <div class="geo-radius-fields special-fields" style="display: none;">
            <div class="geo-grid">
                <div>
                    <label class="rule-label">Center Location (search)</label>
                    <input type="text" class="rule-input geo-search" placeholder="Search for a place...">
                    <input type="hidden" name="geo_lat[]" class="geo-lat" value="0">
                    <input type="hidden" name="geo_lng[]" class="geo-lng" value="0">
                </div>
                <div>
                    <label class="rule-label">Selected Location</label>
                    <div class="geo-selected">Click to search or select on map</div>
                </div>
                <div>
                    <label class="rule-label">Radius (km)</label>
                    <input type="number" name="geo_radius[]" class="rule-input geo-radius-input" value="50" min="1" max="500">
                </div>
            </div>
        </div>

        <div class="county-fields special-fields" style="display: none;">
            <label class="rule-label">Select Counties</label>
            <div class="checkbox-grid county-checkboxes">
                <?php foreach ($counties as $county): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="county_value[0][]" value="<?= htmlspecialchars($county) ?>">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text"><?= htmlspecialchars($county) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="town-fields special-fields" style="display: none;">
            <label class="rule-label">Select Towns/Cities</label>
            <div class="town-search-wrapper">
                <input type="text" class="rule-input town-search" placeholder="Search towns...">
            </div>
            <div class="checkbox-grid town-checkboxes">
                <?php foreach ($towns as $town): ?>
                    <label class="checkbox-label town-option" data-town="<?= strtolower(htmlspecialchars($town)) ?>">
                        <input type="checkbox" name="town_value[0][]" value="<?= htmlspecialchars($town) ?>">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text"><?= htmlspecialchars($town) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="custom-towns">
                <label class="rule-label">Or enter custom towns (comma-separated)</label>
                <input type="text" name="town_custom[0]" class="rule-input town-custom" placeholder="e.g., Ballymun, Tallaght, Blanchardstown">
            </div>
        </div>

        <div class="group-fields special-fields" style="display: none;">
            <label class="rule-label">Select Groups</label>
            <div class="checkbox-grid group-checkboxes">
                <?php if (empty($groups)): ?>
                    <span class="no-groups">No groups available</span>
                <?php else: ?>
                    <?php foreach ($groups as $group): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="group_value[0][]" value="<?= $group['id'] ?>">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text"><?= htmlspecialchars($group['name']) ?></span>
                            <span class="checkbox-count">(<?= $group['member_count'] ?>)</span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</template>

<style>
/* Container */
.segment-form-container {
    max-width: 950px;
    margin: 0 auto;
    padding: 0 24px 60px;
}

/* Flash Messages */
.flash-error,
.flash-success {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    backdrop-filter: blur(10px);
}

.flash-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
    border: 1px solid rgba(239, 68, 68, 0.4);
    color: #fca5a5;
}

.flash-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(22, 163, 74, 0.2) 100%);
    border: 1px solid rgba(34, 197, 94, 0.4);
    color: #86efac;
}

.flash-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.flash-icon.error {
    background: #ef4444;
    color: white;
}

.flash-icon.success {
    background: #22c55e;
    color: white;
}

/* Navigation */
.nav-bar {
    margin-bottom: 24px;
}

.back-link {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: color 0.2s ease;
}

.back-link:hover {
    color: #a5b4fc;
}

/* Smart Suggestions */
.suggestions-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
    border-color: rgba(99, 102, 241, 0.3);
    margin-bottom: 24px;
}

.suggestions-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
}

.suggestions-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.suggestions-icon i {
    color: white;
    font-size: 1.2rem;
}

.suggestions-header h3 {
    margin: 0 0 4px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #a5b4fc;
}

.suggestions-header p {
    margin: 0;
    color: rgba(165, 180, 252, 0.7);
    font-size: 0.9rem;
}

.suggestions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.suggestions-loading {
    text-align: center;
    color: rgba(255, 255, 255, 0.5);
    padding: 24px;
    grid-column: 1/-1;
}

.suggestion-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.suggestion-card:hover {
    transform: translateY(-2px);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

/* Glass Card */
.glass-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    backdrop-filter: blur(10px);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-wrap: wrap;
}

.card-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a5b4fc;
    font-size: 1.1rem;
}

.card-icon.rules {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
    border-color: rgba(251, 191, 36, 0.3);
    color: #fcd34d;
}

.card-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #f1f5f9;
    flex: 1;
}

.match-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.match-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

.match-select {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    color: #f1f5f9;
    padding: 8px 12px;
    font-size: 0.9rem;
    cursor: pointer;
}

.match-select option {
    background: #1e293b;
    color: #f1f5f9;
}

/* Form Elements */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.form-label .required {
    color: #f87171;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    color: #f1f5f9;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

/* Toggle Switch */
.toggle-label {
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
}

.toggle-checkbox {
    display: none;
}

.toggle-switch {
    width: 48px;
    height: 26px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 13px;
    position: relative;
    transition: background 0.3s ease;
    flex-shrink: 0;
}

.toggle-switch::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s ease;
}

.toggle-checkbox:checked + .toggle-switch {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
}

.toggle-checkbox:checked + .toggle-switch::after {
    transform: translateX(22px);
}

.toggle-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.toggle-text strong {
    color: #f1f5f9;
    font-size: 0.95rem;
}

.toggle-text small {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

/* Rule Row */
.rule-row {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    position: relative;
    transition: all 0.2s ease;
}

.rule-row:hover {
    border-color: rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.remove-rule {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.remove-rule:hover {
    background: rgba(239, 68, 68, 0.25);
    transform: scale(1.1);
}

.rule-grid {
    display: grid;
    grid-template-columns: 200px 160px 1fr;
    gap: 16px;
    align-items: start;
}

.rule-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.rule-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.rule-select,
.rule-input {
    width: 100%;
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    color: #f1f5f9;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.rule-select:focus,
.rule-input:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    background: rgba(255, 255, 255, 0.08);
}

.rule-select option,
.rule-select optgroup {
    background: #1e293b;
    color: #f1f5f9;
}

.rule-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

/* Special Fields */
.special-fields {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.geo-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 120px;
    gap: 16px;
}

.geo-selected {
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    min-height: 42px;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

/* Checkbox Grid */
.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    max-height: 220px;
    overflow-y: auto;
    padding: 14px;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    margin-top: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 6px;
    transition: background 0.2s ease;
}

.checkbox-label:hover {
    background: rgba(255, 255, 255, 0.05);
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 18px;
    height: 18px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.checkbox-label input:checked + .checkbox-custom {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-color: #6366f1;
}

.checkbox-label input:checked + .checkbox-custom::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    font-size: 10px;
    color: white;
}

.checkbox-text {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.checkbox-count {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.8rem;
}

.no-groups {
    color: rgba(255, 255, 255, 0.4);
    font-style: italic;
    grid-column: 1/-1;
}

.town-search-wrapper {
    margin-bottom: 12px;
}

.custom-towns {
    margin-top: 14px;
}

/* Add Rule Button */
.add-rule-btn {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 2px dashed rgba(255, 255, 255, 0.15);
    padding: 14px 20px;
    border-radius: 10px;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.5);
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.add-rule-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.4);
    color: #a5b4fc;
}

/* Preview Section */
.preview-section {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.preview-btn {
    background: rgba(14, 165, 233, 0.15);
    border: 1px solid rgba(14, 165, 233, 0.3);
    color: #7dd3fc;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.preview-btn:hover {
    background: rgba(14, 165, 233, 0.25);
    border-color: rgba(14, 165, 233, 0.5);
}

.preview-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.preview-result {
    font-weight: 600;
    font-size: 0.95rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: white;
    padding: 14px 28px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.7);
    padding: 14px 28px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 1rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.btn-danger {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    padding: 14px 28px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    margin-left: auto;
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 32px;
    max-width: 420px;
    width: 90%;
    text-align: center;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 1.75rem;
}

.modal-icon.danger {
    background: rgba(239, 68, 68, 0.2);
    border: 2px solid rgba(239, 68, 68, 0.4);
    color: #f87171;
}

.modal-content h3 {
    margin: 0 0 12px;
    font-size: 1.25rem;
    color: #f1f5f9;
}

.modal-content p {
    margin: 0 0 24px;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.6;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-actions .btn-cancel,
.modal-actions .btn-danger {
    padding: 12px 24px;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .rule-grid {
        grid-template-columns: 1fr;
    }

    .geo-grid {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn-danger {
        margin-left: 0;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }

    .match-selector {
        width: 100%;
    }

    .match-select {
        flex: 1;
    }
}

/* Animations */
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

/* Scrollbar Styling */
.checkbox-grid::-webkit-scrollbar {
    width: 6px;
}

.checkbox-grid::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
}

.checkbox-grid::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.checkbox-grid::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>

<script>
// Field configurations
const fieldConfigs = {
    // Engagement-based fields (Algorithm)
    activity_score: {
        type: 'select',
        operators: [
            {value: 'equals', label: 'is'},
            {value: 'not_equals', label: 'is not'}
        ],
        options: [
            {value: 'high', label: 'High (Active)'},
            {value: 'medium', label: 'Medium'},
            {value: 'low', label: 'Low (Inactive)'}
        ]
    },
    community_rank: {
        type: 'select',
        operators: [
            {value: 'equals', label: 'is in'}
        ],
        options: [
            {value: 'top_10', label: 'Top 10%'},
            {value: 'top_25', label: 'Top 25%'},
            {value: 'top_50', label: 'Top 50%'},
            {value: 'bottom_25', label: 'Bottom 25%'}
        ]
    },
    login_recency: {
        type: 'number',
        operators: [
            {value: 'newer_than_days', label: 'logged in within N days'},
            {value: 'older_than_days', label: 'not logged in for N days'}
        ],
        placeholder: 'Number of days'
    },
    transaction_count: {
        type: 'number',
        operators: [
            {value: 'at_least', label: 'at least'},
            {value: 'at_most', label: 'at most'},
            {value: 'equals', label: 'exactly'},
            {value: 'greater_than', label: 'more than'},
            {value: 'less_than', label: 'less than'}
        ],
        placeholder: 'Number'
    },

    // Email engagement fields
    email_open_rate: {
        type: 'number',
        operators: [
            {value: 'at_least', label: 'at least'},
            {value: 'at_most', label: 'at most'},
            {value: 'greater_than', label: 'more than'},
            {value: 'less_than', label: 'less than'}
        ],
        placeholder: 'Percentage (0-100)'
    },
    email_click_rate: {
        type: 'number',
        operators: [
            {value: 'at_least', label: 'at least'},
            {value: 'at_most', label: 'at most'},
            {value: 'greater_than', label: 'more than'},
            {value: 'less_than', label: 'less than'}
        ],
        placeholder: 'Percentage (0-100)'
    },
    newsletters_received: {
        type: 'number',
        operators: [
            {value: 'at_least', label: 'at least'},
            {value: 'at_most', label: 'at most'},
            {value: 'equals', label: 'exactly'},
            {value: 'greater_than', label: 'more than'},
            {value: 'less_than', label: 'less than'}
        ],
        placeholder: 'Number'
    },
    email_engagement_level: {
        type: 'select',
        operators: [
            {value: 'equals', label: 'is'},
            {value: 'not_equals', label: 'is not'}
        ],
        options: [
            {value: 'highly_engaged', label: 'Highly Engaged'},
            {value: 'engaged', label: 'Engaged'},
            {value: 'passive', label: 'Passive'},
            {value: 'dormant', label: 'Dormant'},
            {value: 'never_opened', label: 'Never Opened'}
        ]
    },

    // Profile fields
    role: {
        type: 'select',
        operators: [
            {value: 'equals', label: 'is'},
            {value: 'not_equals', label: 'is not'}
        ],
        options: [
            {value: 'user', label: 'User'},
            {value: 'admin', label: 'Admin'}
        ]
    },
    profile_type: {
        type: 'select',
        operators: [
            {value: 'equals', label: 'is'},
            {value: 'not_equals', label: 'is not'}
        ],
        options: [
            {value: 'individual', label: 'Individual'},
            {value: 'organisation', label: 'Organisation'}
        ]
    },
    location: {
        type: 'text',
        operators: [
            {value: 'contains', label: 'contains'},
            {value: 'equals', label: 'equals'},
            {value: 'starts_with', label: 'starts with'},
            {value: 'is_empty', label: 'is empty'},
            {value: 'is_not_empty', label: 'is not empty'}
        ]
    },
    county: {
        type: 'county_select',
        operators: [
            {value: 'in', label: 'is any of'},
            {value: 'not_in', label: 'is not any of'}
        ]
    },
    town: {
        type: 'town_select',
        operators: [
            {value: 'in', label: 'is any of'},
            {value: 'not_in', label: 'is not any of'}
        ]
    },
    geo_radius: {
        type: 'geo_radius',
        operators: [
            {value: 'within', label: 'within radius'}
        ]
    },
    group_membership: {
        type: 'group_select',
        operators: [
            {value: 'member_of', label: 'is member of'},
            {value: 'not_member_of', label: 'is not member of'}
        ]
    },
    created_at: {
        type: 'number',
        operators: [
            {value: 'newer_than_days', label: 'within last N days'},
            {value: 'older_than_days', label: 'older than N days'}
        ],
        placeholder: 'Number of days'
    },
    has_listings: {
        type: 'select',
        operators: [
            {value: 'equals', label: 'is'}
        ],
        options: [
            {value: '1', label: 'Yes'},
            {value: '0', label: 'No'}
        ]
    },
    listing_count: {
        type: 'number',
        operators: [
            {value: 'at_least', label: 'at least'},
            {value: 'at_most', label: 'at most'},
            {value: 'equals', label: 'exactly'},
            {value: 'greater_than', label: 'more than'},
            {value: 'less_than', label: 'less than'}
        ],
        placeholder: 'Number'
    }
};

let ruleIndex = 0;

// Add rule function
function addRule(field = '', operator = '', value = '') {
    const template = document.getElementById('rule-template');
    const container = document.getElementById('rules-container');
    const clone = template.content.cloneNode(true);
    const ruleRow = clone.querySelector('.rule-row');

    // Update name attributes with index
    ruleRow.querySelectorAll('[name*="[0]"]').forEach(el => {
        el.name = el.name.replace('[0]', '[' + ruleIndex + ']');
    });

    container.appendChild(clone);

    const newRow = container.lastElementChild;

    // Set field if provided
    if (field) {
        newRow.querySelector('.field-select').value = field;
        updateFieldUI(newRow, field, operator, value);
    }

    // Event listeners
    newRow.querySelector('.field-select').addEventListener('change', function() {
        updateFieldUI(newRow, this.value);
    });

    newRow.querySelector('.remove-rule').addEventListener('click', function() {
        newRow.remove();
    });

    ruleIndex++;
}

// Update UI based on field type
function updateFieldUI(row, field, operator = '', value = '') {
    const config = fieldConfigs[field] || {type: 'text', operators: [{value: 'equals', label: 'equals'}]};
    const operatorSelect = row.querySelector('.operator-select');
    const valueContainer = row.querySelector('.value-container');
    const geoFields = row.querySelector('.geo-radius-fields');
    const countyFields = row.querySelector('.county-fields');
    const townFields = row.querySelector('.town-fields');
    const groupFields = row.querySelector('.group-fields');

    // Reset visibility
    valueContainer.style.display = 'block';
    geoFields.style.display = 'none';
    countyFields.style.display = 'none';
    townFields.style.display = 'none';
    groupFields.style.display = 'none';

    // Update operators
    operatorSelect.innerHTML = '';
    config.operators.forEach(op => {
        const option = document.createElement('option');
        option.value = op.value;
        option.textContent = op.label;
        if (op.value === operator) option.selected = true;
        operatorSelect.appendChild(option);
    });

    // Update value field based on type
    if (config.type === 'select') {
        let selectHtml = '<select name="rule_value[]" class="rule-select value-input">';
        config.options.forEach(opt => {
            const selected = opt.value === value ? 'selected' : '';
            selectHtml += `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
        });
        selectHtml += '</select>';
        valueContainer.innerHTML = '<label class="rule-label">Value</label>' + selectHtml;
    } else if (config.type === 'geo_radius') {
        valueContainer.style.display = 'none';
        geoFields.style.display = 'block';
        if (value && typeof value === 'object') {
            row.querySelector('.geo-lat').value = value.lat || 0;
            row.querySelector('.geo-lng').value = value.lng || 0;
            row.querySelector('.geo-radius-input').value = value.radius_km || 50;
            if (value.lat && value.lng) {
                row.querySelector('.geo-selected').textContent = `Lat: ${value.lat}, Lng: ${value.lng}`;
            }
        }
    } else if (config.type === 'county_select') {
        valueContainer.style.display = 'none';
        countyFields.style.display = 'block';
        if (Array.isArray(value)) {
            countyFields.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = value.includes(cb.value);
            });
        }
    } else if (config.type === 'town_select') {
        valueContainer.style.display = 'none';
        townFields.style.display = 'block';
        if (Array.isArray(value)) {
            townFields.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = value.includes(cb.value);
            });
        }
    } else if (config.type === 'group_select') {
        valueContainer.style.display = 'none';
        groupFields.style.display = 'block';
        if (Array.isArray(value)) {
            groupFields.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = value.map(String).includes(cb.value);
            });
        }
    } else {
        let inputHtml = `<input type="${config.type === 'number' ? 'number' : 'text'}" name="rule_value[]" class="rule-input value-input" placeholder="${config.placeholder || 'Enter value...'}">`;
        valueContainer.innerHTML = '<label class="rule-label">Value</label>' + inputHtml;
        if (value) {
            valueContainer.querySelector('input').value = value;
        }
    }
}

// Add rule button
document.getElementById('add-rule-btn').addEventListener('click', function() {
    addRule();
});

// Preview button with loading state
document.getElementById('preview-btn').addEventListener('click', function() {
    const btn = this;
    const form = document.getElementById('segment-form');
    const formData = new FormData(form);
    const result = document.getElementById('preview-result');

    // Validate at least one rule is configured
    const rules = document.querySelectorAll('.rule-row');
    let hasValidRule = false;
    rules.forEach(rule => {
        const field = rule.querySelector('.field-select')?.value;
        if (field) hasValidRule = true;
    });

    if (!hasValidRule) {
        result.innerHTML = '<span style="color: #fcd34d;">Add at least one rule to preview</span>';
        return;
    }

    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
    result.innerHTML = '<span style="color: rgba(255,255,255,0.5);"><i class="fa-solid fa-spinner fa-spin"></i> Calculating...</span>';

    fetch('<?= $basePath ?>/admin-legacy/newsletters/segments/preview', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const count = data.count;
            const color = count > 0 ? '#86efac' : '#fcd34d';
            const icon = count > 0 ? 'fa-users' : 'fa-exclamation-circle';
            result.innerHTML = `<span style="color: ${color};"><i class="fa-solid ${icon}"></i> ${count} member${count !== 1 ? 's' : ''} match</span>`;
        } else {
            result.innerHTML = `<span style="color: #fca5a5;"><i class="fa-solid fa-times-circle"></i> ${data.error}</span>`;
        }
    })
    .catch(err => {
        result.innerHTML = '<span style="color: #fca5a5;"><i class="fa-solid fa-times-circle"></i> Error loading preview</span>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Preview Count';
    });
});

// Initialize with existing conditions
<?php if (!empty($existingConditions)): ?>
const existingConditions = <?= json_encode($existingConditions) ?>;
existingConditions.forEach(condition => {
    addRule(condition.field, condition.operator, condition.value);
});
<?php else: ?>
// Add one empty rule by default
addRule();
<?php endif; ?>

// Geo search with Mapbox (if available)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('geo-search')) {
        const row = e.target.closest('.rule-row');
        const lat = prompt('Enter latitude:', row.querySelector('.geo-lat').value || '53.349805');
        const lng = prompt('Enter longitude:', row.querySelector('.geo-lng').value || '-6.26031');

        if (lat && lng) {
            row.querySelector('.geo-lat').value = lat;
            row.querySelector('.geo-lng').value = lng;
            row.querySelector('.geo-selected').textContent = `Lat: ${lat}, Lng: ${lng}`;
            row.querySelector('.geo-selected').style.color = '#86efac';
        }
    }
});

// Town search functionality
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('town-search')) {
        const searchValue = e.target.value.toLowerCase();
        const townFields = e.target.closest('.town-fields');
        const townOptions = townFields.querySelectorAll('.town-option');

        townOptions.forEach(option => {
            const townName = option.getAttribute('data-town');
            if (townName.includes(searchValue) || searchValue === '') {
                option.style.display = 'flex';
            } else {
                option.style.display = 'none';
            }
        });
    }
});

// Delete modal functions
function confirmDelete() {
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal on escape or backdrop click
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// =========================================================================
// Smart Suggestions (only on create page)
// =========================================================================
<?php if (!$isEdit): ?>
document.addEventListener('DOMContentLoaded', function() {
    loadSmartSuggestions();
});

function loadSmartSuggestions() {
    const container = document.getElementById('suggestions-container');

    fetch('<?= $basePath ?>/admin-legacy/newsletters/segments/suggestions')
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.suggestions || data.suggestions.length === 0) {
                container.innerHTML = '<div class="suggestions-loading">No suggestions available yet. Send a few newsletters to get personalized recommendations.</div>';
                return;
            }

            container.innerHTML = '';

            data.suggestions.forEach(suggestion => {
                const card = createSuggestionCard(suggestion);
                container.appendChild(card);
            });
        })
        .catch(err => {
            console.error('Failed to load suggestions:', err);
            container.innerHTML = '<div class="suggestions-loading" style="color: #fca5a5;">Failed to load suggestions</div>';
        });
}

function createSuggestionCard(suggestion) {
    const card = document.createElement('div');
    card.className = 'suggestion-card';

    card.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, ${suggestion.color} 0%, ${adjustColor(suggestion.color, -30)} 100%);">
                <i class="fa-solid ${suggestion.icon}" style="color: white; font-size: 1rem;"></i>
            </div>
            <div>
                <div style="font-weight: 700; color: #f1f5f9;">${escapeHtml(suggestion.name)}</div>
                <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">${suggestion.member_count} members</div>
            </div>
        </div>
        <p style="font-size: 0.9rem; color: rgba(255,255,255,0.7); margin: 0 0 12px;">${escapeHtml(suggestion.description)}</p>
        <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5); background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; margin-bottom: 12px;">${escapeHtml(suggestion.explanation)}</div>
        <button type="button" class="use-suggestion-btn" data-id="${suggestion.id}" style="width: 100%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
            <i class="fa-solid fa-plus"></i> Create This Segment
        </button>
    `;

    // Create segment button
    const createBtn = card.querySelector('.use-suggestion-btn');
    createBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        createFromSuggestion(suggestion.id, this);
    });

    return card;
}

function createFromSuggestion(suggestionId, buttonElement) {
    if (!confirm('Create a segment from this suggestion?')) return;

    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const formData = new FormData();
    formData.append('suggestion_id', suggestionId);
    formData.append('csrf_token', csrfToken);

    const btn = buttonElement;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
    }

    fetch('<?= $basePath ?>/admin-legacy/newsletters/segments/from-suggestion', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.redirect) {
            if (btn) {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Created!';
                btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            }
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 500);
        } else {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-plus"></i> Create This Segment';
            }
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plus"></i> Create This Segment';
        }
        showNotification('Failed to create segment', 'error');
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 10px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        backdrop-filter: blur(10px);
    `;
    notification.style.background = type === 'error'
        ? 'linear-gradient(135deg, rgba(239, 68, 68, 0.9) 0%, rgba(220, 38, 38, 0.9) 100%)'
        : type === 'success'
        ? 'linear-gradient(135deg, rgba(34, 197, 94, 0.9) 0%, rgba(22, 163, 74, 0.9) 100%)'
        : 'linear-gradient(135deg, rgba(59, 130, 246, 0.9) 0%, rgba(37, 99, 235, 0.9) 100%)';
    notification.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-times-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i> ${message}`;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function adjustColor(hex, amount) {
    let col = hex.replace('#', '');
    let num = parseInt(col, 16);
    let r = Math.max(0, Math.min(255, (num >> 16) + amount));
    let g = Math.max(0, Math.min(255, ((num >> 8) & 0x00FF) + amount));
    let b = Math.max(0, Math.min(255, (num & 0x0000FF) + amount));
    return '#' + (0x1000000 + r*0x10000 + g*0x100 + b).toString(16).slice(1);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
<?php endif; ?>
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
