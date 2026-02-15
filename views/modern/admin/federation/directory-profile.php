<?php
/**
 * Federation Directory - View Timebank Profile
 * View details of another timebank before requesting partnership
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = $timebank['name'];
$adminPageSubtitle = 'Federation Directory Profile';
$adminPageIcon = 'fa-building';

require __DIR__ . '/../partials/admin-header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-building"></i>
            <?= htmlspecialchars($timebank['name']) ?>
        </h1>
        <p class="admin-page-subtitle">Federation Directory Profile</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/federation/directory" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Directory
        </a>
    </div>
</div>

<div class="profile-layout">
    <!-- Main Profile -->
    <div class="profile-main">
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="profile-header">
                    <?php if (!empty($timebank['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($timebank['logo_url']) ?>" alt="<?= htmlspecialchars($timebank['name']) ?>" class="profile-logo">
                    <?php else: ?>
                    <div class="profile-logo-placeholder">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <?php endif; ?>

                    <div class="profile-header-info">
                        <h2><?= htmlspecialchars($timebank['name']) ?></h2>
                        <?php if (!empty($timebank['region'])): ?>
                        <p class="profile-region">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= htmlspecialchars($timebank['region']) ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($timebank['domain'])): ?>
                        <p class="profile-domain">
                            <i class="fa-solid fa-globe"></i>
                            <?= htmlspecialchars($timebank['domain']) ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="profile-header-actions">
                        <?php if (!$partnership): ?>
                        <button onclick="requestPartnership()" class="admin-btn admin-btn-primary">
                            <i class="fa-solid fa-handshake"></i>
                            Request Partnership
                        </button>
                        <?php elseif ($partnership['status'] === 'pending'): ?>
                        <span class="admin-badge admin-badge-warning" style="font-size: 1rem; padding: 0.5rem 1rem;">
                            <i class="fa-solid fa-clock"></i>
                            Request Pending
                        </span>
                        <?php elseif ($partnership['status'] === 'active'): ?>
                        <span class="admin-badge admin-badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                            <i class="fa-solid fa-check"></i>
                            Active Partner
                        </span>
                        <?php elseif ($partnership['status'] === 'suspended'): ?>
                        <span class="admin-badge admin-badge-danger" style="font-size: 1rem; padding: 0.5rem 1rem;">
                            <i class="fa-solid fa-pause"></i>
                            Partnership Suspended
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($timebank['description'])): ?>
                <div class="profile-section">
                    <h3>About</h3>
                    <p><?= nl2br(htmlspecialchars($timebank['description'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($timebank['categories'])): ?>
                <div class="profile-section">
                    <h3>Categories</h3>
                    <div class="profile-tags">
                        <?php
                        $cats = is_array($timebank['categories'])
                            ? $timebank['categories']
                            : array_map('trim', explode(',', $timebank['categories']));
                        foreach ($cats as $cat):
                        ?>
                        <span class="profile-tag"><?= htmlspecialchars($cat) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="profile-sidebar">
        <!-- Stats -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fa-solid fa-chart-simple"></i>
                    Quick Facts
                </h3>
            </div>
            <div class="admin-card-body">
                <div class="stats-list">
                    <?php if ($timebank['member_count'] !== null): ?>
                    <div class="stat-item">
                        <i class="fa-solid fa-users"></i>
                        <div>
                            <span class="stat-value"><?= number_format($timebank['member_count']) ?></span>
                            <span class="stat-label">Members</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fa-solid fa-puzzle-piece"></i>
                    Federation Features
                </h3>
            </div>
            <div class="admin-card-body">
                <p class="features-intro">Features this timebank has enabled for federation:</p>
                <div class="features-list">
                    <div class="feature-item <?= $features['profiles'] ? 'enabled' : 'disabled' ?>">
                        <i class="fa-solid fa-user"></i>
                        <span>Member Profiles</span>
                        <i class="fa-solid <?= $features['profiles'] ? 'fa-check' : 'fa-times' ?> feature-status"></i>
                    </div>
                    <div class="feature-item <?= $features['listings'] ? 'enabled' : 'disabled' ?>">
                        <i class="fa-solid fa-list"></i>
                        <span>Listings</span>
                        <i class="fa-solid <?= $features['listings'] ? 'fa-check' : 'fa-times' ?> feature-status"></i>
                    </div>
                    <div class="feature-item <?= $features['messaging'] ? 'enabled' : 'disabled' ?>">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Messaging</span>
                        <i class="fa-solid <?= $features['messaging'] ? 'fa-check' : 'fa-times' ?> feature-status"></i>
                    </div>
                    <div class="feature-item <?= $features['transactions'] ? 'enabled' : 'disabled' ?>">
                        <i class="fa-solid fa-exchange-alt"></i>
                        <span>Transactions</span>
                        <i class="fa-solid <?= $features['transactions'] ? 'fa-check' : 'fa-times' ?> feature-status"></i>
                    </div>
                    <div class="feature-item <?= $features['events'] ? 'enabled' : 'disabled' ?>">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Events</span>
                        <i class="fa-solid <?= $features['events'] ? 'fa-check' : 'fa-times' ?> feature-status"></i>
                    </div>
                    <div class="feature-item <?= $features['groups'] ? 'enabled' : 'disabled' ?>">
                        <i class="fa-solid fa-users-rectangle"></i>
                        <span>Groups</span>
                        <i class="fa-solid <?= $features['groups'] ? 'fa-check' : 'fa-times' ?> feature-status"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact -->
        <?php if (!empty($timebank['contact_name']) || !empty($timebank['contact_email'])): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fa-solid fa-address-card"></i>
                    Federation Contact
                </h3>
            </div>
            <div class="admin-card-body">
                <?php if (!empty($timebank['contact_name'])): ?>
                <p style="margin: 0 0 0.5rem 0;">
                    <strong><?= htmlspecialchars($timebank['contact_name']) ?></strong>
                </p>
                <?php endif; ?>
                <?php if (!empty($timebank['contact_email'])): ?>
                <p style="margin: 0; color: var(--admin-text-muted);">
                    <i class="fa-solid fa-envelope"></i>
                    <?= htmlspecialchars($timebank['contact_email']) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Request Partnership Modal -->
<?php if (!$partnership): ?>
<div id="partnershipModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3><i class="fa-solid fa-handshake"></i> Request Partnership</h3>
            <button onclick="closeModal()" class="admin-modal-close">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p>Request a partnership with <strong><?= htmlspecialchars($timebank['name']) ?></strong>.</p>

            <div class="admin-form-group">
                <label class="admin-label">Federation Level</label>
                <select id="federationLevel" class="admin-input">
                    <option value="1">Level 1 - Discovery (See in directory only)</option>
                    <option value="2" selected>Level 2 - Social (Profiles + Messaging)</option>
                    <option value="3">Level 3 - Economic (+ Transactions)</option>
                    <option value="4">Level 4 - Integrated (Full access)</option>
                </select>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Message (optional)</label>
                <textarea id="partnershipMessage" class="admin-input" rows="3"
                    placeholder="Introduce your timebank..."></textarea>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button onclick="closeModal()" class="admin-btn admin-btn-secondary">Cancel</button>
            <button onclick="submitRequest()" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-paper-plane"></i>
                Send Request
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Modern Dark Glass UI - Profile View */
.profile-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

