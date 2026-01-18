<?php
// Federated Events - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Events";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Events - Partner Timebank Calendar');
Nexus\Core\SEO::setDescription('Discover and join events from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-events-wrapper">

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

            #fed-events-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px 0;
            }

            .page-header {
                animation: fadeInUp 0.4s ease-out 0.05s both;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                flex-wrap: wrap;
                gap: 20px;
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

            /* Search & Filters */
            .filters-bar {
                animation: fadeInUp 0.4s ease-out 0.1s both;
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
                min-width: 160px;
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

            /* Events Grid */
            .events-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
                gap: 24px;
                animation: fadeInUp 0.4s ease-out 0.15s both;
            }

            .event-card {
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

            [data-theme="dark"] .event-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .event-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 40px rgba(139, 92, 246, 0.2);
            }

            .event-header {
                padding: 20px;
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            [data-theme="dark"] .event-header {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .event-date-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 14px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border-radius: 10px;
                font-size: 0.85rem;
                font-weight: 700;
                margin-bottom: 12px;
            }

            .event-title {
                font-size: 1.2rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 8px 0;
                line-height: 1.4;
            }

            .event-title a {
                color: inherit;
                text-decoration: none;
                transition: color 0.2s;
            }

            .event-title a:hover {
                color: #8b5cf6;
            }

            .event-tenant {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 0.85rem;
                color: #8b5cf6;
                font-weight: 600;
            }

            .event-body {
                padding: 20px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .event-description {
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

            .event-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                margin-bottom: 16px;
            }

            .event-meta-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .event-meta-item i {
                color: #8b5cf6;
                width: 16px;
            }

            .event-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 16px;
                border-top: 1px solid rgba(139, 92, 246, 0.15);
            }

            .spots-badge {
                font-size: 0.85rem;
                padding: 6px 12px;
                border-radius: 8px;
                font-weight: 600;
            }

            .spots-available {
                background: rgba(16, 185, 129, 0.1);
                color: #059669;
            }

            .spots-limited {
                background: rgba(245, 158, 11, 0.1);
                color: #d97706;
            }

            .spots-full {
                background: rgba(239, 68, 68, 0.1);
                color: #dc2626;
            }

            [data-theme="dark"] .spots-available {
                background: rgba(16, 185, 129, 0.2);
                color: #34d399;
            }

            [data-theme="dark"] .spots-limited {
                background: rgba(245, 158, 11, 0.2);
                color: #fbbf24;
            }

            [data-theme="dark"] .spots-full {
                background: rgba(239, 68, 68, 0.2);
                color: #f87171;
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
            .search-box input {
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
            .search-box input:focus-visible {
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

                .events-grid {
                    grid-template-columns: 1fr;
                }

                .event-card:hover {
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

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
