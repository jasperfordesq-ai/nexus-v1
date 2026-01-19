<?php
// Federated Events - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Events";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Events - Partner Timebank Calendar');
Nexus\Core\SEO::setDescription('Discover and join events from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-events-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Federation Hub
        </a>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fa-solid fa-calendar-days"></i>
                    Federated Events
                </h1>
                <p class="page-subtitle">Discover and join events from partner timebanks</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="event-search" placeholder="Search events...">
            </div>
            <select id="timebank-filter" class="filter-select">
                <option value="">All Timebanks</option>
            </select>
            <select id="time-filter" class="filter-select">
                <option value="upcoming">Upcoming Events</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="all">All Events</option>
            </select>
        </div>

        <!-- Events Grid -->
        <div id="events-container">
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p style="color: var(--htb-text-muted);">Loading federated events...</p>
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

    const eventsContainer = document.getElementById('events-container');
    const searchInput = document.getElementById('event-search');
    const timebankFilter = document.getElementById('timebank-filter');
    const timeFilter = document.getElementById('time-filter');
    const pagination = document.getElementById('pagination');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');

    // Load events
    async function loadEvents() {
        eventsContainer.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p style="color: var(--htb-text-muted);">Loading federated events...</p>
            </div>
        `;

        const params = new URLSearchParams({
            page: currentPage,
            search: searchInput.value,
            tenant_id: timebankFilter.value,
            time_filter: timeFilter.value
        });

        try {
            const response = await fetch(`${basePath}/federation/events/api?${params}`);
            const data = await response.json();

            if (data.success) {
                renderEvents(data.events);
                updatePagination(data.pagination);
                updateTimebankFilter(data.tenants || []);
            } else {
                showError(data.error || 'Failed to load events');
            }
        } catch (error) {
            console.error('Error loading events:', error);
            showError('Failed to load events. Please try again.');
        }
    }

    // Render events grid
    function renderEvents(events) {
        if (!events || events.length === 0) {
            eventsContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-calendar-xmark"></i>
                    </div>
                    <h3 class="empty-title">No Events Found</h3>
                    <p class="empty-message">
                        There are no federated events matching your criteria.<br>
                        Check back later for new events from partner timebanks.
                    </p>
                </div>
            `;
            return;
        }

        const html = `
            <div class="events-grid">
                ${events.map(event => renderEventCard(event)).join('')}
            </div>
        `;
        eventsContainer.innerHTML = html;
    }

    // Render single event card
    function renderEventCard(event) {
        const eventDate = new Date(event.event_date);
        const formattedDate = eventDate.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });

        const spotsLeft = event.max_attendees ? (event.max_attendees - (event.attendee_count || 0)) : null;
        let spotsClass = 'spots-available';
        let spotsText = 'Spots Available';

        if (spotsLeft !== null) {
            if (spotsLeft <= 0) {
                spotsClass = 'spots-full';
                spotsText = 'Full';
            } else if (spotsLeft <= 5) {
                spotsClass = 'spots-limited';
                spotsText = `${spotsLeft} spots left`;
            } else {
                spotsText = `${spotsLeft} spots left`;
            }
        }

        return `
            <div class="event-card">
                <div class="event-header">
                    <div class="event-date-badge">
                        <i class="fa-solid fa-clock"></i>
                        ${formattedDate}
                    </div>
                    <h3 class="event-title">
                        <a href="${basePath}/federation/events/${event.id}?tenant=${event.tenant_id}">
                            ${escapeHtml(event.title)}
                        </a>
                    </h3>
                    <span class="event-tenant">
                        <i class="fa-solid fa-building"></i>
                        ${escapeHtml(event.tenant_name || 'Partner Timebank')}
                    </span>
                </div>
                <div class="event-body">
                    <p class="event-description">${escapeHtml(event.description || 'No description provided.')}</p>
                    <div class="event-meta">
                        ${event.location ? `
                            <div class="event-meta-item">
                                <i class="fa-solid fa-location-dot"></i>
                                ${escapeHtml(event.location)}
                            </div>
                        ` : ''}
                        ${event.organizer_name ? `
                            <div class="event-meta-item">
                                <i class="fa-solid fa-user"></i>
                                ${escapeHtml(event.organizer_name)}
                            </div>
                        ` : ''}
                    </div>
                    <div class="event-footer">
                        <span class="spots-badge ${spotsClass}">${spotsText}</span>
                        <a href="${basePath}/federation/events/${event.id}?tenant=${event.tenant_id}" class="view-btn">
                            View Details
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
        eventsContainer.innerHTML = `
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
            loadEvents();
        }, 300);
    });

    timebankFilter.addEventListener('change', function() {
        currentPage = 1;
        loadEvents();
    });

    timeFilter.addEventListener('change', function() {
        currentPage = 1;
        loadEvents();
    });

    prevBtn.addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadEvents();
        }
    });

    nextBtn.addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadEvents();
        }
    });

    // Initial load
    loadEvents();
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
