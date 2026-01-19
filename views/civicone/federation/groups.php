<?php
// Federated Groups - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Groups - Partner Timebank Communities');
Nexus\Core\SEO::setDescription('Discover and join groups from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-groups-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Federation Hub
        </a>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fa-solid fa-people-group"></i>
                    Federated Groups
                </h1>
                <p class="page-subtitle">Discover and join groups from partner timebanks</p>
            </div>
            <div class="header-actions">
                <a href="<?= $basePath ?>/federation/groups/my">
                    <i class="fa-solid fa-user-group"></i>
                    My Federated Groups
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="group-search" placeholder="Search groups...">
            </div>
            <select id="timebank-filter" class="filter-select">
                <option value="">All Timebanks</option>
            </select>
        </div>

        <!-- Groups Grid -->
        <div id="groups-container">
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p style="color: var(--htb-text-muted);">Loading federated groups...</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="pagination" style="display: none;">
            <button id="prev-page" disabled>
                <i class="fa-solid fa-chevron-left"></i> Previous
            </button>
            <span class="page-info" id="page-info">Page 1 of 1</span>
            <button id="next-page" disabled>
                Next <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const basePath = '<?= $basePath ?>';
    let currentPage = 1;
    let totalPages = 1;
    let searchTimeout;

    const groupsContainer = document.getElementById('groups-container');
    const searchInput = document.getElementById('group-search');
    const timebankFilter = document.getElementById('timebank-filter');
    const pagination = document.getElementById('pagination');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');

    // Load groups
    async function loadGroups() {
        groupsContainer.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p style="color: var(--htb-text-muted);">Loading federated groups...</p>
            </div>
        `;

        const params = new URLSearchParams({
            page: currentPage,
            search: searchInput.value,
            tenant_id: timebankFilter.value
        });

        try {
            const response = await fetch(`${basePath}/federation/groups/api?${params}`);
            const data = await response.json();

            if (data.success) {
                renderGroups(data.groups);
                updatePagination(data.pagination);
                updateTimebankFilter(data.tenants || []);
            } else {
                showError(data.error || 'Failed to load groups');
            }
        } catch (error) {
            console.error('Error loading groups:', error);
            showError('Failed to load groups. Please try again.');
        }
    }

    // Render groups grid
    function renderGroups(groups) {
        if (!groups || groups.length === 0) {
            groupsContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <h3 class="empty-title">No Groups Found</h3>
                    <p class="empty-message">
                        There are no federated groups matching your criteria.<br>
                        Check back later for new groups from partner timebanks.
                    </p>
                </div>
            `;
            return;
        }

        const html = `
            <div class="groups-grid">
                ${groups.map(group => renderGroupCard(group)).join('')}
            </div>
        `;
        groupsContainer.innerHTML = html;
    }

    // Render single group card
    function renderGroupCard(group) {
        return `
            <div class="group-card">
                <div class="group-header">
                    <span class="group-tenant">
                        <i class="fa-solid fa-building"></i>
                        ${escapeHtml(group.tenant_name || 'Partner Timebank')}
                    </span>
                    <h3 class="group-name">
                        <a href="${basePath}/federation/groups/${group.id}?tenant=${group.tenant_id}">
                            ${escapeHtml(group.name)}
                        </a>
                    </h3>
                </div>
                <div class="group-body">
                    <p class="group-description">${escapeHtml(group.description || 'No description provided.')}</p>
                    <div class="group-stats">
                        <div class="group-stat">
                            <i class="fa-solid fa-users"></i>
                            ${group.member_count || 0} members
                        </div>
                    </div>
                    <div class="group-footer">
                        ${group.is_member ? `
                            <span class="member-badge">
                                <i class="fa-solid fa-check"></i>
                                Member
                            </span>
                        ` : '<span></span>'}
                        <a href="${basePath}/federation/groups/${group.id}?tenant=${group.tenant_id}" class="view-btn">
                            View Group
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    // Update pagination
    function updatePagination(paginationData) {
        if (!paginationData) return;

        totalPages = paginationData.total_pages || 1;
        currentPage = paginationData.current_page || 1;

        if (totalPages > 1) {
            pagination.style.display = 'flex';
            pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;
        } else {
            pagination.style.display = 'none';
        }
    }

    // Update timebank filter options
    function updateTimebankFilter(tenants) {
        const currentValue = timebankFilter.value;
        timebankFilter.innerHTML = '<option value="">All Timebanks</option>';

        tenants.forEach(tenant => {
            const option = document.createElement('option');
            option.value = tenant.id;
            option.textContent = tenant.name;
            if (tenant.id == currentValue) option.selected = true;
            timebankFilter.appendChild(option);
        });
    }

    // Show error
    function showError(message) {
        groupsContainer.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                </div>
                <h3 class="empty-title">Error</h3>
                <p class="empty-message">${escapeHtml(message)}</p>
            </div>
        `;
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Event listeners
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadGroups();
        }, 300);
    });

    timebankFilter.addEventListener('change', function() {
        currentPage = 1;
        loadGroups();
    });

    prevBtn.addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadGroups();
        }
    });

    nextBtn.addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadGroups();
        }
    });

    // Initial load
    loadGroups();
});

// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
