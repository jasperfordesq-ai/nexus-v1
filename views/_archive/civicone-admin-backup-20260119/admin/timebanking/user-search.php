<?php
/**
 * Timebanking User Search - Gold Standard Admin UI
 * Holographic Glassmorphism Design
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'User Activity Report';
$adminPageSubtitle = 'Search and investigate user timebanking activity';
$adminPageIcon = 'fa-solid fa-user-magnifying-glass';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="user-search-container">
    <!-- Search Card -->
    <div class="glass-card search-card">
        <div class="card-header">
            <div class="card-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <h2>Find User</h2>
        </div>

        <form action="<?= $basePath ?>/admin/timebanking/user-report" method="GET" class="search-form" id="userSearchForm">
            <div class="search-input-wrapper">
                <i class="fa-solid fa-search search-icon"></i>
                <input type="text"
                       name="q"
                       id="userSearchInput"
                       class="search-input"
                       placeholder="Search by name, email, or user ID..."
                       autocomplete="off"
                       autofocus>
                <div class="search-hint">
                    <kbd>Enter</kbd> to search
                </div>
            </div>

            <!-- Search Results Container -->
            <div id="searchResults" class="search-results" style="display: none;"></div>
        </form>

        <div class="search-tips">
            <h4><i class="fa-solid fa-lightbulb"></i> Search Tips</h4>
            <ul>
                <li>Enter a user's full name (e.g., "John Smith")</li>
                <li>Search by email address</li>
                <li>Enter a numeric user ID directly</li>
                <li>Results appear as you type</li>
            </ul>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="glass-card">
        <div class="card-header">
            <div class="card-icon quick">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <h2>Quick Access</h2>
        </div>

        <div class="quick-links-grid">
            <a href="<?= $basePath ?>/admin/timebanking" class="quick-link">
                <div class="quick-link-icon dashboard">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="quick-link-content">
                    <strong>Dashboard</strong>
                    <span>Overview & statistics</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="<?= $basePath ?>/admin/timebanking/alerts" class="quick-link">
                <div class="quick-link-icon alerts">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div class="quick-link-content">
                    <strong>Abuse Alerts</strong>
                    <span>Review flagged activity</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="<?= $basePath ?>/admin/timebanking/org-wallets" class="quick-link">
                <div class="quick-link-icon orgs">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div class="quick-link-content">
                    <strong>Organization Wallets</strong>
                    <span>Manage org balances</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="<?= $basePath ?>/admin/users" class="quick-link">
                <div class="quick-link-icon users">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="quick-link-content">
                    <strong>User Management</strong>
                    <span>All platform users</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <!-- Recent Reports -->
    <div class="glass-card">
        <div class="card-header">
            <div class="card-icon history">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <h2>Recent Reports</h2>
        </div>

        <div id="recentReports" class="recent-reports">
            <div class="empty-state">
                <i class="fa-solid fa-history"></i>
                <p>Your recently viewed user reports will appear here</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Container */
.user-search-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 24px 60px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Glass Card */
.glass-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(10px);
}

.search-card {
    padding: 32px;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.card-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a5b4fc;
    font-size: 1.2rem;
}

.card-icon.quick {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
    border-color: rgba(251, 191, 36, 0.3);
    color: #fcd34d;
}

.card-icon.history {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.2) 0%, rgba(6, 182, 212, 0.2) 100%);
    border-color: rgba(14, 165, 233, 0.3);
    color: #7dd3fc;
}

.card-header h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 600;
    color: #f1f5f9;
}

/* Search Form */
.search-form {
    margin-bottom: 24px;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 20px;
    color: rgba(255, 255, 255, 0.4);
    font-size: 1.1rem;
    pointer-events: none;
}

.search-input {
    width: 100%;
    padding: 18px 20px 18px 52px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    color: #f1f5f9;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.35);
}

.search-hint {
    position: absolute;
    right: 16px;
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.8rem;
}

.search-hint kbd {
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.75rem;
}

/* Search Results */
.search-results {
    margin-top: 16px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    max-height: 400px;
    overflow-y: auto;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    cursor: pointer;
    transition: background 0.2s ease;
    text-decoration: none;
}

.search-result-item:hover {
    background: rgba(99, 102, 241, 0.1);
}

.search-result-item:last-child {
    border-bottom: none;
}

