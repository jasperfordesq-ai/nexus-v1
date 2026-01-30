/**
 * Admin Search Modal & Keyboard Shortcuts with AJAX Live Search
 * Extracted from admin-header.php for CLAUDE.md compliance
 */
(function() {
    'use strict';

    var searchModal = document.getElementById('adminSearchModal');
    var searchTrigger = document.getElementById('adminSearchTrigger');
    var searchInput = document.getElementById('adminSearchInput');
    var searchResults = document.getElementById('adminSearchResults');
    var activeIndex = -1;
    var basePath = window.NEXUS_ADMIN_CONFIG ? window.NEXUS_ADMIN_CONFIG.basePath : '';
    var pageTitle = window.NEXUS_ADMIN_CONFIG ? window.NEXUS_ADMIN_CONFIG.pageTitle : 'Dashboard';
    var pageIcon = window.NEXUS_ADMIN_CONFIG ? window.NEXUS_ADMIN_CONFIG.pageIcon : 'fa-gauge-high';
    var recentSection = document.getElementById('adminRecentSection');
    var recentItemsContainer = document.getElementById('adminRecentItems');
    var liveSection = document.getElementById('adminLiveSection');
    var liveResults = document.getElementById('adminLiveResults');
    var quickNav = document.getElementById('adminQuickNav');
    var RECENT_KEY = 'nexus_admin_recent';
    var MAX_RECENT = 5;
    var searchTimeout = null;
    var isSearching = false;

    // Track current page visit
    function trackPageVisit() {
        var pageUrl = window.location.pathname;

        // Don't track the main dashboard
        if (pageUrl === basePath + '/admin' || pageUrl === basePath + '/admin/') return;

        var recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

        // Remove duplicates
        recent = recent.filter(function(item) { return item.url !== pageUrl; });

        // Add current page to front
        recent.unshift({ title: pageTitle, icon: pageIcon, url: pageUrl, time: Date.now() });

        // Keep only MAX_RECENT items
        recent = recent.slice(0, MAX_RECENT);

        localStorage.setItem(RECENT_KEY, JSON.stringify(recent));
    }

    // Render recently viewed items
    function renderRecentItems() {
        var recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

        if (recent.length === 0 || !recentSection || !recentItemsContainer) {
            if (recentSection) recentSection.classList.add('hidden');
            return;
        }

        recentSection.classList.remove('hidden');
        recentItemsContainer.innerHTML = recent.map(function(item) {
            return '<a href="' + item.url + '" class="admin-search-item recent-item" data-search="' + item.title.toLowerCase() + '">' +
                '<i class="fa-solid ' + item.icon + '"></i>' +
                '<span>' + item.title + '</span>' +
                '</a>';
        }).join('');
    }

    // AJAX Live Search
    function performLiveSearch(query) {
        if (query.length < 2) {
            liveSection.classList.add('hidden');
            liveResults.innerHTML = '';
            quickNav.classList.remove('hidden');
            recentSection.classList.remove('hidden');
            return;
        }

        isSearching = true;

        fetch(basePath + '/admin/api/search?q=' + encodeURIComponent(query))
            .then(function(response) { return response.json(); })
            .then(function(data) {
                isSearching = false;
                renderLiveResults(data, query);
            })
            .catch(function(err) {
                isSearching = false;
                console.error('Search error:', err);
            });
    }

    // Render live search results with quick actions
    function renderLiveResults(data, query) {
        var html = '';
        var hasResults = false;

        // Users section
        if (data.users && data.users.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-users"></i> Users</div>';
            data.users.forEach(function(user) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + user.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + user.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(user.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(user.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(user.actions) +
                    '</div>' +
                '</div>';
            });
        }

        // Listings section
        if (data.listings && data.listings.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-rectangle-list"></i> Listings</div>';
            data.listings.forEach(function(listing) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + listing.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + listing.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(listing.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(listing.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(listing.actions) +
                    '</div>' +
                '</div>';
            });
        }

        // Blog posts section
        if (data.pages && data.pages.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-file-lines"></i> Content</div>';
            data.pages.forEach(function(page) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + page.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + page.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(page.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(page.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(page.actions) +
                    '</div>' +
                '</div>';
            });
        }

        if (hasResults) {
            liveSection.classList.remove('hidden');
            liveResults.innerHTML = html;
            quickNav.classList.add('hidden');
            recentSection.classList.add('hidden');
        } else {
            liveSection.classList.remove('hidden');
            liveResults.innerHTML = '<div class="admin-search-empty"><i class="fa-solid fa-magnifying-glass"></i> No results for "' + escapeHtml(query) + '"</div>';
            quickNav.classList.remove('hidden');
            recentSection.classList.remove('hidden');
        }

        activeIndex = -1;
        updateActiveItem();
    }

    function renderQuickActions(actions) {
        if (!actions || actions.length === 0) return '';
        return actions.map(function(action) {
            return '<a href="' + action.url + '" class="admin-quick-action" title="' + escapeHtml(action.label) + '">' +
                '<i class="fa-solid ' + action.icon + '"></i>' +
            '</a>';
        }).join('');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Track page on load
    trackPageVisit();

    function openSearch() {
        if (searchModal) {
            searchModal.classList.add('open');
            renderRecentItems();
            liveSection.classList.add('hidden');
            quickNav.classList.remove('hidden');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
                filterItems('');
            }
            activeIndex = -1;
            updateActiveItem();
        }
    }

    function getAllSearchItems() {
        return searchResults ? searchResults.querySelectorAll('.admin-search-item') : [];
    }

    function closeSearch() {
        if (searchModal) {
            searchModal.classList.remove('open');
        }
    }

    function filterItems(query) {
        query = query.toLowerCase().trim();

        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // If query is long enough, do AJAX search with debounce
        if (query.length >= 2) {
            searchTimeout = setTimeout(function() {
                performLiveSearch(query);
            }, 250); // 250ms debounce
        } else {
            liveSection.classList.add('hidden');
            quickNav.classList.remove('hidden');
        }

        // Also filter static items
        var allItems = quickNav.querySelectorAll('.admin-search-item');
        allItems.forEach(function(item) {
            var searchText = (item.getAttribute('data-search') || '') + ' ' + item.textContent;
            if (query === '' || searchText.toLowerCase().includes(query)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        // Hide recent section if searching
        if (recentSection) {
            var recentVisible = recentItemsContainer ? recentItemsContainer.querySelectorAll('.admin-search-item:not(.hidden)').length : 0;
            if (query === '' || recentVisible > 0) {
                recentSection.classList.remove('hidden');
            } else {
                recentSection.classList.add('hidden');
            }
        }

        activeIndex = -1;
        updateActiveItem();
    }

    function getVisibleItems() {
        return Array.from(getAllSearchItems()).filter(function(item) {
            return !item.classList.contains('hidden');
        });
    }

    function updateActiveItem() {
        var allItems = getAllSearchItems();
        allItems.forEach(function(item) {
            item.classList.remove('active');
        });
        var visible = getVisibleItems();
        if (activeIndex >= 0 && activeIndex < visible.length) {
            visible[activeIndex].classList.add('active');
            visible[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function navigateItems(direction) {
        var visible = getVisibleItems();
        if (visible.length === 0) return;

        if (direction === 'down') {
            activeIndex = activeIndex < visible.length - 1 ? activeIndex + 1 : 0;
        } else {
            activeIndex = activeIndex > 0 ? activeIndex - 1 : visible.length - 1;
        }
        updateActiveItem();
    }

    function selectActive() {
        var visible = getVisibleItems();
        if (activeIndex >= 0 && activeIndex < visible.length) {
            window.location.href = visible[activeIndex].href;
        } else if (visible.length > 0) {
            window.location.href = visible[0].href;
        }
    }

    // Event listeners
    if (searchTrigger) {
        searchTrigger.addEventListener('click', openSearch);
    }

    if (searchModal) {
        searchModal.querySelector('.admin-search-modal-backdrop').addEventListener('click', closeSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterItems(this.value);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateItems('down');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateItems('up');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                selectActive();
            } else if (e.key === 'Escape') {
                closeSearch();
            }
        });
    }

    // Global keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+K or Cmd+K to open search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }

        // Escape to close search
        if (e.key === 'Escape' && searchModal && searchModal.classList.contains('open')) {
            closeSearch();
        }

        // Alt+shortcuts for quick navigation (only when not in input)
        if (e.altKey && !e.target.matches('input, textarea, select')) {
            var shortcutMap = {
                'u': basePath + '/admin/users',
                'l': basePath + '/admin/listings',
                's': basePath + '/admin/settings',
                'd': basePath + '/admin',
                'n': basePath + '/admin/newsletters'
            };
            var key = e.key.toLowerCase();
            if (shortcutMap[key]) {
                e.preventDefault();
                window.location.href = shortcutMap[key];
            }
        }

        // "?" to show keyboard shortcuts help
        if (e.key === '?' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            showKeyboardHelp();
        }
    });

    function showKeyboardHelp() {
        var helpModal = document.getElementById('adminHelpModal');
        if (!helpModal) {
            helpModal = document.createElement('div');
            helpModal.id = 'adminHelpModal';
            helpModal.className = 'admin-search-modal';
            helpModal.innerHTML = '<div class="admin-search-modal-backdrop"></div>' +
                '<div class="admin-search-modal-content admin-help-modal-content">' +
                    '<div class="admin-help-header">' +
                        '<h3><i class="fa-solid fa-keyboard"></i>Keyboard Shortcuts</h3>' +
                    '</div>' +
                    '<div class="admin-help-body">' +
                        '<div class="admin-help-shortcuts">' +
                            '<div class="admin-help-row"><div><kbd>Ctrl</kbd><kbd>K</kbd></div><span>Open search</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>D</kbd></div><span>Dashboard</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>U</kbd></div><span>Users</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>L</kbd></div><span>Listings</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>S</kbd></div><span>Settings</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>N</kbd></div><span>Newsletters</span></div>' +
                            '<div class="admin-help-row"><div><kbd>ESC</kbd></div><span>Close modal</span></div>' +
                            '<div class="admin-help-row"><div><kbd>?</kbd></div><span>Show this help</span></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="admin-help-footer">' +
                        '<span>Press <kbd>ESC</kbd> to close</span>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(helpModal);

            helpModal.querySelector('.admin-search-modal-backdrop').addEventListener('click', function() {
                helpModal.classList.remove('open');
            });

            document.addEventListener('keydown', function(ev) {
                if (ev.key === 'Escape' && helpModal.classList.contains('open')) {
                    helpModal.classList.remove('open');
                }
            });
        }
        helpModal.classList.add('open');
    }
})();
