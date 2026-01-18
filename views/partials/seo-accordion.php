<?php
/**
 * SEO Accordion Partial
 *
 * Reusable SEO editing component for entity edit forms.
 * Uses the same accordion pattern as SDG goals.
 *
 * Variables expected:
 * - $seo: array|null - SEO metadata from SeoMetadata::get()
 * - $entityTitle: string - Current entity title (for preview)
 * - $entityUrl: string - Current entity URL (for preview)
 *
 * Form fields use seo[field] naming convention.
 */

$seo = $seo ?? [];
$entityTitle = $entityTitle ?? '';
$entityUrl = $entityUrl ?? '';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<details class="holo-sdg-accordion seo-accordion" style="margin-bottom: 32px;">
    <summary class="holo-sdg-header">
        <span><i class="fa-solid fa-magnifying-glass-chart" style="margin-right: 8px;"></i>SEO Settings <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span></span>
        <i class="fa-solid fa-chevron-down seo-accordion-icon"></i>
    </summary>
    <div class="holo-sdg-content seo-accordion-content" style="padding-top: 20px;">

        <!-- SERP Preview -->
        <div class="seo-serp-preview" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
            <div style="font-size: 0.75rem; color: #70757a; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Google Search Preview</div>
            <div class="serp-title" id="seoPreviewTitle" style="font-size: 1.25rem; color: #1a0dab; margin-bottom: 4px; font-family: Arial, sans-serif; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars($seo['meta_title'] ?? $entityTitle ?: 'Page Title') ?>
            </div>
            <div class="serp-url" id="seoPreviewUrl" style="font-size: 0.875rem; color: #006621; margin-bottom: 4px; font-family: Arial, sans-serif;">
                <?= htmlspecialchars($entityUrl ?: ($basePath ?: 'https://example.com')) ?>
            </div>
            <div class="serp-description" id="seoPreviewDesc" style="font-size: 0.875rem; color: #545454; font-family: Arial, sans-serif; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                <?= htmlspecialchars($seo['meta_description'] ?? 'Enter a meta description to see how your page will appear in search results...') ?>
            </div>
        </div>

        <!-- Meta Title -->
        <div class="holo-section" style="margin-bottom: 20px;">
            <label class="holo-label" for="seo_meta_title" style="display: block; font-weight: 600; margin-bottom: 8px;">
                Meta Title
            </label>
            <input type="text"
                   name="seo[meta_title]"
                   id="seo_meta_title"
                   class="holo-input"
                   style="width: 100%; padding: 12px; border: 1px solid var(--htb-border, #e2e8f0); border-radius: 8px; font-size: 1rem;"
                   value="<?= htmlspecialchars($seo['meta_title'] ?? '') ?>"
                   placeholder="Custom title for search engines (50-60 characters)"
                   maxlength="70"
                   oninput="updateSeoPreview()">
            <div style="display: flex; justify-content: space-between; margin-top: 6px;">
                <span style="font-size: 0.8rem; color: var(--htb-text-muted, #6b7280);">Recommended: 50-60 characters</span>
                <span style="font-size: 0.8rem; color: var(--htb-text-muted, #6b7280);"><span id="titleCharCount">0</span>/60</span>
            </div>
        </div>

        <!-- Meta Description -->
        <div class="holo-section" style="margin-bottom: 20px;">
            <label class="holo-label" for="seo_meta_description" style="display: block; font-weight: 600; margin-bottom: 8px;">
                Meta Description
            </label>
            <textarea name="seo[meta_description]"
                      id="seo_meta_description"
                      class="holo-textarea"
                      style="width: 100%; padding: 12px; border: 1px solid var(--htb-border, #e2e8f0); border-radius: 8px; font-size: 1rem; resize: vertical; min-height: 80px;"
                      rows="3"
                      placeholder="Description shown in search results (150-160 characters)"
                      maxlength="160"
                      oninput="updateSeoPreview()"><?= htmlspecialchars($seo['meta_description'] ?? '') ?></textarea>
            <div style="display: flex; justify-content: space-between; margin-top: 6px;">
                <span style="font-size: 0.8rem; color: var(--htb-text-muted, #6b7280);">Recommended: 150-160 characters</span>
                <span style="font-size: 0.8rem; color: var(--htb-text-muted, #6b7280);"><span id="descCharCount">0</span>/160</span>
            </div>
        </div>

        <!-- Advanced Fields (Collapsible) -->
        <details class="seo-advanced" style="margin-top: 16px;">
            <summary style="cursor: pointer; font-weight: 600; color: var(--htb-primary, #6366f1); padding: 8px 0;">
                <i class="fa-solid fa-gear" style="margin-right: 6px;"></i>Advanced Options
            </summary>
            <div style="padding-top: 16px;">

                <!-- Meta Keywords -->
                <div class="holo-section" style="margin-bottom: 20px;">
                    <label class="holo-label" for="seo_meta_keywords" style="display: block; font-weight: 600; margin-bottom: 8px;">
                        Meta Keywords <span style="font-weight: 400; opacity: 0.6;">(Optional)</span>
                    </label>
                    <input type="text"
                           name="seo[meta_keywords]"
                           id="seo_meta_keywords"
                           class="holo-input"
                           style="width: 100%; padding: 12px; border: 1px solid var(--htb-border, #e2e8f0); border-radius: 8px; font-size: 1rem;"
                           value="<?= htmlspecialchars($seo['meta_keywords'] ?? '') ?>"
                           placeholder="keyword1, keyword2, keyword3">
                    <div style="font-size: 0.8rem; color: var(--htb-text-muted, #6b7280); margin-top: 6px;">
                        Comma-separated keywords (less important for modern SEO)
                    </div>
                </div>

                <!-- Canonical URL -->
                <div class="holo-section" style="margin-bottom: 20px;">
                    <label class="holo-label" for="seo_canonical_url" style="display: block; font-weight: 600; margin-bottom: 8px;">
                        Canonical URL <span style="font-weight: 400; opacity: 0.6;">(Advanced)</span>
                    </label>
                    <input type="url"
                           name="seo[canonical_url]"
                           id="seo_canonical_url"
                           class="holo-input"
                           style="width: 100%; padding: 12px; border: 1px solid var(--htb-border, #e2e8f0); border-radius: 8px; font-size: 1rem;"
                           value="<?= htmlspecialchars($seo['canonical_url'] ?? '') ?>"
                           placeholder="https://example.com/preferred-url">
                    <div style="font-size: 0.8rem; color: var(--htb-text-muted, #6b7280); margin-top: 6px;">
                        Leave blank to use the default URL. Use to prevent duplicate content issues.
                    </div>
                </div>

                <!-- OG Image URL -->
                <div class="holo-section" style="margin-bottom: 20px;">
                    <label class="holo-label" for="seo_og_image_url" style="display: block; font-weight: 600; margin-bottom: 8px;">
                        Social Share Image
                    </label>
                    <input type="url"
                           name="seo[og_image_url]"
                           id="seo_og_image_url"
                           class="holo-input"
                           style="width: 100%; padding: 12px; border: 1px solid var(--htb-border, #e2e8f0); border-radius: 8px; font-size: 1rem;"
                           value="<?= htmlspecialchars($seo['og_image_url'] ?? '') ?>"
                           placeholder="https://example.com/image.jpg">
                    <div style="font-size: 0.8rem; color: var(--htb-text-muted, #6b7280); margin-top: 6px;">
                        Image shown when shared on Facebook, Twitter, LinkedIn. Recommended: 1200x630 pixels.
                    </div>
                </div>

                <!-- NoIndex -->
                <div class="holo-section" style="margin-bottom: 8px;">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; padding: 12px; background: var(--htb-bg-warning, #fef3c7); border-radius: 8px; border: 1px solid var(--htb-border-warning, #fcd34d);">
                        <input type="checkbox"
                               name="seo[noindex]"
                               value="1"
                               style="margin-top: 3px; width: 18px; height: 18px;"
                               <?= !empty($seo['noindex']) ? 'checked' : '' ?>>
                        <div>
                            <span style="font-weight: 600; color: var(--htb-text-warning, #92400e);">Hide from search engines (noindex)</span>
                            <div style="font-size: 0.8rem; color: var(--htb-text-muted, #a16207); margin-top: 4px;">
                                Check this to prevent Google and other search engines from indexing this page.
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </details>
    </div>
</details>

<script>
// SEO Preview Update
function updateSeoPreview() {
    const titleInput = document.getElementById('seo_meta_title');
    const descInput = document.getElementById('seo_meta_description');
    const titlePreview = document.getElementById('seoPreviewTitle');
    const descPreview = document.getElementById('seoPreviewDesc');
    const titleCount = document.getElementById('titleCharCount');
    const descCount = document.getElementById('descCharCount');

    // Get entity title as fallback
    const entityTitleField = document.querySelector('input[name="title"]');
    const fallbackTitle = entityTitleField ? entityTitleField.value : '<?= addslashes($entityTitle) ?>';

    // Update preview
    const titleValue = titleInput.value || fallbackTitle || 'Page Title';
    const descValue = descInput.value || 'Enter a meta description to see how your page will appear in search results...';

    titlePreview.textContent = titleValue;
    descPreview.textContent = descValue;

    // Update character counts
    titleCount.textContent = titleInput.value.length;
    descCount.textContent = descInput.value.length;

    // Color feedback for character limits
    titleCount.style.color = titleInput.value.length > 60 ? '#dc2626' : (titleInput.value.length > 50 ? '#f59e0b' : '#6b7280');
    descCount.style.color = descInput.value.length > 160 ? '#dc2626' : (descInput.value.length > 150 ? '#f59e0b' : '#6b7280');
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    updateSeoPreview();

    // Also update preview when main title field changes
    const titleField = document.querySelector('input[name="title"]');
    if (titleField) {
        titleField.addEventListener('input', function() {
            const seoTitle = document.getElementById('seo_meta_title');
            if (!seoTitle.value) {
                updateSeoPreview();
            }
        });
    }
});
</script>