.result-avatar {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.result-info {
    flex: 1;
    min-width: 0;
}

.result-name {
    color: #f1f5f9;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.result-email {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.result-balance {
    text-align: right;
    flex-shrink: 0;
}

.result-balance-value {
    color: #86efac;
    font-weight: 700;
    font-size: 1rem;
}

.result-balance-label {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.75rem;
}

.search-loading {
    padding: 24px;
    text-align: center;
    color: rgba(255, 255, 255, 0.5);
}

.search-no-results {
    padding: 24px;
    text-align: center;
    color: rgba(255, 255, 255, 0.5);
}

.search-no-results i {
    font-size: 2rem;
    margin-bottom: 12px;
    opacity: 0.3;
}

/* Search Tips */
.search-tips {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    padding: 16px 20px;
}

.search-tips h4 {
    margin: 0 0 12px;
    font-size: 0.9rem;
    color: #a5b4fc;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-tips ul {
    margin: 0;
    padding-left: 20px;
    color: rgba(165, 180, 252, 0.8);
    font-size: 0.85rem;
    line-height: 1.8;
}

/* Quick Links */
.quick-links-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.quick-link:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.15);
    transform: translateX(4px);
}

.quick-link-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.quick-link-icon.dashboard {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(22, 163, 74, 0.2) 100%);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.quick-link-icon.alerts {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.quick-link-icon.orgs {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(124, 58, 237, 0.2) 100%);
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: #c4b5fd;
}

.quick-link-icon.users {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(37, 99, 235, 0.2) 100%);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #93c5fd;
}

.quick-link-content {
    flex: 1;
}

.quick-link-content strong {
    display: block;
    color: #f1f5f9;
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.quick-link-content span {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

.quick-link > i {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.85rem;
}

/* Recent Reports */
.recent-reports {
    min-height: 100px;
}

.empty-state {
    text-align: center;
    padding: 32px 24px;
    color: rgba(255, 255, 255, 0.4);
}

.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 12px;
    opacity: 0.3;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .user-search-container {
        padding: 0 16px 40px;
    }

    .quick-links-grid {
        grid-template-columns: 1fr;
    }

    .search-hint {
        display: none;
    }
}

/* Scrollbar */
.search-results::-webkit-scrollbar {
    width: 6px;
}

.search-results::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02);
}

.search-results::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 3px;
}

.search-results::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}
</style>

<script>
const searchInput = document.getElementById('userSearchInput');
const searchResults = document.getElementById('searchResults');
let searchTimeout = null;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();

    if (query.length < 2) {
        searchResults.style.display = 'none';
        return;
    }

    searchTimeout = setTimeout(() => {
        performSearch(query);
    }, 300);
});

async function performSearch(query) {
    searchResults.style.display = 'block';
    searchResults.innerHTML = '<div class="search-loading"><i class="fa-solid fa-spinner fa-spin"></i> Searching...</div>';

    try {
        const response = await fetch('<?= $basePath ?>/api/admin/users/search?q=' + encodeURIComponent(query));
        const data = await response.json();

        if (!data.success || !data.users || data.users.length === 0) {
            searchResults.innerHTML = `
                <div class="search-no-results">
                    <i class="fa-solid fa-user-slash"></i>
                    <p>No users found matching "${escapeHtml(query)}"</p>
                </div>
            `;
            return;
        }

        let html = '';
        data.users.forEach(user => {
            const initials = getInitials(user.first_name, user.last_name);
            const balance = parseFloat(user.balance || 0).toFixed(1);
            html += `
                <a href="<?= $basePath ?>/admin/timebanking/user-report/${user.id}" class="search-result-item">
                    <div class="result-avatar">${initials}</div>
                    <div class="result-info">
                        <div class="result-name">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</div>
                        <div class="result-email">${escapeHtml(user.email)}</div>
                    </div>
                    <div class="result-balance">
                        <div class="result-balance-value">${balance}h</div>
                        <div class="result-balance-label">balance</div>
                    </div>
                </a>
            `;
        });

        searchResults.innerHTML = html;
    } catch (error) {
        searchResults.innerHTML = `
            <div class="search-no-results">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <p>Error searching users. Please try again.</p>
            </div>
        `;
    }
}

function getInitials(firstName, lastName) {
    return ((firstName || '')[0] || '') + ((lastName || '')[0] || '');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Handle enter key
searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const firstResult = searchResults.querySelector('.search-result-item');
        if (firstResult) {
            window.location.href = firstResult.href;
        }
    }
});

// Close results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-form')) {
        searchResults.style.display = 'none';
    }
});

// Show results when input is focused and has value
searchInput.addEventListener('focus', function() {
    if (this.value.trim().length >= 2 && searchResults.innerHTML) {
        searchResults.style.display = 'block';
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
