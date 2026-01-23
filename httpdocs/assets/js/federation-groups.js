/**
 * Federation Groups - JavaScript
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    const basePath = window.federationGroupsBasePath || '';
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

    if (!groupsContainer) return;

    // Load groups
    function loadGroups() {
        groupsContainer.innerHTML =
            '<div class="loading-state" role="status">' +
                '<div class="loading-spinner" aria-hidden="true"></div>' +
                '<p class="loading-text">Loading federated groups...</p>' +
            '</div>';

        const params = new URLSearchParams({
            page: currentPage,
            search: searchInput ? searchInput.value : '',
            tenant_id: timebankFilter ? timebankFilter.value : ''
        });

        fetch(basePath + '/federation/groups/api?' + params)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    renderGroups(data.groups);
                    updatePagination(data.pagination);
                    updateTimebankFilter(data.tenants || []);
                } else {
                    showError(data.error || 'Failed to load groups');
                }
            })
            .catch(function(error) {
                console.error('Error loading groups:', error);
                showError('Failed to load groups. Please try again.');
            });
    }

    // Render groups grid
    function renderGroups(groups) {
        if (!groups || groups.length === 0) {
            groupsContainer.innerHTML =
                '<div class="empty-state" role="status">' +
                    '<div class="empty-state-icon" aria-hidden="true">' +
                        '<i class="fa-solid fa-people-group"></i>' +
                    '</div>' +
                    '<h3 class="empty-state-title">No Groups Found</h3>' +
                    '<p class="empty-state-text">' +
                        'There are no federated groups matching your criteria.<br>' +
                        'Check back later for new groups from partner timebanks.' +
                    '</p>' +
                '</div>';
            return;
        }

        let html = '<div class="groups-grid" role="list" aria-label="Federated groups">';
        groups.forEach(function(group) {
            html += renderGroupCard(group);
        });
        html += '</div>';
        groupsContainer.innerHTML = html;
    }

    // Render single group card
    function renderGroupCard(group) {
        return '' +
            '<article class="group-card" role="listitem" aria-label="' + escapeHtml(group.name) + ' group">' +
                '<div class="group-header">' +
                    '<span class="group-tenant">' +
                        '<i class="fa-solid fa-building" aria-hidden="true"></i>' +
                        escapeHtml(group.tenant_name || 'Partner Timebank') +
                    '</span>' +
                    '<h3 class="group-name">' +
                        '<a href="' + basePath + '/federation/groups/' + group.id + '?tenant=' + group.tenant_id + '">' +
                            escapeHtml(group.name) +
                        '</a>' +
                    '</h3>' +
                '</div>' +
                '<div class="group-body">' +
                    '<p class="group-description">' + escapeHtml(group.description || 'No description provided.') + '</p>' +
                    '<div class="group-stats">' +
                        '<div class="group-stat">' +
                            '<i class="fa-solid fa-users" aria-hidden="true"></i>' +
                            '<span>' + (group.member_count || 0) + ' members</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="group-footer">' +
                        (group.is_member ?
                            '<span class="member-badge" role="status">' +
                                '<i class="fa-solid fa-check" aria-hidden="true"></i>' +
                                'Member' +
                            '</span>' : '<span></span>') +
                        '<a href="' + basePath + '/federation/groups/' + group.id + '?tenant=' + group.tenant_id + '" class="view-btn" aria-label="View ' + escapeHtml(group.name) + ' group">' +
                            'View Group' +
                            '<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>' +
                        '</a>' +
                    '</div>' +
                '</div>' +
            '</article>';
    }

    // Update pagination
    function updatePagination(paginationData) {
        if (!paginationData || !pagination) return;

        totalPages = paginationData.total_pages || 1;
        currentPage = paginationData.current_page || 1;

        if (totalPages > 1) {
            pagination.classList.remove('hidden');
            pageInfo.textContent = 'Page ' + currentPage + ' of ' + totalPages;
            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;
        } else {
            pagination.classList.add('hidden');
        }
    }

    // Update timebank filter options
    function updateTimebankFilter(tenants) {
        if (!timebankFilter) return;

        const currentValue = timebankFilter.value;
        timebankFilter.innerHTML = '<option value="">All Timebanks</option>';

        tenants.forEach(function(tenant) {
            const option = document.createElement('option');
            option.value = tenant.id;
            option.textContent = tenant.name;
            if (tenant.id == currentValue) option.selected = true;
            timebankFilter.appendChild(option);
        });
    }

    // Show error
    function showError(message) {
        groupsContainer.innerHTML =
            '<div class="empty-state" role="alert">' +
                '<div class="empty-state-icon" aria-hidden="true">' +
                    '<i class="fa-solid fa-exclamation-triangle"></i>' +
                '</div>' +
                '<h3 class="empty-state-title">Error</h3>' +
                '<p class="empty-state-text">' + escapeHtml(message) + '</p>' +
            '</div>';
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                currentPage = 1;
                loadGroups();
            }, 300);
        });
    }

    if (timebankFilter) {
        timebankFilter.addEventListener('change', function() {
            currentPage = 1;
            loadGroups();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadGroups();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (currentPage < totalPages) {
                currentPage++;
                loadGroups();
            }
        });
    }

    // Initial load
    loadGroups();

    // Offline indicator
    const offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        window.addEventListener('online', function() { offlineBanner.classList.remove('visible'); });
        window.addEventListener('offline', function() { offlineBanner.classList.add('visible'); });
        if (!navigator.onLine) offlineBanner.classList.add('visible');
    }
})();
