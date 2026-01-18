<?php
/**
 * Federation Directory - Discover Partner Timebanks
 * Gold Standard admin page for finding and connecting with other timebanks
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Discover Partner Timebanks';
$adminPageSubtitle = 'Federation Directory';
$adminPageIcon = 'fa-compass';

require __DIR__ . '/../partials/admin-header.php';

$timebanks = $timebanks ?? [];
$regions = $regions ?? [];
$categories = $categories ?? [];
$filters = $filters ?? ['search' => '', 'region' => '', 'category' => '', 'exclude_partnered' => false];
$myProfile = $myProfile ?? [];

// Calculate stats
$totalTimebanks = count($timebanks);
$availableTimebanks = count(array_filter($timebanks, fn($t) => empty($t['partnership_status'])));
$pendingRequests = count(array_filter($timebanks, fn($t) => ($t['partnership_status'] ?? '') === 'pending'));
$activePartners = count(array_filter($timebanks, fn($t) => ($t['partnership_status'] ?? '') === 'active'));
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-compass"></i>
            Discover Partner Timebanks
        </h1>
        <p class="admin-page-subtitle">Find timebanks to connect with and expand your community network</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/federation/directory/profile" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-user-edit"></i>
            My Listing
        </a>
        <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-handshake"></i>
            Partnerships
        </a>
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-globe"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $totalTimebanks ?></div>
            <div class="admin-stat-label">Timebanks in Directory</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-network-wired"></i>
            <span>Network</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-circle-plus"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $availableTimebanks ?></div>
            <div class="admin-stat-label">Available to Partner</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-check"></i>
            <span>Ready</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $pendingRequests ?></div>
            <div class="admin-stat-label">Pending Requests</div>
        </div>
        <div class="admin-stat-trend <?= $pendingRequests > 0 ? 'admin-stat-trend-warning' : '' ?>">
            <i class="fa-solid fa-hourglass-half"></i>
            <span>Waiting</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $activePartners ?></div>
            <div class="admin-stat-label">Active Partners</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-link"></i>
            <span>Connected</span>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="admin-glass-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-search"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Search Directory</h3>
            <p class="admin-card-subtitle">Find partner timebanks by name, region, or category</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form method="GET" action="<?= $basePath ?>/admin/federation/directory" class="directory-filters">
            <div class="filter-row">
                <div class="filter-search">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" name="q" class="admin-input" placeholder="Search by name or description..."
                        value="<?= htmlspecialchars($filters['search']) ?>">
                </div>

                <div class="filter-select">
                    <select name="region" class="admin-select">
                        <option value="">All Regions</option>
                        <?php foreach ($regions as $region): ?>
                        <option value="<?= htmlspecialchars($region) ?>" <?= ($filters['region'] ?? '') === $region ? 'selected' : '' ?>>
                            <?= htmlspecialchars($region) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-select">
                    <select name="category" class="admin-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($category)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="filter-checkbox" title="Hide timebanks you already have a partnership with">
                    <input type="checkbox" name="available_only" <?= ($filters['exclude_partnered'] ?? false) ? 'checked' : '' ?>>
                    <span>Available only</span>
                </label>

                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Search
                </button>

                <?php if (!empty($filters['search']) || !empty($filters['region']) || !empty($filters['category']) || ($filters['exclude_partnered'] ?? false)): ?>
                <a href="<?= $basePath ?>/admin/federation/directory" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Directory Grid -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-emerald">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Federation Directory</h3>
            <p class="admin-card-subtitle"><?= $totalTimebanks ?> timebank<?= $totalTimebanks !== 1 ? 's' : '' ?> found</p>
        </div>
    </div>
    <div class="admin-card-body">
        <?php if (empty($timebanks)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-compass"></i>
            </div>
            <h4 class="admin-empty-title">No Timebanks Found</h4>
            <p>
                <?php if (!empty($filters['search']) || !empty($filters['region']) || !empty($filters['category'])): ?>
                Try adjusting your filters or search terms.
                <?php else: ?>
                No other timebanks are currently available in the directory.
                <?php endif; ?>
            </p>
            <?php if (!empty($filters['search']) || !empty($filters['region']) || !empty($filters['category'])): ?>
            <a href="<?= $basePath ?>/admin/federation/directory" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-times"></i> Clear Filters
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="directory-grid">
            <?php foreach ($timebanks as $tb): ?>
            <div class="directory-card <?= ($tb['partnership_status'] ?? '') === 'active' ? 'directory-card-partner' : '' ?>">
                <div class="directory-card-header">
                    <?php if (!empty($tb['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($tb['logo_url']) ?>" alt="<?= htmlspecialchars($tb['name']) ?>" class="directory-logo">
                    <?php else: ?>
                    <div class="directory-logo-placeholder">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <?php endif; ?>

                    <div class="directory-card-title">
                        <h3><?= htmlspecialchars($tb['name']) ?></h3>
                        <?php if (!empty($tb['region'])): ?>
                        <span class="directory-region">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= htmlspecialchars($tb['region']) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($tb['partnership_status'])): ?>
                    <span class="directory-status directory-status-<?= $tb['partnership_status'] ?>">
                        <?php
                        $statusIcon = match($tb['partnership_status']) {
                            'active' => 'fa-check-circle',
                            'pending' => 'fa-clock',
                            'suspended' => 'fa-ban',
                            default => 'fa-circle'
                        };
                        ?>
                        <i class="fa-solid <?= $statusIcon ?>"></i>
                        <?= ucfirst($tb['partnership_status']) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($tb['description'])): ?>
                <p class="directory-description"><?= htmlspecialchars(substr($tb['description'], 0, 150)) ?><?= strlen($tb['description']) > 150 ? '...' : '' ?></p>
                <?php else: ?>
                <p class="directory-description directory-description-empty">No description available</p>
                <?php endif; ?>

                <div class="directory-meta">
                    <?php if ($tb['member_count'] !== null): ?>
                    <span class="directory-meta-item">
                        <i class="fa-solid fa-users"></i>
                        <?= number_format($tb['member_count']) ?> members
                    </span>
                    <?php endif; ?>

                    <?php if (!empty($tb['categories'])): ?>
                    <div class="directory-tags">
                        <?php
                        $cats = is_array($tb['categories']) ? $tb['categories'] : explode(',', $tb['categories']);
                        $cats = array_slice(array_map('trim', $cats), 0, 3);
                        foreach ($cats as $cat):
                            if (empty($cat)) continue;
                        ?>
                        <span class="directory-tag"><?= htmlspecialchars($cat) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="directory-features">
                    <span class="feature-badge <?= ($tb['profiles_enabled'] ?? false) ? 'enabled' : 'disabled' ?>" title="Member Profiles">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <span class="feature-badge <?= ($tb['listings_enabled'] ?? false) ? 'enabled' : 'disabled' ?>" title="Listings">
                        <i class="fa-solid fa-list"></i>
                    </span>
                    <span class="feature-badge <?= ($tb['messaging_enabled'] ?? false) ? 'enabled' : 'disabled' ?>" title="Messaging">
                        <i class="fa-solid fa-envelope"></i>
                    </span>
                    <span class="feature-badge <?= ($tb['transactions_enabled'] ?? false) ? 'enabled' : 'disabled' ?>" title="Transactions">
                        <i class="fa-solid fa-exchange-alt"></i>
                    </span>
                    <span class="feature-badge <?= ($tb['events_enabled'] ?? false) ? 'enabled' : 'disabled' ?>" title="Events">
                        <i class="fa-solid fa-calendar"></i>
                    </span>
                    <span class="feature-badge <?= ($tb['groups_enabled'] ?? false) ? 'enabled' : 'disabled' ?>" title="Groups">
                        <i class="fa-solid fa-users-rectangle"></i>
                    </span>
                </div>

                <div class="directory-card-actions">
                    <a href="<?= $basePath ?>/admin/federation/directory/<?= $tb['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                        <i class="fa-solid fa-eye"></i>
                        View
                    </a>

                    <?php if (empty($tb['partnership_status'])): ?>
                    <button onclick="requestPartnership(<?= $tb['id'] ?>, '<?= htmlspecialchars($tb['name'], ENT_QUOTES) ?>')"
                        class="admin-btn admin-btn-primary admin-btn-sm">
                        <i class="fa-solid fa-handshake"></i>
                        Request Partnership
                    </button>
                    <?php elseif ($tb['partnership_status'] === 'pending'): ?>
                    <span class="directory-badge directory-badge-warning">
                        <i class="fa-solid fa-clock"></i>
                        Pending
                    </span>
                    <?php elseif ($tb['partnership_status'] === 'active'): ?>
                    <span class="directory-badge directory-badge-success">
                        <i class="fa-solid fa-check"></i>
                        Partner
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- My Listing Status -->
<?php if (empty($myProfile['federation_discoverable'])): ?>
<div class="admin-alert admin-alert-warning" style="margin-top: 1.5rem;">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-eye-slash"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Your Timebank is Hidden</div>
        <div class="admin-alert-text">Your timebank is not visible in the federation directory. Other timebanks won't be able to find and request partnerships with you.</div>
    </div>
    <a href="<?= $basePath ?>/admin/federation/directory/profile" class="admin-btn admin-btn-warning">
        <i class="fa-solid fa-eye"></i>
        Enable Listing
    </a>
</div>
<?php endif; ?>

<!-- Request Partnership Modal -->
<div id="partnershipModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-backdrop" onclick="closeModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <div class="admin-modal-header-icon">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <div>
                <h3>Request Partnership</h3>
                <p>Connect with <span id="modalTimebankName"></span></p>
            </div>
            <button onclick="closeModal()" class="admin-modal-close">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <label class="admin-label">Federation Level</label>
                <select id="federationLevel" class="admin-input">
                    <option value="1">Level 1 - Discovery (Directory visibility only)</option>
                    <option value="2" selected>Level 2 - Social (Profiles + Messaging)</option>
                    <option value="3">Level 3 - Economic (+ Transactions)</option>
                    <option value="4">Level 4 - Integrated (Full access)</option>
                </select>
                <small class="admin-help-text">Choose the level of integration you'd like with this timebank.</small>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Message (optional)</label>
                <textarea id="partnershipMessage" class="admin-input" rows="4"
                    placeholder="Introduce your timebank and explain why you'd like to partner..."></textarea>
                <small class="admin-help-text">A friendly introduction can help your request get approved faster.</small>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button onclick="closeModal()" class="admin-btn admin-btn-secondary">Cancel</button>
            <button onclick="submitPartnershipRequest()" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-paper-plane"></i>
                Send Request
            </button>
        </div>
    </div>
</div>

<style>
/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .admin-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .admin-stats-grid {
        grid-template-columns: 1fr;
    }
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-purple { --stat-color: #8b5cf6; }
.admin-stat-orange { --stat-color: #f59e0b; }
.admin-stat-cyan { --stat-color: #06b6d4; }

.admin-stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.admin-stat-content {
    flex: 1;
}

.admin-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.admin-stat-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.admin-stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
}

.admin-stat-trend-up {
    color: #22c55e;
    background: rgba(34, 197, 94, 0.1);
}

.admin-stat-trend-warning {
    color: #f59e0b;
    background: rgba(245, 158, 11, 0.1);
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    overflow: hidden;
}

.admin-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-card-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-card-header-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.125rem 0 0 0;
}

.admin-card-body {
    padding: 1.5rem;
}

/* Filter Styles */
.directory-filters {
    width: 100%;
}

