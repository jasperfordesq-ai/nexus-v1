<?php
/**
 * Skeleton Layout - Create Listing
 * Form for creating new listings
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $basePath . '/login');
    exit;
}
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<!-- Breadcrumb -->
<div style="margin-bottom: 1rem;">
    <a href="<?= $basePath ?>/" style="color: var(--sk-link);">Home</a>
    <span style="color: #888;"> / </span>
    <a href="<?= $basePath ?>/listings" style="color: var(--sk-link);">Listings</a>
    <span style="color: #888;"> / </span>
    <span style="color: #888;">Create</span>
</div>

<h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Create New Listing</h1>
<p style="color: #888; margin-bottom: 2rem;">Share something with your community</p>

<?php if (!empty($errors)): ?>
    <div class="sk-alert sk-alert-error">
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div style="max-width: 800px;">
    <form method="POST" action="<?= $basePath ?>/listings/store" enctype="multipart/form-data" class="sk-card">
        <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::generate() ?>">

        <div class="sk-form-group">
            <label for="title" class="sk-form-label">Title *</label>
            <input type="text" id="title" name="title" class="sk-form-input" required
                   placeholder="Enter a descriptive title..."
                   value="<?= htmlspecialchars($old['title'] ?? '') ?>">
        </div>

        <div class="sk-form-group">
            <label for="category" class="sk-form-label">Category *</label>
            <select id="category" name="category_id" class="sk-form-select" required>
                <option value="">Select a category</option>
                <?php if (!empty($categories) && is_array($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"
                                <?= ($old['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="1">General</option>
                    <option value="2">Offers</option>
                    <option value="3">Requests</option>
                    <option value="4">Services</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="sk-form-group">
            <label for="description" class="sk-form-label">Description *</label>
            <textarea id="description" name="description" class="sk-form-textarea" required
                      placeholder="Provide a detailed description..."><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
        </div>

        <div class="sk-form-group">
            <label for="location" class="sk-form-label">Location</label>
            <input type="text" id="location" name="location" class="sk-form-input"
                   placeholder="Where is this located?"
                   value="<?= htmlspecialchars($old['location'] ?? '') ?>">
        </div>

        <div class="sk-form-group">
            <label for="image" class="sk-form-label">Image</label>
            <input type="file" id="image" name="image" class="sk-form-input" accept="image/*">
            <small style="color: #888; display: block; margin-top: 0.25rem;">
                Upload an image (max 5MB, JPG/PNG)
            </small>
        </div>

        <div class="sk-form-group">
            <label for="tags" class="sk-form-label">Tags</label>
            <input type="text" id="tags" name="tags" class="sk-form-input"
                   placeholder="Separate tags with commas (e.g., furniture, free, pickup)"
                   value="<?= htmlspecialchars($old['tags'] ?? '') ?>">
        </div>

        <div class="sk-form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="featured" value="1"
                       <?= !empty($old['featured']) ? 'checked' : '' ?>>
                <span>Feature this listing (if available)</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <button type="submit" class="sk-btn" style="flex: 1;">
                <i class="fas fa-check"></i> Create Listing
            </button>
            <a href="<?= $basePath ?>/listings" class="sk-btn sk-btn-secondary" style="flex: 0; text-align: center; padding: 0.5rem 1.5rem;">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