.profile-header {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.profile-logo {
    width: 100px;
    height: 100px;
    border-radius: 16px;
    object-fit: cover;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.profile-logo-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    font-size: 2.5rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

.profile-header-info {
    flex: 1;
}

.profile-header-info h2 {
    margin: 0 0 0.5rem 0;
    color: #fff;
}

.profile-region,
.profile-domain {
    margin: 0.25rem 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-region i,
.profile-domain i {
    color: #8b5cf6;
}

.profile-section {
    margin-bottom: 1.5rem;
}

.profile-section:last-child {
    margin-bottom: 0;
}

.profile-section h3 {
    font-size: 0.85rem;
    margin: 0 0 0.75rem 0;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-section p {
    margin: 0;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.8);
}

.profile-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.profile-tag {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
}

.stats-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 10px;
}

.stat-item > i {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
    color: #8b5cf6;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
}

.stat-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

.features-intro {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 1rem 0;
}

.features-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.875rem;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.feature-item.enabled {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.2);
    color: rgba(255, 255, 255, 0.9);
}

.feature-item.disabled {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    color: rgba(255, 255, 255, 0.4);
}

.feature-item i:first-child {
    width: 20px;
    text-align: center;
}

.feature-item.enabled i:first-child {
    color: #22c55e;
}

.feature-item span {
    flex: 1;
}

.feature-item .feature-status {
    font-size: 0.75rem;
}

.feature-item.enabled .feature-status {
    color: #22c55e;
}

.feature-item.disabled .feature-status {
    color: rgba(255, 255, 255, 0.3);
}

/* Modal - Dark Glass */
.admin-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.admin-modal-content {
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.admin-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #fff;
}

.admin-modal-header h3 i {
    color: #8b5cf6;
}

.admin-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.5);
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.admin-modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.admin-modal-body {
    padding: 1.5rem;
}

.admin-modal-body p {
    color: rgba(255, 255, 255, 0.8);
}

.admin-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-form-group {
    margin-bottom: 1.25rem;
}

.admin-form-group:last-child {
    margin-bottom: 0;
}

@media (max-width: 900px) {
    .profile-layout {
        grid-template-columns: 1fr;
    }

    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .profile-header-actions {
        margin-top: 1rem;
    }
}
</style>

<?php if (!$partnership): ?>
<script>
const basePath = '<?= $basePath ?>';
const csrfToken = '<?= Csrf::token() ?>';
const targetTenantId = <?= $timebank['id'] ?>;

function requestPartnership() {
    document.getElementById('partnershipModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('partnershipModal').style.display = 'none';
}

function submitRequest() {
    const federationLevel = document.getElementById('federationLevel').value;
    const message = document.getElementById('partnershipMessage').value;

    fetch(basePath + '/admin-legacy/federation/directory/request-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            target_tenant_id: targetTenantId,
            federation_level: federationLevel,
            message: message
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Partnership request sent!');
            location.reload();
        } else {
            alert(data.error || 'Failed to send request');
        }
    });

    closeModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
document.getElementById('partnershipModal')?.addEventListener('click', e => { if (e.target.id === 'partnershipModal') closeModal(); });
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
