/**
 * Federation Listings - JavaScript
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    // DOM Elements
    const searchInput = document.getElementById('listing-search');
    const tenantFilter = document.getElementById('tenant-filter');
    const typeFilter = document.getElementById('type-filter');
    const categoryFilter = document.getElementById('category-filter');
    const grid = document.getElementById('listings-grid');
    const countLabel = document.getElementById('listings-count');
    const spinner = document.getElementById('search-spinner');
    const loadMoreSpinner = document.getElementById('load-more-spinner');
    const infiniteScrollTrigger = document.getElementById('infinite-scroll-trigger');

    // State
    let debounceTimer;
    let currentOffset = grid ? grid.querySelectorAll('.listing-card').length : 0;
    let isLoading = false;
    let hasMore = currentOffset >= 30;

    // Get base path from URL or default
    const basePath = window.location.pathname.split('/federation')[0] || '';

    // Initialize if elements exist
    if (!searchInput || !grid) return;

    // Search & filter handlers
    searchInput.addEventListener('keyup', function() {
        clearTimeout(debounceTimer);
        if (spinner) spinner.classList.remove('hidden');
        debounceTimer = setTimeout(function() {
            currentOffset = 0;
            hasMore = true;
            fetchListings();
        }, 300);
    });

    [tenantFilter, typeFilter, categoryFilter].forEach(function(el) {
        if (el) {
            el.addEventListener('change', function() {
                currentOffset = 0;
                hasMore = true;
                fetchListings();
            });
        }
    });

    function fetchListings(append) {
        append = append || false;

        const params = new URLSearchParams({
            q: searchInput.value,
            tenant: tenantFilter ? tenantFilter.value : '',
            type: typeFilter ? typeFilter.value : '',
            category: categoryFilter ? categoryFilter.value : '',
            offset: append ? currentOffset : 0,
            limit: 30
        });

        if (!append && spinner) spinner.classList.remove('hidden');
        isLoading = true;

        fetch(basePath + '/federation/listings/api?' + params.toString())
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (spinner) spinner.classList.add('hidden');
                if (loadMoreSpinner) loadMoreSpinner.classList.add('hidden');
                isLoading = false;

                if (data.success) {
                    if (append) {
                        appendListings(data.listings);
                        currentOffset += data.listings.length;
                    } else {
                        renderGrid(data.listings);
                        currentOffset = data.listings.length;
                    }
                    hasMore = data.hasMore;

                    const count = append ? currentOffset : data.listings.length;
                    if (countLabel) {
                        countLabel.textContent = count + ' listing' + (count !== 1 ? 's' : '') + ' found';
                    }
                }
            })
            .catch(function(err) {
                console.error('Fetch error:', err);
                if (spinner) spinner.classList.add('hidden');
                if (loadMoreSpinner) loadMoreSpinner.classList.add('hidden');
                isLoading = false;
            });
    }

    function renderGrid(listings) {
        if (!listings || listings.length === 0) {
            grid.innerHTML =
                '<div class="empty-state" role="status">' +
                    '<div class="empty-state-icon" aria-hidden="true"><i class="fa-solid fa-search"></i></div>' +
                    '<h3 class="empty-state-title">No listings found</h3>' +
                    '<p class="empty-state-text">Try adjusting your search or filters.</p>' +
                '</div>';
            return;
        }

        grid.innerHTML = '';
        listings.forEach(function(l) {
            grid.appendChild(createListingCard(l));
        });
    }

    function appendListings(listings) {
        listings.forEach(function(l) {
            grid.appendChild(createListingCard(l));
        });
    }

    function createListingCard(listing) {
        const fallbackAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(listing.owner_name || 'User') + '&background=8b5cf6&color=fff&size=100';
        const avatar = listing.owner_avatar || fallbackAvatar;
        const listingUrl = basePath + '/federation/listings/' + listing.id;
        const type = listing.type || 'offer';
        const typeIcon = type === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding';

        const card = document.createElement('a');
        card.href = listingUrl;
        card.className = 'listing-card';
        card.setAttribute('role', 'listitem');
        card.setAttribute('aria-label', ucfirst(type) + ': ' + escapeHtml(listing.title || 'Untitled') + ' by ' + escapeHtml(listing.owner_name || 'Unknown'));

        card.innerHTML =
            '<div class="listing-card-body">' +
                '<span class="listing-type ' + type + '">' +
                    '<i class="fa-solid ' + typeIcon + '" aria-hidden="true"></i>' +
                    ucfirst(type) +
                '</span>' +
                '<div class="listing-tenant">' +
                    '<i class="fa-solid fa-building" aria-hidden="true"></i>' +
                    escapeHtml(listing.tenant_name || 'Partner') +
                '</div>' +
                '<h3 class="listing-title">' + escapeHtml(listing.title || 'Untitled') + '</h3>' +
                (listing.description ? '<p class="listing-description">' + escapeHtml(listing.description) + '</p>' : '') +
                (listing.category_name ? '<span class="listing-category"><i class="fa-solid fa-tag" aria-hidden="true"></i>' + escapeHtml(listing.category_name) + '</span>' : '') +
                '<div class="listing-owner">' +
                    '<img src="' + escapeHtml(avatar) + '" onerror="this.src=\'' + fallbackAvatar + '\'" class="owner-avatar" alt="" loading="lazy">' +
                    '<span class="owner-name">' + escapeHtml(listing.owner_name || 'Unknown') + '</span>' +
                '</div>' +
            '</div>';

        return card;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function ucfirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }

    // Infinite scroll
    if (infiniteScrollTrigger) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    isLoading = true;
                    if (loadMoreSpinner) loadMoreSpinner.classList.remove('hidden');
                    fetchListings(true);
                }
            });
        }, { rootMargin: '100px', threshold: 0.1 });
        observer.observe(infiniteScrollTrigger);
    }

    // Offline indicator
    const offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        window.addEventListener('online', function() { offlineBanner.classList.remove('visible'); });
        window.addEventListener('offline', function() { offlineBanner.classList.add('visible'); });
        if (!navigator.onLine) offlineBanner.classList.add('visible');
    }
})();