.filter-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.filter-search {
    flex: 2;
    min-width: 200px;
    position: relative;
}

.filter-search i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.4);
}

.filter-search input {
    padding-left: 42px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: #fff;
}

.filter-search input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.filter-select {
    flex: 1;
    min-width: 150px;
}

.admin-select,
.filter-select select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: #fff;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23a78bfa' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.admin-select:focus,
.filter-select select:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.admin-select option {
    background: #1e293b;
    color: #fff;
}

.filter-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    white-space: nowrap;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.filter-checkbox input {
    width: 18px;
    height: 18px;
    accent-color: #8b5cf6;
}

/* Directory Grid */
.directory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}

.directory-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 14px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: all 0.3s ease;
}

.directory-card:hover {
    border-color: rgba(139, 92, 246, 0.3);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
}

.directory-card-partner {
    border-color: rgba(34, 197, 94, 0.3);
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.05), transparent);
}

.directory-card-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.directory-logo {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    object-fit: cover;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.directory-logo-placeholder {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    font-size: 1.5rem;
}

.directory-card-title {
    flex: 1;
}

.directory-card-title h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
}

.directory-region {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.directory-region i {
    color: #8b5cf6;
}

.directory-status {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.directory-status-active {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.directory-status-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.directory-status-suspended {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.directory-description {
    margin: 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    line-height: 1.5;
}

.directory-description-empty {
    color: rgba(255, 255, 255, 0.4);
    font-style: italic;
}

.directory-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.directory-meta-item {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.directory-meta-item i {
    color: #8b5cf6;
}

.directory-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.directory-tag {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.directory-features {
    display: flex;
    gap: 0.5rem;
    padding-top: 0.5rem;
}

.feature-badge {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.feature-badge.enabled {
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
}

.feature-badge.disabled {
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.2);
}

.directory-card-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
    align-items: center;
}

.directory-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
}

.directory-badge-success {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.directory-badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

/* Alert Banner */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
}

.admin-alert-warning {
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-alert-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    flex-shrink: 0;
}

.admin-alert-content {
    flex: 1;
}

.admin-alert-title {
    font-weight: 600;
    color: #f59e0b;
}

.admin-alert-text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.25rem;
}

/* Modal Styles */
.admin-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.admin-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
}

.admin-modal-content {
    position: relative;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.admin-modal-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-modal-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: white;
}

.admin-modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #fff;
}

.admin-modal-header p {
    margin: 0.25rem 0 0 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.admin-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1;
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

.admin-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 0.5rem;
}

.admin-help-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-sm {
    padding: 0.5rem 0.875rem;
    font-size: 0.8rem;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.2);
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #000;
    font-weight: 600;
}

