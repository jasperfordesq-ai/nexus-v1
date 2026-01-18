<?php
// Federated Groups - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Groups - Partner Timebank Communities');
Nexus\Core\SEO::setDescription('Discover and join groups from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-groups-wrapper">

        <style>
            /* Offline Banner */
            .offline-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10001;
                padding: 12px 20px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                font-size: 0.9rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transform: translateY(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            /* Content Reveal Animation */
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Back Link */
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
                animation: fadeInUp 0.4s ease-out;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            #fed-groups-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px 0;
            }

            .page-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                flex-wrap: wrap;
                gap: 20px;
                animation: fadeInUp 0.4s ease-out 0.05s both;
            }

            .page-title {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main);
                margin: 0;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .page-title i {
                color: #8b5cf6;
            }

            .page-subtitle {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
                margin-top: 6px;
            }

            .header-actions a {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 20px;
                background: rgba(139, 92, 246, 0.1);
                color: #8b5cf6;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.3s ease;
            }

            .header-actions a:hover {
                background: rgba(139, 92, 246, 0.2);
            }

            /* Filters */
            .filters-bar {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12);
                padding: 20px 24px;
                margin-bottom: 24px;
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
                align-items: center;
                animation: fadeInUp 0.4s ease-out 0.1s both;
            }

            [data-theme="dark"] .filters-bar {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .search-box {
                flex: 1;
                min-width: 250px;
                position: relative;
            }

            .search-box input {
                width: 100%;
                padding: 12px 16px 12px 44px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                border-radius: 14px;
                font-size: 0.95rem;
                background: rgba(255, 255, 255, 0.8);
                color: var(--htb-text-main);
                transition: all 0.3s ease;
            }

            [data-theme="dark"] .search-box input {
                background: rgba(30, 41, 59, 0.6);
                border-color: rgba(139, 92, 246, 0.3);
            }

            .search-box input:focus {
                outline: none;
                border-color: #8b5cf6;
                box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
            }

            .search-box i {
                position: absolute;
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: #8b5cf6;
            }

            .filter-select {
                padding: 12px 16px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                border-radius: 14px;
                font-size: 0.95rem;
                background: rgba(255, 255, 255, 0.8);
                color: var(--htb-text-main);
                min-width: 180px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            [data-theme="dark"] .filter-select {
                background: rgba(30, 41, 59, 0.6);
                border-color: rgba(139, 92, 246, 0.3);
            }

            .filter-select:focus {
                outline: none;
                border-color: #8b5cf6;
            }

            /* Groups Grid */
            .groups-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
                gap: 24px;
                animation: fadeInUp 0.4s ease-out 0.15s both;
            }

            .group-card {
                animation: fadeInUp 0.4s ease-out both;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12);
                overflow: hidden;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
            }

            [data-theme="dark"] .group-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .group-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 40px rgba(139, 92, 246, 0.2);
            }

            .group-header {
                padding: 20px;
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            [data-theme="dark"] .group-header {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .group-tenant {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 0.8rem;
                color: #8b5cf6;
                font-weight: 600;
                margin-bottom: 10px;
            }

            .group-name {
                font-size: 1.15rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0;
                line-height: 1.4;
            }

            .group-name a {
                color: inherit;
                text-decoration: none;
                transition: color 0.2s;
            }

            .group-name a:hover {
                color: #8b5cf6;
            }

            .group-body {
                padding: 20px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .group-description {
                color: var(--htb-text-muted);
                font-size: 0.9rem;
                line-height: 1.6;
                margin-bottom: 16px;
                flex: 1;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .group-stats {
                display: flex;
                gap: 20px;
                margin-bottom: 16px;
            }

            .group-stat {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .group-stat i {
                color: #8b5cf6;
            }

            .group-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 16px;
                border-top: 1px solid rgba(139, 92, 246, 0.15);
            }

            .member-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                background: rgba(16, 185, 129, 0.1);
                color: #059669;
                border-radius: 8px;
                font-size: 0.8rem;
                font-weight: 600;
            }

            [data-theme="dark"] .member-badge {
                background: rgba(16, 185, 129, 0.2);
                color: #34d399;
            }

            .view-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.3s ease;
            }

            .view-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
            }

            /* Loading & Empty States */
            .loading-state,
            .empty-state {
                text-align: center;
                padding: 60px 20px;
            }

            .loading-spinner {
                width: 50px;
                height: 50px;
                border: 4px solid rgba(139, 92, 246, 0.2);
                border-top-color: #8b5cf6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .empty-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 20px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .empty-icon i {
                font-size: 2rem;
                color: #8b5cf6;
            }

            .empty-title {
                font-size: 1.2rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 8px 0;
            }

            .empty-message {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
            }

            /* Pagination */
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 8px;
                margin-top: 30px;
            }

            .pagination button {
                padding: 10px 16px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                background: rgba(255, 255, 255, 0.8);
                border-radius: 10px;
                font-size: 0.9rem;
                color: var(--htb-text-main);
                cursor: pointer;
                transition: all 0.3s ease;
            }

            [data-theme="dark"] .pagination button {
                background: rgba(30, 41, 59, 0.6);
                border-color: rgba(139, 92, 246, 0.3);
            }

            .pagination button:hover:not(:disabled) {
                border-color: #8b5cf6;
                background: rgba(139, 92, 246, 0.1);
            }

            .pagination button:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .pagination .page-info {
                padding: 10px 16px;
                color: var(--htb-text-muted);
                font-size: 0.9rem;
            }

            /* Touch Targets */
            .view-btn,
            button,
            .filter-select,
            .search-box input,
            .header-actions a {
                min-height: 44px;
            }

            .search-box input,
            .filter-select {
                font-size: 16px !important;
            }

            /* Focus Visible */
            .view-btn:focus-visible,
            button:focus-visible,
            .filter-select:focus-visible,
            .search-box input:focus-visible,
            .header-actions a:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            @media (max-width: 768px) {
                .page-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .filters-bar {
                    flex-direction: column;
                }

                .search-box {
                    width: 100%;
                }

                .filter-select {
                    width: 100%;
                }

                .groups-grid {
                    grid-template-columns: 1fr;
                }

                .group-card:hover {
                    transform: none;
                }
            }
        </style>

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

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
