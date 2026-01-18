<?php
/**
 * AI Content Generation Button Partial
 *
 * Usage:
 *   <?php include __DIR__ . '/../partials/ai-generate-button.php'; ?>
 *
 * Required variables:
 *   $aiGenerateType - 'listing' or 'event'
 *   $aiTitleField - ID of the title input field (default: 'title')
 *   $aiDescriptionField - ID of the description textarea (default: 'description')
 *   $aiTypeField - ID of the type select/radio (optional, for listings: 'type')
 *
 * Optional variables:
 *   $aiButtonText - Custom button text
 *   $aiButtonClass - Additional CSS classes
 */

$aiGenerateType = $aiGenerateType ?? 'listing';
$aiTitleField = $aiTitleField ?? 'title';
$aiDescriptionField = $aiDescriptionField ?? 'description';
$aiTypeField = $aiTypeField ?? 'type';
$aiButtonText = $aiButtonText ?? 'Generate with AI';
$aiButtonClass = $aiButtonClass ?? '';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="ai-generate-wrapper" id="ai-generate-wrapper-<?= $aiGenerateType ?>">
    <button type="button"
            class="ai-generate-btn <?= $aiButtonClass ?>"
            id="ai-generate-btn-<?= $aiGenerateType ?>"
            data-type="<?= $aiGenerateType ?>"
            data-title-field="<?= $aiTitleField ?>"
            data-description-field="<?= $aiDescriptionField ?>"
            data-type-field="<?= $aiTypeField ?>">
        <span class="ai-btn-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
        </span>
        <span class="ai-btn-text"><?= htmlspecialchars($aiButtonText) ?></span>
        <span class="ai-btn-loading" style="display: none;">
            <svg class="ai-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
            </svg>
            Generating...
        </span>
    </button>
    <div class="ai-generate-status" id="ai-status-<?= $aiGenerateType ?>"></div>
</div>

<style>
/* AI Generate Button Styles */
.ai-generate-wrapper {
    margin-bottom: 12px;
}

.ai-generate-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 0.9rem;
    font-weight: 600;
    font-family: inherit;
    color: #ffffff;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
    position: relative;
    overflow: hidden;
}

.ai-generate-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.ai-generate-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
}

.ai-generate-btn:hover::before {
    left: 100%;
}

.ai-generate-btn:active {
    transform: translateY(0);
}

.ai-generate-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.ai-generate-btn.loading .ai-btn-icon,
.ai-generate-btn.loading .ai-btn-text {
    display: none;
}

.ai-generate-btn.loading .ai-btn-loading {
    display: inline-flex !important;
    align-items: center;
    gap: 8px;
}

.ai-spinner {
    animation: ai-spin 1s linear infinite;
}

@keyframes ai-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.ai-generate-status {
    margin-top: 8px;
    font-size: 0.85rem;
    min-height: 20px;
}

.ai-generate-status.success {
    color: #10b981;
}

.ai-generate-status.error {
    color: #ef4444;
}

/* Dark Mode */
[data-theme="dark"] .ai-generate-btn {
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.25);
}

[data-theme="dark"] .ai-generate-btn:hover {
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
}

/* Responsive */
@media (max-width: 600px) {
    .ai-generate-btn {
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
    }
}

/* Alternative styles for different contexts */
.ai-generate-btn.ai-btn-compact {
    padding: 8px 14px;
    font-size: 0.85rem;
    border-radius: 10px;
}

.ai-generate-btn.ai-btn-outline {
    background: transparent;
    border: 2px solid #6366f1;
    color: #6366f1;
    box-shadow: none;
}

.ai-generate-btn.ai-btn-outline:hover {
    background: rgba(99, 102, 241, 0.1);
    box-shadow: none;
}

[data-theme="dark"] .ai-generate-btn.ai-btn-outline {
    border-color: #818cf8;
    color: #a5b4fc;
}

[data-theme="dark"] .ai-generate-btn.ai-btn-outline:hover {
    background: rgba(99, 102, 241, 0.15);
}
</style>