.admin-btn-warning:hover {
    box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
}

/* Page Header */
.admin-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.admin-page-title i {
    color: #8b5cf6;
}

.admin-page-subtitle {
    color: rgba(255, 255, 255, 0.6);
    margin: 0.25rem 0 0 0;
    font-size: 0.9rem;
}

.admin-page-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Responsive */
@media (max-width: 768px) {
    .directory-grid {
        grid-template-columns: 1fr;
    }

    .filter-row {
        flex-direction: column;
    }

    .filter-search,
    .filter-select {
        width: 100%;
    }

    .admin-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-page-header-actions {
        width: 100%;
        flex-wrap: wrap;
    }
}
</style>

<script>
const basePath = '<?= $basePath ?>';
const csrfToken = '<?= Csrf::token() ?>';
let selectedTimebankId = null;

function requestPartnership(timebankId, timebankName) {
    selectedTimebankId = timebankId;
    document.getElementById('modalTimebankName').textContent = timebankName;
    document.getElementById('federationLevel').value = '2';
    document.getElementById('partnershipMessage').value = '';
    document.getElementById('partnershipModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('partnershipModal').style.display = 'none';
    selectedTimebankId = null;
}

function submitPartnershipRequest() {
    if (!selectedTimebankId) return;

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

    const federationLevel = document.getElementById('federationLevel').value;
    const message = document.getElementById('partnershipMessage').value;

    fetch(basePath + '/admin/federation/directory/request-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            target_tenant_id: selectedTimebankId,
            federation_level: federationLevel,
            message: message
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (typeof AdminToast !== 'undefined') {
                AdminToast.show('Partnership request sent successfully!', 'success');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            if (typeof AdminToast !== 'undefined') {
                AdminToast.show(data.error || 'Failed to send request', 'error');
            } else {
                alert(data.error || 'Failed to send request');
            }
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        if (typeof AdminToast !== 'undefined') {
            AdminToast.show('Network error. Please try again.', 'error');
        } else {
            alert('Network error. Please try again.');
        }
        btn.disabled = false;
        btn.innerHTML = originalText;
    });

    closeModal();
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