<style>
/* SEO Accordion Styles */
.seo-accordion .holo-sdg-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfeff 100%);
    border: 1px solid #a7f3d0;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    color: #065f46;
    transition: all 0.2s ease;
}

.seo-accordion .holo-sdg-header:hover {
    background: linear-gradient(135deg, #dcfce7 0%, #cffafe 100%);
}

.seo-accordion[open] .holo-sdg-header {
    border-radius: 12px 12px 0 0;
    border-bottom: none;
}

.seo-accordion .seo-accordion-content {
    border: 1px solid #a7f3d0;
    border-top: none;
    border-radius: 0 0 12px 12px;
    padding: 20px;
    background: #fff;
}

.seo-accordion .seo-accordion-icon {
    transition: transform 0.2s ease;
}

.seo-accordion[open] .seo-accordion-icon {
    transform: rotate(180deg);
}

/* Dark Mode Support */
[data-theme="dark"] .seo-accordion .holo-sdg-header {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
    border-color: rgba(16, 185, 129, 0.3);
    color: #6ee7b7;
}

[data-theme="dark"] .seo-accordion .holo-sdg-header:hover {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(6, 182, 212, 0.15) 100%);
}

[data-theme="dark"] .seo-accordion .seo-accordion-content {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(16, 185, 129, 0.3);
}

