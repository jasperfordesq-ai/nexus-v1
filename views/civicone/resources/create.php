<?php
/**
 * CivicOne Resources Create - Upload Resource Form
 * Template D: Form/Flow (Section 10.7)
 * File upload form with holographic glassmorphism design
 * WCAG 2.1 AA Compliant
 */
require __DIR__ . '/../../layouts/civicone/header.php';
?>
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-resources-form.min.css?v=<?= time() ?>">

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="holo-resource-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1" aria-hidden="true"></div>
    <div class="holo-orb holo-orb-2" aria-hidden="true"></div>
    <div class="holo-orb holo-orb-3" aria-hidden="true"></div>

    <div class="holo-glass-card">
        <div class="holo-header">
            <div class="holo-header-icon" aria-hidden="true">
                <i class="fa-solid fa-cloud-arrow-up"></i>
            </div>
            <h1 class="holo-title">Upload Resource</h1>
            <p class="holo-subtitle">Share useful documents, guides, or forms with the community.</p>
        </div>

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/store" method="POST" enctype="multipart/form-data" id="uploadForm">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="holo-form-group">
                <label for="title" class="holo-label">Document Title</label>
                <input type="text" name="title" id="title" class="holo-input" required
                       placeholder="e.g. Volunteer Guide 2025">
            </div>

            <!-- Description -->
            <div class="holo-form-group">
                <label for="description" class="holo-label">Description</label>
                <textarea name="description" id="description" class="holo-input" rows="3"
                          placeholder="Briefly describe what this file contains..."></textarea>
            </div>

            <!-- Category -->
            <div class="holo-form-group">
                <label for="category_id" class="holo-label">Category</label>
                <select name="category_id" id="category_id" class="holo-select">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- File Upload -->
            <div class="holo-form-group">
                <label for="fileInput" class="holo-label">Select File</label>
                <div class="holo-upload-zone" id="uploadZone">
                    <input type="file" name="file" required class="holo-file-input" id="fileInput">
                    <div class="holo-upload-icon" aria-hidden="true">
                        <i class="fa-solid fa-file-arrow-up"></i>
                    </div>
                    <div class="holo-upload-text">Click to select or drag and drop</div>
                    <div class="holo-upload-hint">PDF, DOC, DOCX, ZIP, JPG (Max 5MB)</div>
                    <div class="holo-file-name" id="fileName" aria-live="polite"></div>
                </div>
            </div>

            <div class="holo-actions">
                <button type="submit" class="holo-btn holo-btn-primary">
                    <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                    Upload Document
                </button>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources" class="holo-btn holo-btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script src="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-resources-form.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
