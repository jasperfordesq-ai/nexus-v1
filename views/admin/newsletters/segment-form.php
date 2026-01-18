<?php
/**
 * Newsletter Segment Create/Edit Form
 */
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();
$isEdit = isset($segment);
$action = $isEdit
    ? $basePath . "/admin/newsletters/segments/update/" . $segment['id']
    : $basePath . "/admin/newsletters/segments/store";

$fields = $fields ?? [];
$groups = $groups ?? [];
$counties = $counties ?? [];
$towns = $towns ?? [];
$existingConditions = ($isEdit && !empty($segment['rules']['conditions'])) ? $segment['rules']['conditions'] : [];
$matchType = ($isEdit && !empty($segment['rules']['match'])) ? $segment['rules']['match'] : 'all';

// Hero settings for modern layout
$hTitle = $isEdit ? 'Edit Segment' : 'Create Segment';
$hSubtitle = 'Define rules to target specific groups of members';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Segments';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 900px; margin: 0 auto;">

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            <div style="width: 32px; height: 32px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-xmark" style="color: white;"></i>
            </div>
            <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Navigation -->
    <div style="margin-bottom: 24px;">
        <a href="<?= $basePath ?>/admin/newsletters/segments" style="color: #6b7280; text-decoration: none; font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-left"></i> Back to Segments
        </a>
    </div>

    <?php if (!$isEdit): ?>
    <!-- Smart Segment Suggestions -->
    <div id="smart-suggestions-section" class="nexus-card" style="margin-bottom: 24px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #bfdbfe;">
        <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fa-solid fa-lightbulb" style="color: white; font-size: 1.2rem;"></i>
            </div>
            <div>
                <h3 style="margin: 0 0 4px; font-size: 1.1rem; font-weight: 700; color: #1e40af;">Smart Suggestions</h3>
                <p style="margin: 0; color: #3b82f6; font-size: 0.9rem;">AI-powered segment recommendations based on your member data</p>
            </div>
        </div>

        <div id="suggestions-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            <div style="text-align: center; color: #6b7280; padding: 20px; grid-column: 1/-1;">
                <i class="fa-solid fa-spinner fa-spin"></i> Loading suggestions...
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form action="<?= $action ?>" method="POST" id="segment-form">
        <?= \Nexus\Core\Csrf::input() ?>

        <!-- Basic Info -->
        <div class="nexus-card" style="margin-bottom: 20px;">
            <h3 style="margin-top: 0; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb;">
                Segment Details
            </h3>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">
                    Segment Name <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" required
                    value="<?= $isEdit ? htmlspecialchars($segment['name']) : '' ?>"
                    class="nexus-input" style="width: 100%; max-width: 400px;"
                    placeholder="e.g., Dublin Members, Active Sellers">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Description</label>
                <textarea name="description" class="nexus-input" style="width: 100%; height: 80px;"
                    placeholder="Brief description of who this segment targets..."><?= $isEdit ? htmlspecialchars($segment['description'] ?? '') : '' ?></textarea>
            </div>

            <?php if ($isEdit): ?>
            <div>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1"
                        <?= $segment['is_active'] ? 'checked' : '' ?>
                        style="width: 18px; height: 18px;">
                    <span style="font-weight: 600;">Active</span>
                    <span style="color: #6b7280; font-size: 0.9rem;">Only active segments appear in newsletter targeting</span>
                </label>
            </div>
            <?php endif; ?>
        </div>

        <!-- Rules -->
        <div class="nexus-card" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb;">
                <h3 style="margin: 0;">Targeting Rules</h3>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="color: #6b7280;">Match</span>
                    <select name="match" class="nexus-input" style="width: auto;">
                        <option value="all" <?= $matchType === 'all' ? 'selected' : '' ?>>ALL rules (AND)</option>
                        <option value="any" <?= $matchType === 'any' ? 'selected' : '' ?>>ANY rule (OR)</option>
                    </select>
                </div>
            </div>

            <!-- Rules Container -->
            <div id="rules-container">
                <?php if (!empty($existingConditions)): ?>
                    <?php foreach ($existingConditions as $index => $condition): ?>
                        <!-- Existing rules will be rendered by JavaScript -->
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Add Rule Button -->
            <button type="button" id="add-rule-btn" style="background: #f3f4f6; border: 2px dashed #d1d5db; padding: 12px 20px; border-radius: 8px; cursor: pointer; width: 100%; color: #6b7280; font-weight: 500;">
                + Add Rule
            </button>

            <!-- Preview -->
            <div id="preview-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <button type="button" id="preview-btn" class="nexus-btn" style="background: #e0f2fe; color: #0369a1;">
                    Preview Matching Members
                </button>
                <span id="preview-result" style="margin-left: 15px; font-weight: 600;"></span>
            </div>
        </div>

        <!-- Submit -->
        <div style="display: flex; gap: 15px;">
            <button type="submit" class="nexus-btn" style="background: #6366f1; color: white;">
                <?= $isEdit ? 'Update Segment' : 'Create Segment' ?>
            </button>
            <a href="<?= $basePath ?>/admin/newsletters/segments" class="nexus-btn" style="background: #e5e7eb; color: #374151;">
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- Rule Template (hidden) -->
<template id="rule-template">
    <div class="rule-row" style="background: #f9fafb; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative;">
        <button type="button" class="remove-rule" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem;">&times;</button>

        <div style="display: grid; grid-template-columns: 200px 150px 1fr; gap: 15px; align-items: start;">
            <!-- Field Select -->
            <div>
                <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Field</label>
                <select name="rule_field[]" class="nexus-input field-select" style="width: 100%;">
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
            <div class="operator-container">
                <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Operator</label>
                <select name="rule_operator[]" class="nexus-input operator-select" style="width: 100%;">
                    <option value="equals">equals</option>
                </select>
            </div>

            <!-- Value Input (changes based on field type) -->
            <div class="value-container">
                <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Value</label>
                <input type="text" name="rule_value[]" class="nexus-input value-input" style="width: 100%;" placeholder="Enter value...">
            </div>
        </div>

        <!-- Special field containers (hidden by default) -->
        <div class="geo-radius-fields" style="display: none; margin-top: 15px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 120px; gap: 15px;">
                <div>
                    <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Center Location (search)</label>
                    <input type="text" class="geo-search nexus-input" style="width: 100%;" placeholder="Search for a place...">
                    <input type="hidden" name="geo_lat[]" class="geo-lat" value="0">
                    <input type="hidden" name="geo_lng[]" class="geo-lng" value="0">
                </div>
                <div>
                    <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Selected Location</label>
                    <div class="geo-selected" style="padding: 8px 12px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px; min-height: 38px; color: #6b7280;">
                        Click to search or select on map
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Radius (km)</label>
                    <input type="number" name="geo_radius[]" class="nexus-input geo-radius-input" value="50" min="1" max="500" style="width: 100%;">
                </div>
            </div>
        </div>

        <div class="county-fields" style="display: none; margin-top: 15px;">
            <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Select Counties</label>
            <div class="county-checkboxes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px;">
                <?php foreach ($counties as $county): ?>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" name="county_value[0][]" value="<?= htmlspecialchars($county) ?>">
                        <span style="font-size: 0.9rem;"><?= htmlspecialchars($county) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="town-fields" style="display: none; margin-top: 15px;">
            <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Select Towns/Cities</label>
            <div style="margin-bottom: 10px;">
                <input type="text" class="town-search nexus-input" style="width: 100%; max-width: 300px;" placeholder="Search towns...">
            </div>
            <div class="town-checkboxes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; max-height: 250px; overflow-y: auto; padding: 10px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px;">
                <?php foreach ($towns as $town): ?>
                    <label class="town-option" style="display: flex; align-items: center; gap: 5px; cursor: pointer;" data-town="<?= strtolower(htmlspecialchars($town)) ?>">
                        <input type="checkbox" name="town_value[0][]" value="<?= htmlspecialchars($town) ?>">
                        <span style="font-size: 0.9rem;"><?= htmlspecialchars($town) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 10px;">
                <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Or enter custom towns (comma-separated)</label>
                <input type="text" name="town_custom[0]" class="nexus-input town-custom" style="width: 100%;" placeholder="e.g., Ballymun, Tallaght, Blanchardstown">
            </div>
        </div>

        <div class="group-fields" style="display: none; margin-top: 15px;">
            <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Select Groups</label>
            <div class="group-checkboxes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px;">
                <?php if (empty($groups)): ?>
                    <span style="color: #6b7280; font-style: italic;">No groups available</span>
                <?php else: ?>
                    <?php foreach ($groups as $group): ?>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="checkbox" name="group_value[0][]" value="<?= $group['id'] ?>">
                            <span style="font-size: 0.9rem;"><?= htmlspecialchars($group['name']) ?></span>
                            <span style="color: #6b7280; font-size: 0.8rem;">(<?= $group['member_count'] ?>)</span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</template>

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
        let selectHtml = '<select name="rule_value[]" class="nexus-input value-input" style="width: 100%;">';
        config.options.forEach(opt => {
            const selected = opt.value === value ? 'selected' : '';
            selectHtml += `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
        });
        selectHtml += '</select>';
        valueContainer.innerHTML = '<label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Value</label>' + selectHtml;
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
        let inputHtml = `<input type="${config.type === 'number' ? 'number' : 'text'}" name="rule_value[]" class="nexus-input value-input" style="width: 100%;" placeholder="${config.placeholder || 'Enter value...'}">`;
        valueContainer.innerHTML = '<label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 5px;">Value</label>' + inputHtml;
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
        result.innerHTML = '<span style="color: #f59e0b;">Add at least one rule to preview</span>';
        return;
    }

    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
    result.innerHTML = '<span style="color: #6b7280;"><i class="fa-solid fa-spinner fa-spin"></i> Calculating...</span>';

    fetch('<?= $basePath ?>/admin/newsletters/segments/preview', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const count = data.count;
            const color = count > 0 ? '#059669' : '#f59e0b';
            const icon = count > 0 ? 'fa-users' : 'fa-exclamation-circle';
            result.innerHTML = `<span style="color: ${color};"><i class="fa-solid ${icon}"></i> ${count} member${count !== 1 ? 's' : ''} match</span>`;
        } else {
            result.innerHTML = `<span style="color: #ef4444;"><i class="fa-solid fa-times-circle"></i> ${data.error}</span>`;
        }
    })
    .catch(err => {
        result.innerHTML = '<span style="color: #ef4444;"><i class="fa-solid fa-times-circle"></i> Error loading preview</span>';
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
        // For now, allow manual lat/lng input.
        // TODO: Integrate with Mapbox geocoding API
        const row = e.target.closest('.rule-row');
        const lat = prompt('Enter latitude:', row.querySelector('.geo-lat').value || '53.349805');
        const lng = prompt('Enter longitude:', row.querySelector('.geo-lng').value || '-6.26031');

        if (lat && lng) {
            row.querySelector('.geo-lat').value = lat;
            row.querySelector('.geo-lng').value = lng;
            row.querySelector('.geo-selected').textContent = `Lat: ${lat}, Lng: ${lng}`;
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

// =========================================================================
// Smart Suggestions (only on create page)
// =========================================================================
<?php if (!$isEdit): ?>
document.addEventListener('DOMContentLoaded', function() {
    loadSmartSuggestions();
});

function loadSmartSuggestions() {
    const container = document.getElementById('suggestions-container');

    fetch('<?= $basePath ?>/admin/newsletters/segments/suggestions')
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.suggestions || data.suggestions.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: #6b7280; padding: 20px; grid-column: 1/-1;">No suggestions available yet. Send a few newsletters to get personalized recommendations.</div>';
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
            container.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 20px; grid-column: 1/-1;">Failed to load suggestions</div>';
        });
}

function createSuggestionCard(suggestion) {
    const card = document.createElement('div');
    card.className = 'suggestion-card';
    card.style.cssText = 'background: white; border-radius: 12px; padding: 20px; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05);';

    card.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, ${suggestion.color} 0%, ${adjustColor(suggestion.color, -30)} 100%);">
                <i class="fa-solid ${suggestion.icon}" style="color: white; font-size: 1rem;"></i>
            </div>
            <div>
                <div style="font-weight: 700; color: #111827;">${escapeHtml(suggestion.name)}</div>
                <div style="font-size: 0.85rem; color: #6b7280;">${suggestion.member_count} members</div>
            </div>
        </div>
        <p style="font-size: 0.9rem; color: #374151; margin: 0 0 12px;">${escapeHtml(suggestion.description)}</p>
        <div style="font-size: 0.85rem; color: #6b7280; background: #f3f4f6; padding: 10px; border-radius: 8px; margin-bottom: 12px;">${escapeHtml(suggestion.explanation)}</div>
        <button type="button" class="use-suggestion-btn" data-id="${suggestion.id}" style="width: 100%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fa-solid fa-plus"></i> Create This Segment
        </button>
    `;

    // Hover effects
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = '';
        this.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';
    });

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

    // Show loading state on button
    const btn = buttonElement || document.querySelector(`[data-id="${suggestionId}"]`);
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
    }

    fetch('<?= $basePath ?>/admin/newsletters/segments/from-suggestion', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.redirect) {
            // Show success state before redirect
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
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    notification.style.background = type === 'error' ? '#ef4444' : type === 'success' ? '#10b981' : '#3b82f6';
    notification.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-times-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i> ${message}`;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function adjustColor(hex, amount) {
    // Darken/lighten a hex color
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

<style>
.rule-row {
    transition: all 0.2s ease;
}
.rule-row:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.remove-rule:hover {
    transform: scale(1.2);
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
.suggestion-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
</style>

    </div>
</div>

<style>
    .newsletter-admin-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    @media (min-width: 601px) {
        .newsletter-admin-wrapper {
            padding-top: 140px;
        }
    }

    @media (max-width: 600px) {
        .newsletter-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }
    }
</style>

<?php
else {
    require __DIR__ . '/../../layouts/modern/footer.php';
}
?>
