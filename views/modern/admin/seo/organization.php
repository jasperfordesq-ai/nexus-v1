<?php
/**
 * Admin Organization Schema Settings - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Organization Schema';
$adminPageSubtitle = 'SEO';
$adminPageIcon = 'fa-building';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/seo" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Organization Schema
        </h1>
        <p class="admin-page-subtitle">Business information and social links for structured data</p>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <span>Organization settings saved successfully!</span>
</div>
<?php endif; ?>

<form action="<?= $basePath ?>/admin-legacy/seo/organization/save" method="POST">
    <?= Csrf::input() ?>

    <!-- Organization Details -->
    <div class="admin-glass-card" style="max-width: 900px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                <i class="fa-solid fa-building"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Organization Details</h3>
                <p class="admin-card-subtitle">Basic information about your organization</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="form-group">
                <label for="name">Organization Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($org['name']) ?>" disabled class="disabled">
                <small>Set in tenant configuration</small>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" placeholder="A brief description of your organization..."><?= htmlspecialchars($org['description']) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Contact Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($org['email']) ?>" placeholder="contact@example.com">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($org['phone']) ?>" placeholder="+1 (555) 123-4567">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?= htmlspecialchars($org['address']) ?>" placeholder="123 Main St, City, State 12345">
            </div>
        </div>
    </div>

    <!-- Social Links -->
    <div class="admin-glass-card" style="max-width: 900px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ec4899, #be185d);">
                <i class="fa-solid fa-share-nodes"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Social Media Links</h3>
                <p class="admin-card-subtitle">These appear in your Organization schema for search engines</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="social-input">
                <label>
                    <i class="fa-brands fa-facebook" style="color: #1877f2;"></i>
                    Facebook
                </label>
                <input type="url" name="social_facebook" value="<?= htmlspecialchars($org['social_facebook']) ?>" placeholder="https://facebook.com/yourpage">
            </div>

            <div class="social-input">
                <label>
                    <i class="fa-brands fa-twitter" style="color: #1da1f2;"></i>
                    Twitter / X
                </label>
                <input type="url" name="social_twitter" value="<?= htmlspecialchars($org['social_twitter']) ?>" placeholder="https://twitter.com/yourhandle">
            </div>

            <div class="social-input">
                <label>
                    <i class="fa-brands fa-instagram" style="color: #e4405f;"></i>
                    Instagram
                </label>
                <input type="url" name="social_instagram" value="<?= htmlspecialchars($org['social_instagram']) ?>" placeholder="https://instagram.com/yourprofile">
            </div>

            <div class="social-input">
                <label>
                    <i class="fa-brands fa-linkedin" style="color: #0a66c2;"></i>
                    LinkedIn
                </label>
                <input type="url" name="social_linkedin" value="<?= htmlspecialchars($org['social_linkedin']) ?>" placeholder="https://linkedin.com/company/yourcompany">
            </div>

            <div class="social-input">
                <label>
                    <i class="fa-brands fa-youtube" style="color: #ff0000;"></i>
                    YouTube
                </label>
                <input type="url" name="social_youtube" value="<?= htmlspecialchars($org['social_youtube']) ?>" placeholder="https://youtube.com/@yourchannel">
            </div>
        </div>
    </div>

    <!-- Sitemap Tools -->
    <div class="admin-glass-card" style="max-width: 900px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Sitemap Tools</h3>
                <p class="admin-card-subtitle">Notify search engines about your sitemap</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="sitemap-tool">
                <div class="sitemap-info">
                    <h4>Ping Search Engines</h4>
                    <p>Notify Google and Bing about your sitemap to speed up indexing.</p>
                </div>
                <button type="button" id="pingSitemapBtn" class="admin-btn admin-btn-success">
                    <i class="fa-solid fa-paper-plane"></i> Ping Sitemaps
                </button>
            </div>
            <div id="pingResults" class="ping-results"></div>
        </div>
    </div>

    <div class="form-actions" style="max-width: 900px;">
        <button type="submit" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-check"></i> Save Organization Settings
        </button>
    </div>
</form>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    max-width: 900px;
}

.admin-alert-success {
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
}

/* Form Group */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #fff;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 1rem;
    transition: all 0.2s;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.form-group input.disabled {
    background: rgba(255, 255, 255, 0.05);
    cursor: not-allowed;
}

.form-group small {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* Social Input */
.social-input {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.social-input:last-child {
    margin-bottom: 0;
}

.social-input label {
    width: 140px;
    font-weight: 600;
    color: #e2e8f0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.social-input label i {
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
}

.social-input input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.social-input input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.social-input input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

/* Sitemap Tool */
.sitemap-tool {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.sitemap-info h4 {
    margin: 0 0 0.25rem;
    color: #fff;
    font-size: 1rem;
}

.sitemap-info p {
    margin: 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

.admin-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #34d399, #10b981);
}

.ping-results {
    margin-top: 1rem;
    display: none;
}

.ping-results.show {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.ping-result {
    padding: 0.65rem 1rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
}

.ping-result.success {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.ping-result.error {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

/* Form Actions */
.form-actions {
    margin-top: 1.5rem;
    text-align: right;
}

/* Mobile */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .social-input {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .social-input label {
        width: 100%;
    }

    .social-input input {
        width: 100%;
    }

    .sitemap-tool {
        flex-direction: column;
        align-items: flex-start;
    }

    .sitemap-tool .admin-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
document.getElementById('pingSitemapBtn').addEventListener('click', function() {
    const btn = this;
    const results = document.getElementById('pingResults');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Pinging...';
    results.classList.remove('show');

    const formData = new FormData();
    formData.append('csrf_token', '<?= Csrf::generate() ?>');

    fetch('<?= $basePath ?>/admin-legacy/seo/ping-sitemaps', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Ping Sitemaps';

        let html = '';
        if (data.results.google) {
            html += '<div class="ping-result success"><i class="fa-brands fa-google"></i> Google: Success</div>';
        } else {
            html += '<div class="ping-result error"><i class="fa-brands fa-google"></i> Google: Failed</div>';
        }

        if (data.results.bing) {
            html += '<div class="ping-result success"><i class="fa-brands fa-microsoft"></i> Bing: Success</div>';
        } else {
            html += '<div class="ping-result error"><i class="fa-brands fa-microsoft"></i> Bing: Failed</div>';
        }

        results.innerHTML = html;
        results.classList.add('show');
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Ping Sitemaps';
        results.innerHTML = '<div class="ping-result error">Error pinging sitemaps. Please try again.</div>';
        results.classList.add('show');
    });
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
