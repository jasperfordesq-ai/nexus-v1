/**
 * Federation Events - JavaScript
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    var basePath = window.federationEventsBasePath || '';
    var currentPage = 1;
    var totalPages = 1;
    var searchTimeout;

    var eventsContainer = document.getElementById('events-container');
    var searchInput = document.getElementById('event-search');
    var timebankFilter = document.getElementById('timebank-filter');
    var timeFilter = document.getElementById('time-filter');
    var pagination = document.getElementById('pagination');
    var prevBtn = document.getElementById('prev-page');
    var nextBtn = document.getElementById('next-page');
    var pageInfo = document.getElementById('page-info');

    if (!eventsContainer) return;

    // Load events
    function loadEvents() {
        eventsContainer.innerHTML =
            '<div class="loading-state" role="status">' +
                '<div class="loading-spinner" aria-hidden="true"></div>' +
                '<p class="loading-text">Loading federated events...</p>' +
            '</div>';

        var params = new URLSearchParams({
            page: currentPage,
            search: searchInput ? searchInput.value : '',
            tenant_id: timebankFilter ? timebankFilter.value : '',
            time_filter: timeFilter ? timeFilter.value : 'upcoming'
        });

        fetch(basePath + '/federation/events/api?' + params)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    renderEvents(data.events);
                    updatePagination(data.pagination);
                    updateTimebankFilter(data.tenants || []);
                } else {
                    showError(data.error || 'Failed to load events');
                }
            })
            .catch(function(error) {
                console.error('Error loading events:', error);
                showError('Failed to load events. Please try again.');
            });
    }

    // Render events grid
    function renderEvents(events) {
        if (!events || events.length === 0) {
            eventsContainer.innerHTML =
                '<div class="empty-state" role="status">' +
                    '<div class="empty-state-icon" aria-hidden="true">' +
                        '<i class="fa-solid fa-calendar-xmark"></i>' +
                    '</div>' +
                    '<h3 class="empty-state-title">No Events Found</h3>' +
                    '<p class="empty-state-text">' +
                        'There are no federated events matching your criteria.<br>' +
                        'Check back later for new events from partner timebanks.' +
                    '</p>' +
                '</div>';
            return;
        }

        var html = '<div class="events-grid" role="list" aria-label="Federated events">';
        events.forEach(function(event) {
            html += renderEventCard(event);
        });
        html += '</div>';
        eventsContainer.innerHTML = html;
    }

    // Render single event card
    function renderEventCard(event) {
        var eventDate = new Date(event.event_date);
        var formattedDate = eventDate.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
        var isoDate = eventDate.toISOString();

        var spotsLeft = event.max_attendees ? (event.max_attendees - (event.attendee_count || 0)) : null;
        var spotsClass = 'spots-available';
        var spotsText = 'Spots Available';

        if (spotsLeft !== null) {
            if (spotsLeft <= 0) {
                spotsClass = 'spots-full';
                spotsText = 'Full';
            } else if (spotsLeft <= 5) {
                spotsClass = 'spots-limited';
                spotsText = spotsLeft + ' spots left';
            } else {
                spotsText = spotsLeft + ' spots left';
            }
        }

        return '' +
            '<article class="event-card" role="listitem" aria-label="' + escapeHtml(event.title) + ' event">' +
                '<div class="event-header">' +
                    '<time class="event-date-badge" datetime="' + isoDate + '">' +
                        '<i class="fa-solid fa-clock" aria-hidden="true"></i>' +
                        formattedDate +
                    '</time>' +
                    '<h3 class="event-title">' +
                        '<a href="' + basePath + '/federation/events/' + event.id + '?tenant=' + event.tenant_id + '">' +
                            escapeHtml(event.title) +
                        '</a>' +
                    '</h3>' +
                    '<span class="event-tenant">' +
                        '<i class="fa-solid fa-building" aria-hidden="true"></i>' +
                        escapeHtml(event.tenant_name || 'Partner Timebank') +
                    '</span>' +
                '</div>' +
                '<div class="event-body">' +
                    '<p class="event-description">' + escapeHtml(event.description || 'No description provided.') + '</p>' +
                    '<div class="event-meta">' +
                        (event.location ? '' +
                            '<div class="event-meta-item">' +
                                '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>' +
                                '<span>' + escapeHtml(event.location) + '</span>' +
                            '</div>' : '') +
                        (event.organizer_name ? '' +
                            '<div class="event-meta-item">' +
                                '<i class="fa-solid fa-user" aria-hidden="true"></i>' +
                                '<span>' + escapeHtml(event.organizer_name) + '</span>' +
                            '</div>' : '') +
                    '</div>' +
                    '<div class="event-footer">' +
                        '<span class="spots-badge ' + spotsClass + '" role="status">' + spotsText + '</span>' +
                        '<a href="' + basePath + '/federation/events/' + event.id + '?tenant=' + event.tenant_id + '" class="view-btn" aria-label="View details for ' + escapeHtml(event.title) + '">' +
                            'View Details' +
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

        var currentValue = timebankFilter.value;
        timebankFilter.innerHTML = '<option value="">All Timebanks</option>';

        tenants.forEach(function(tenant) {
            var option = document.createElement('option');
            option.value = tenant.id;
            option.textContent = tenant.name;
            if (tenant.id == currentValue) option.selected = true;
            timebankFilter.appendChild(option);
        });
    }

    // Show error
    function showError(message) {
        eventsContainer.innerHTML =
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
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                currentPage = 1;
                loadEvents();
            }, 300);
        });
    }

    if (timebankFilter) {
        timebankFilter.addEventListener('change', function() {
            currentPage = 1;
            loadEvents();
        });
    }

    if (timeFilter) {
        timeFilter.addEventListener('change', function() {
            currentPage = 1;
            loadEvents();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadEvents();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (currentPage < totalPages) {
                currentPage++;
                loadEvents();
            }
        });
    }

    // Initial load
    loadEvents();

    // Offline indicator
    var offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        window.addEventListener('online', function() { offlineBanner.classList.remove('visible'); });
        window.addEventListener('offline', function() { offlineBanner.classList.add('visible'); });
        if (!navigator.onLine) offlineBanner.classList.add('visible');
    }
})();
