/**
 * CivicOne Members Directory - AJAX Search
 * Provides real-time search with debouncing for members directory
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('member-search');
        const membersList = document.getElementById('members-list');
        const emptyState = document.getElementById('empty-state');
        const resultsCount = document.getElementById('results-count');
        const spinner = document.getElementById('search-spinner');
        let debounceTimer;

        if (!searchInput) return;

        searchInput.addEventListener('input', function(e) {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();

            spinner.style.display = 'block';

            debounceTimer = setTimeout(() => {
                if (query.length === 0) {
                    window.location.href = basePath + '/members';
                    return;
                }
                fetchMembers(query);
            }, 400);
        });

        function fetchMembers(query) {
            fetch(basePath + '/members?q=' + encodeURIComponent(query), {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    renderList(data.data);
                    updateResultsCount(data.data.length, data.total || data.data.length);
                    spinner.style.display = 'none';
                })
                .catch(err => {
                    console.error('Search error:', err);
                    spinner.style.display = 'none';
                });
        }

        function renderList(members) {
            membersList.innerHTML = '';

            if (members.length === 0) {
                emptyState.style.display = 'block';
                return;
            }
            emptyState.style.display = 'none';

            members.forEach(member => {
                const li = document.createElement('li');
                li.className = 'civicone-member-item';

                const name = escapeHtml(member.first_name + ' ' + member.last_name);
                const location = member.location ? escapeHtml(member.location) : '';

                // Check online status - active within 5 minutes
                const isOnline = member.last_active_at && (new Date(member.last_active_at) > new Date(Date.now() - 5 * 60 * 1000));
                const onlineIndicator = isOnline ? '<span class="civicone-status-indicator civicone-status-indicator--online" title="Active now" aria-label="Currently online"></span>' : '';

                // Avatar HTML
                let avatarHtml = '';
                if (member.avatar_url) {
                    avatarHtml = `<img src="${escapeHtml(member.avatar_url)}" alt="" class="civicone-avatar">`;
                } else {
                    avatarHtml = `
                        <div class="civicone-avatar civicone-avatar--placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>`;
                }

                // Location HTML
                let locationHtml = '';
                if (location) {
                    locationHtml = `
                        <p class="civicone-member-item__meta">
                            <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            ${location}
                        </p>`;
                }

                li.innerHTML = `
                    <div class="civicone-member-item__avatar">
                        ${avatarHtml}
                        ${onlineIndicator}
                    </div>
                    <div class="civicone-member-item__content">
                        <h3 class="civicone-member-item__name">
                            <a href="${basePath}/profile/${member.id}" class="civicone-link">
                                ${name}
                            </a>
                        </h3>
                        ${locationHtml}
                    </div>
                    <div class="civicone-member-item__actions">
                        <a href="${basePath}/profile/${member.id}" class="civicone-button civicone-button--secondary">
                            View profile
                        </a>
                    </div>
                `;

                membersList.appendChild(li);
            });
        }

        function updateResultsCount(showing, total) {
            resultsCount.innerHTML = `Showing <strong>${showing}</strong> of <strong>${total}</strong> members`;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }
    });
})();
