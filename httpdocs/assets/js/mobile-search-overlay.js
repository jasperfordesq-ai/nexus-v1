/**
 * Mobile Search Overlay
 * Full-screen search experience like Instagram/TikTok
 *
 * Version: 1.0 - 2026-01-20
 *
 * Usage:
 * 1. MobileSearch.open() - Open the search overlay
 * 2. MobileSearch.close() - Close the overlay
 * 3. Add class "mobile-search-trigger" to any element to auto-bind
 *
 * Configuration:
 * - data-search-endpoint: API endpoint for search
 * - data-search-placeholder: Custom placeholder text
 * - data-search-tabs: JSON array of tab names
 */

(function() {
    'use strict';

    // Only run on mobile or native
    const isMobile = () => {
        return document.body.classList.contains('is-native') ||
               window.innerWidth <= 768 ||
               'ontouchstart' in window;
    };

    // Default configuration
    const defaultConfig = {
        endpoint: '/api/search',
        placeholder: 'Search members, listings, groups...',
        tabs: null, // ['All', 'Members', 'Listings', 'Groups']
        debounceMs: 300,
        minChars: 2,
        recentSearchesKey: 'mobile_recent_searches',
        maxRecentSearches: 10
    };

    // Overlay HTML template
    const createOverlayHTML = (config) => `
        <div class="mobile-search-overlay" id="mobile-search-overlay" role="dialog" aria-modal="true" aria-label="Search">
            <div class="mobile-search-header">
                <button class="mobile-search-back" aria-label="Close search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </button>
                <div class="mobile-search-input-wrap">
                    <svg class="mobile-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="search" class="mobile-search-input" placeholder="${config.placeholder}" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                    <button class="mobile-search-clear" aria-label="Clear search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <button class="mobile-search-cancel">Cancel</button>
            </div>
            ${config.tabs ? `
            <div class="mobile-search-tabs" role="tablist">
                ${config.tabs.map((tab, i) => `
                    <button class="mobile-search-tab ${i === 0 ? 'active' : ''}" role="tab" data-tab="${tab.toLowerCase()}">${tab}</button>
                `).join('')}
            </div>
            ` : ''}
            <div class="mobile-search-body">
                <div class="mobile-search-content">
                    <!-- Dynamic content goes here -->
                </div>
            </div>
        </div>
    `;

    // Recent searches section template
    const recentSearchesHTML = (searches) => {
        if (!searches || searches.length === 0) return '';
        return `
            <div class="mobile-search-section">
                <div class="mobile-search-section-header">
                    <span class="mobile-search-section-title">Recent Searches</span>
                    <button class="mobile-search-section-action" data-action="clear-recent">Clear All</button>
                </div>
                ${searches.map(search => `
                    <div class="mobile-search-item" data-search-term="${escapeHtml(search)}">
                        <div class="mobile-search-item-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="mobile-search-item-content">
                            <div class="mobile-search-item-title">${escapeHtml(search)}</div>
                        </div>
                        <button class="mobile-search-item-remove" data-remove-search="${escapeHtml(search)}" aria-label="Remove">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                `).join('')}
            </div>
        `;
    };

    // Search results template
    const searchResultsHTML = (results, query) => {
        if (!results || results.length === 0) {
            return `
                <div class="mobile-search-empty">
                    <div class="mobile-search-empty-icon">🔍</div>
                    <div class="mobile-search-empty-title">No results found</div>
                    <div class="mobile-search-empty-text">Try a different search term or check your spelling</div>
                </div>
            `;
        }

        return `
            <div class="mobile-search-section">
                ${results.map(result => `
                    <a href="${escapeHtml(result.url)}" class="mobile-search-item" data-result-id="${result.id || ''}">
                        <div class="mobile-search-item-icon ${result.type === 'listing' || result.type === 'group' ? 'square' : ''}">
                            ${result.image
                                ? `<img src="${escapeHtml(result.image)}" alt="">`
                                : `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    ${getIconForType(result.type)}
                                  </svg>`
                            }
                        </div>
                        <div class="mobile-search-item-content">
                            <div class="mobile-search-item-title">${highlightMatch(result.title, query)}</div>
                            ${result.subtitle ? `<div class="mobile-search-item-subtitle">${escapeHtml(result.subtitle)}</div>` : ''}
                        </div>
                    </a>
                `).join('')}
            </div>
        `;
    };

    // Loading state template
    const loadingHTML = () => `
        <div class="mobile-search-loading">
            <div class="mobile-search-spinner"></div>
        </div>
    `;

    // Helper: Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Helper: Highlight matching text
    function highlightMatch(text, query) {
        if (!query || !text) return escapeHtml(text);
        const escaped = escapeHtml(text);
        const regex = new RegExp(`(${escapeHtml(query)})`, 'gi');
        return escaped.replace(regex, '<span class="mobile-search-highlight">$1</span>');
    }

    // Helper: Get icon SVG path for result type
    function getIconForType(type) {
        const icons = {
            member: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
            listing: '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line>',
            group: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            event: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
            default: '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>'
        };
        return icons[type] || icons.default;
    }

    class MobileSearchOverlay {
        constructor(config = {}) {
            this.config = { ...defaultConfig, ...config };
            this.overlay = null;
            this.input = null;
            this.content = null;
            this.isOpen = false;
            this.debounceTimer = null;
            this.currentQuery = '';
            this.activeTab = 'all';
            this.abortController = null;

            this.init();
        }

        init() {
            // Create overlay if it doesn't exist
            this.createOverlay();

            // Bind events
            this.bindEvents();

            // Auto-bind trigger elements
            this.bindTriggers();
        }

        createOverlay() {
            if (document.getElementById('mobile-search-overlay')) {
                this.overlay = document.getElementById('mobile-search-overlay');
                this.input = this.overlay.querySelector('.mobile-search-input');
                this.content = this.overlay.querySelector('.mobile-search-content');
                return;
            }

            const container = document.createElement('div');
            container.innerHTML = createOverlayHTML(this.config);
            document.body.appendChild(container.firstElementChild);

            this.overlay = document.getElementById('mobile-search-overlay');
            this.input = this.overlay.querySelector('.mobile-search-input');
            this.content = this.overlay.querySelector('.mobile-search-content');
        }

        bindEvents() {
            // Back/Cancel buttons
            this.overlay.querySelector('.mobile-search-back').addEventListener('click', () => this.close());
            this.overlay.querySelector('.mobile-search-cancel').addEventListener('click', () => this.close());

            // Input events
            this.input.addEventListener('input', (e) => this.handleInput(e.target.value));
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.close();
                if (e.key === 'Enter') this.handleEnter();
            });

            // Clear button
            const clearBtn = this.overlay.querySelector('.mobile-search-clear');
            clearBtn.addEventListener('click', () => {
                this.input.value = '';
                this.input.focus();
                clearBtn.classList.remove('visible');
                this.showRecentSearches();
            });

            // Tab switching
            if (this.config.tabs) {
                this.overlay.querySelectorAll('.mobile-search-tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        this.overlay.querySelectorAll('.mobile-search-tab').forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');
                        this.activeTab = tab.dataset.tab;
                        if (this.currentQuery) {
                            this.performSearch(this.currentQuery);
                        }
                    });
                });
            }

            // Content click delegation
            this.content.addEventListener('click', (e) => {
                // Recent search item click
                const searchItem = e.target.closest('[data-search-term]');
                if (searchItem && !e.target.closest('[data-remove-search]')) {
                    const term = searchItem.dataset.searchTerm;
                    this.input.value = term;
                    this.handleInput(term);
                    return;
                }

                // Remove recent search
                const removeBtn = e.target.closest('[data-remove-search]');
                if (removeBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.removeRecentSearch(removeBtn.dataset.removeSearch);
                    return;
                }

                // Clear all recent
                const clearAllBtn = e.target.closest('[data-action="clear-recent"]');
                if (clearAllBtn) {
                    this.clearRecentSearches();
                    return;
                }

                // Result item click - save search term
                const resultItem = e.target.closest('.mobile-search-item[data-result-id]');
                if (resultItem && this.currentQuery) {
                    this.saveRecentSearch(this.currentQuery);
                }
            });

            // Hardware back button (Capacitor)
            document.addEventListener('backbutton', () => {
                if (this.isOpen) {
                    this.close();
                    return false;
                }
            });

            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }

        bindTriggers() {
            document.querySelectorAll('.mobile-search-trigger').forEach(trigger => {
                if (trigger.dataset.searchBound) return;
                trigger.dataset.searchBound = 'true';

                trigger.addEventListener('click', (e) => {
                    if (!isMobile()) return; // Let desktop handle normally

                    e.preventDefault();
                    this.open({
                        endpoint: trigger.dataset.searchEndpoint,
                        placeholder: trigger.dataset.searchPlaceholder
                    });
                });
            });

            // Watch for dynamically added triggers
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) {
                            if (node.matches && node.matches('.mobile-search-trigger') && !node.dataset.searchBound) {
                                this.bindTriggers();
                            }
                            if (node.querySelectorAll) {
                                node.querySelectorAll('.mobile-search-trigger:not([data-search-bound])').forEach(() => {
                                    this.bindTriggers();
                                });
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        }

        open(options = {}) {
            if (!isMobile()) return;

            // Merge options
            if (options.endpoint) this.config.endpoint = options.endpoint;
            if (options.placeholder) this.input.placeholder = options.placeholder;

            this.isOpen = true;
            this.overlay.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Show recent searches
            this.showRecentSearches();

            // Focus input after animation
            setTimeout(() => {
                this.input.focus();
            }, 300);

            // Trigger haptic
            this.triggerHaptic('light');
        }

        close() {
            if (!this.isOpen) return;

            this.isOpen = false;
            this.overlay.classList.remove('active');
            document.body.style.overflow = '';
            this.input.blur();

            // Cancel any pending requests
            if (this.abortController) {
                this.abortController.abort();
            }

            // Clear input
            setTimeout(() => {
                this.input.value = '';
                this.currentQuery = '';
                this.overlay.querySelector('.mobile-search-clear').classList.remove('visible');
            }, 300);
        }

        handleInput(value) {
            const trimmed = value.trim();
            this.currentQuery = trimmed;

            // Show/hide clear button
            const clearBtn = this.overlay.querySelector('.mobile-search-clear');
            clearBtn.classList.toggle('visible', value.length > 0);

            // Clear existing debounce
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            // Show recent if empty
            if (!trimmed) {
                this.showRecentSearches();
                return;
            }

            // Wait for minimum characters
            if (trimmed.length < this.config.minChars) {
                return;
            }

            // Debounce search
            this.debounceTimer = setTimeout(() => {
                this.performSearch(trimmed);
            }, this.config.debounceMs);
        }

        handleEnter() {
            if (this.currentQuery && this.currentQuery.length >= this.config.minChars) {
                this.saveRecentSearch(this.currentQuery);
                this.performSearch(this.currentQuery);
            }
        }

        async performSearch(query) {
            // Cancel previous request
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();

            // Show loading
            this.content.innerHTML = loadingHTML();

            try {
                const params = new URLSearchParams({
                    q: query,
                    type: this.activeTab !== 'all' ? this.activeTab : ''
                });

                const response = await fetch(`${this.config.endpoint}?${params}`, {
                    signal: this.abortController.signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) throw new Error('Search failed');

                const data = await response.json();
                const results = data.results || data.data || data || [];

                this.content.innerHTML = searchResultsHTML(results, query);

            } catch (error) {
                if (error.name === 'AbortError') return; // Cancelled, ignore

                console.error('Search error:', error);
                this.content.innerHTML = `
                    <div class="mobile-search-empty">
                        <div class="mobile-search-empty-icon">⚠️</div>
                        <div class="mobile-search-empty-title">Search unavailable</div>
                        <div class="mobile-search-empty-text">Please try again later</div>
                    </div>
                `;
            }
        }

        showRecentSearches() {
            const recent = this.getRecentSearches();
            this.content.innerHTML = recentSearchesHTML(recent);
        }

        getRecentSearches() {
            try {
                const stored = localStorage.getItem(this.config.recentSearchesKey);
                return stored ? JSON.parse(stored) : [];
            } catch {
                return [];
            }
        }

        saveRecentSearch(term) {
            if (!term) return;

            let recent = this.getRecentSearches();

            // Remove if already exists (will re-add at top)
            recent = recent.filter(s => s.toLowerCase() !== term.toLowerCase());

            // Add to beginning
            recent.unshift(term);

            // Limit size
            recent = recent.slice(0, this.config.maxRecentSearches);

            try {
                localStorage.setItem(this.config.recentSearchesKey, JSON.stringify(recent));
            } catch {
                // Storage full or disabled
            }
        }

        removeRecentSearch(term) {
            let recent = this.getRecentSearches();
            recent = recent.filter(s => s !== term);

            try {
                localStorage.setItem(this.config.recentSearchesKey, JSON.stringify(recent));
            } catch {
                // Ignore
            }

            this.showRecentSearches();
            this.triggerHaptic('light');
        }

        clearRecentSearches() {
            try {
                localStorage.removeItem(this.config.recentSearchesKey);
            } catch {
                // Ignore
            }

            this.showRecentSearches();
            this.triggerHaptic('medium');
        }

        triggerHaptic(type = 'light') {
            if (window.NexusNative?.Haptics?.impact) {
                window.NexusNative.Haptics.impact(type);
            } else if (navigator.vibrate) {
                navigator.vibrate(type === 'medium' ? 20 : 10);
            }
        }
    }

    // Initialize and expose globally
    const instance = new MobileSearchOverlay();

    window.MobileSearch = {
        open: (options) => instance.open(options),
        close: () => instance.close(),
        configure: (config) => Object.assign(instance.config, config)
    };

    // Auto-init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => instance.bindTriggers());
    }
})();