[data-theme="dark"] .seo-serp-preview {
    background: #1e293b !important;
    border-color: #334155 !important;
}

[data-theme="dark"] .seo-serp-preview .serp-title {
    color: #8ab4f8 !important;
}

[data-theme="dark"] .seo-serp-preview .serp-url {
    color: #34d399 !important;
}

[data-theme="dark"] .seo-serp-preview .serp-description {
    color: #94a3b8 !important;
}

[data-theme="dark"] .seo-advanced summary {
    color: #818cf8 !important;
}

[data-theme="dark"] .holo-input,
[data-theme="dark"] .holo-textarea {
    background: rgba(30, 41, 59, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: #e2e8f0 !important;
}

[data-theme="dark"] label[style*="background: var(--htb-bg-warning"] {
    background: rgba(251, 191, 36, 0.15) !important;
    border-color: rgba(251, 191, 36, 0.3) !important;
}

[data-theme="dark"] label[style*="background: var(--htb-bg-warning"] span[style*="color: var(--htb-text-warning"] {
    color: #fcd34d !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .seo-accordion .holo-sdg-header {
        padding: 14px 16px;
        font-size: 0.95rem;
    }

    .seo-accordion .seo-accordion-content {
        padding: 16px;
    }

    .seo-serp-preview {
        padding: 12px !important;
    }

    .seo-serp-preview .serp-title {
        font-size: 1.1rem !important;
    }

    .holo-input,
    .holo-textarea {
        font-size: 16px !important; /* Prevent iOS zoom */
    }
}
</style>