<script>
(function() {
    // Prevent multiple initializations
    if (window.aiGenerateInitialized) return;
    window.aiGenerateInitialized = true;

    const basePath = '<?= $basePath ?>';

    /**
     * Collect all relevant form context for better AI generation
     */
    function collectFormContext(type) {
        const context = {};

        if (type === 'listing') {
            // Category - try multiple possible selectors
            const categorySelect = document.getElementById('category_id') ||
                                   document.querySelector('select[name="category_id"]');
            if (categorySelect && categorySelect.value) {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                context.category = selectedOption?.text?.trim() || '';
                context.category_id = categorySelect.value;
            }

            // Listing type (offer/request) - try multiple patterns
            const typeInput = document.querySelector('input[name="type"]:checked') ||
                              document.querySelector('input[name="listing_type"]:checked');
            if (typeInput) {
                context.listing_type = typeInput.value;
            }

            // Selected attributes (checkboxes) - more robust selector
            const selectedAttrs = [];
            document.querySelectorAll('#attributes-container input[type="checkbox"]:checked, .attribute-item input[type="checkbox"]:checked, input[name^="attributes["]:checked').forEach(cb => {
                // Try to find label text in various ways
                let label = cb.closest('label')?.querySelector('span')?.textContent;
                if (!label) {
                    label = cb.closest('.holo-attribute-item')?.querySelector('span')?.textContent;
                }
                if (!label) {
                    const parentLabel = cb.closest('label');
                    if (parentLabel) {
                        label = parentLabel.textContent.replace(cb.value, '').trim();
                    }
                }
                if (label) selectedAttrs.push(label.trim());
            });
            if (selectedAttrs.length > 0) {
                context.attributes = selectedAttrs;
            }

            // Selected SDGs - more robust selector
            const selectedSdgs = [];
            document.querySelectorAll('input[name="sdg_goals[]"]:checked').forEach(cb => {
                const card = cb.closest('.holo-sdg-card, .sdg-card, label');
                let label = card?.querySelector('.sdg-label')?.textContent;
                if (!label) {
                    label = card?.textContent?.trim();
                }
                if (label) selectedSdgs.push(label.trim());
            });
            if (selectedSdgs.length > 0) {
                context.sdg_goals = selectedSdgs;
            }

            // Existing description (for improvement mode)
            const descField = document.getElementById('description') ||
                              document.querySelector('textarea[name="description"]');
            if (descField && descField.value.trim().length > 20) {
                context.existing_description = descField.value.trim();
            }

        } else if (type === 'event') {
            // Category
            const categorySelect = document.getElementById('category_id') ||
                                   document.querySelector('select[name="category_id"]');
            if (categorySelect && categorySelect.value) {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                context.category = selectedOption?.text?.trim() || '';
            }

            // Location
            const locationField = document.getElementById('location') ||
                                  document.querySelector('input[name="location"]');
            if (locationField && locationField.value) {
                context.location = locationField.value.trim();
            }

            // Date/Time
            const startDate = (document.getElementById('start_date') || document.querySelector('input[name="start_date"]'))?.value;
            const startTime = (document.getElementById('start_time') || document.querySelector('input[name="start_time"]'))?.value;
            const endDate = (document.getElementById('end_date') || document.querySelector('input[name="end_date"]'))?.value;
            const endTime = (document.getElementById('end_time') || document.querySelector('input[name="end_time"]'))?.value;

            if (startDate) {
                context.start_date = startDate;
                if (startTime) context.start_time = startTime;
            }
            if (endDate) {
                context.end_date = endDate;
                if (endTime) context.end_time = endTime;
            }

            // Hub/Group
            const groupSelect = document.getElementById('group_id') ||
                                document.querySelector('select[name="group_id"]');
            if (groupSelect && groupSelect.value) {
                const selectedOption = groupSelect.options[groupSelect.selectedIndex];
                context.group_name = selectedOption?.text?.trim() || '';
            }

            // Selected SDGs
            const selectedSdgs = [];
            document.querySelectorAll('input[name="sdg_goals[]"]:checked').forEach(cb => {
                const card = cb.closest('.holo-sdg-card, .sdg-card, label');
                let label = card?.querySelector('.sdg-label')?.textContent;
                if (!label) {
                    label = card?.textContent?.trim();
                }
                if (label) selectedSdgs.push(label.trim());
            });
            if (selectedSdgs.length > 0) {
                context.sdg_goals = selectedSdgs;
            }

            // Existing description
            const descField = document.getElementById('description') ||
                              document.querySelector('textarea[name="description"]');
            if (descField && descField.value.trim().length > 20) {
                context.existing_description = descField.value.trim();
            }
        }

        // Debug: Log what was collected (remove in production)
        console.log('AI Generate - Collected context:', context);

        return context;
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.ai-generate-btn');
        if (!btn) return;

        const type = btn.dataset.type;
        const titleFieldId = btn.dataset.titleField;
        const descFieldId = btn.dataset.descriptionField;
        const typeFieldId = btn.dataset.typeField;
        const statusEl = document.getElementById('ai-status-' + type);

        const titleField = document.getElementById(titleFieldId);
        const descField = document.getElementById(descFieldId);

        // For listings: validate that description/prompt field has content (user's prompt)
        // For events: still use title as the primary input
        if (type === 'listing') {
            if (!descField || !descField.value.trim()) {
                if (statusEl) {
                    statusEl.textContent = 'Please enter your prompt in the box below';
                    statusEl.className = 'ai-generate-status error';
                }
                descField?.focus();
                return;
            }
        } else {
            // Events still use title validation
            if (!titleField || !titleField.value.trim()) {
                if (statusEl) {
                    statusEl.textContent = 'Please enter a title first';
                    statusEl.className = 'ai-generate-status error';
                }
                titleField?.focus();
                return;
            }
        }

        // Set loading state
        btn.classList.add('loading');
        btn.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Generating content...';
            statusEl.className = 'ai-generate-status';
        }

        // Build request body with full context
        const formContext = collectFormContext(type);

        // For listings: use description field as the user's prompt
        const body = {
            title: titleField?.value?.trim() || '',
            context: formContext
        };

        // For listings, add the user's prompt from the description field
        if (type === 'listing' && descField) {
            body.context.user_prompt = descField.value.trim();
        }

        // Legacy support for type field
        if (type === 'listing') {
            const typeInput = document.querySelector(`input[name="${typeFieldId}"]:checked`) ||
                             document.getElementById(typeFieldId);
            if (typeInput) {
                body.type = typeInput.value;
            }
        }

        // Make API request
        fetch(basePath + '/api/ai/generate/' + type, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        })
        .then(response => response.json())
        .then(data => {
            btn.classList.remove('loading');
            btn.disabled = false;

            if (data.success && data.content) {
                // Insert generated content
                if (descField) {
                    descField.value = data.content;
                    descField.focus();
                    // Trigger input event for any listeners
                    descField.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (statusEl) {
                    statusEl.textContent = '✓ Description generated!';
                    statusEl.className = 'ai-generate-status success';
                    setTimeout(() => {
                        statusEl.textContent = '';
                    }, 3000);
                }
            } else {
                throw new Error(data.error || 'Failed to generate content');
            }
        })
        .catch(error => {
            btn.classList.remove('loading');
            btn.disabled = false;

            let errorMsg = 'Generation failed. ';
            if (error.message.includes('not enabled')) {
                errorMsg = 'AI content generation is not enabled. Contact admin.';
            } else if (error.message.includes('limit')) {
                errorMsg = 'Usage limit reached. Try again tomorrow.';
            } else {
                errorMsg += error.message;
            }

            if (statusEl) {
                statusEl.textContent = errorMsg;
                statusEl.className = 'ai-generate-status error';
            }
            console.error('AI Generate Error:', error);
        });
    });
})();
</script>
