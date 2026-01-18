<?php
/**
 * Post Box Home - Modern Layout
 * A modern, card-based home page with post box composer and feed
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Guest';
$userAvatar = $_SESSION['user_avatar'] ?? $basePath . '/assets/images/default-avatar.png';

// Hero configuration for layout header
$heroTitle = 'Community Hub';
$heroSubtitle = 'Share, Connect, and Exchange';

require __DIR__ . '/../layouts/modern/header.php';
?>

<div class="post-box-home">
    <!-- Post Composer Section -->
    <?php if ($isLoggedIn): ?>
    <section class="composer-section">
        <div class="htb-card composer-card">
            <div class="composer-header">
                <img src="<?= htmlspecialchars($userAvatar) ?>" loading="lazy" alt="Your avatar" class="composer-avatar">
                <button class="composer-trigger" onclick="openComposerModal()">
                    <span>What would you like to share, <?= htmlspecialchars($userName) ?>?</span>
                </button>
            </div>
            <div class="composer-actions">
                <button class="composer-action-btn" onclick="openComposerModal('offer')">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span>Offer</span>
                </button>
                <button class="composer-action-btn" onclick="openComposerModal('request')">
                    <i class="fas fa-hand-paper"></i>
                    <span>Request</span>
                </button>
                <button class="composer-action-btn" onclick="openComposerModal('event')">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Event</span>
                </button>
                <button class="composer-action-btn" onclick="openComposerModal('post')">
                    <i class="fas fa-edit"></i>
                    <span>Post</span>
                </button>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content Grid -->
    <div class="post-box-grid">
        <!-- Primary Feed Column -->
        <main class="feed-column">
            <!-- Filter Tabs -->
            <div class="feed-filters">
                <button class="filter-tab active" data-filter="all">
                    <i class="fas fa-stream"></i> All
                </button>
                <button class="filter-tab" data-filter="offers">
                    <i class="fas fa-hand-holding-heart"></i> Offers
                </button>
                <button class="filter-tab" data-filter="requests">
                    <i class="fas fa-hand-paper"></i> Requests
                </button>
                <button class="filter-tab" data-filter="events">
                    <i class="fas fa-calendar"></i> Events
                </button>
            </div>

            <!-- Feed Container -->
            <div class="feed-container" id="feedContainer">
                <!-- Sample Feed Item: Offer -->
                <article class="fb-card feed-item" data-type="offer">
                    <div class="feed-item-header">
                        <img src="<?= $basePath ?>" loading="lazy"/assets/images/default-avatar.png" alt="User" class="feed-avatar">
                        <div class="feed-item-meta-wrapper">
                            <span class="feed-item-author-name">Sarah Johnson</span>
                            <span class="feed-item-verb">is offering</span>
                            <div class="feed-item-meta">
                                <i class="far fa-clock"></i> 2 hours ago
                                <span class="meta-separator">·</span>
                                <i class="fas fa-map-marker-alt"></i> Downtown
                            </div>
                        </div>
                        <span class="feed-type-badge badge-offer">Offer</span>
                    </div>
                    <div class="feed-item-body">
                        <h3 class="feed-item-title">Guitar Lessons for Beginners</h3>
                        <p class="feed-item-description">
                            I'm offering free guitar lessons for anyone interested in learning!
                            I have 10 years of experience and can teach acoustic or electric guitar.
                            Sessions are 1 hour each.
                        </p>
                        <div class="feed-item-tags">
                            <span class="tag">Music</span>
                            <span class="tag">Education</span>
                            <span class="tag">1 hour</span>
                        </div>
                    </div>
                    <div class="feed-item-media">
                        <img src="<?= $basePath ?>" loading="lazy"/assets/images/placeholder-guitar.jpg" alt="Guitar lessons" loading="lazy">
                    </div>
                    <div class="feed-reactions-row">
                        <span class="reaction-count"><i class="fas fa-heart"></i> 24</span>
                        <span class="reaction-count"><i class="fas fa-comment"></i> 8 comments</span>
                    </div>
                    <div class="feed-action-bar">
                        <button class="feed-action-btn"><i class="far fa-heart"></i> Like</button>
                        <button class="feed-action-btn"><i class="far fa-comment"></i> Comment</button>
                        <button class="feed-action-btn"><i class="far fa-share-square"></i> Share</button>
                        <button class="feed-action-btn feed-action-primary"><i class="fas fa-handshake"></i> Connect</button>
                    </div>
                </article>

                <!-- Sample Feed Item: Request -->
                <article class="fb-card feed-item" data-type="request">
                    <div class="feed-item-header">
                        <img src="<?= $basePath ?>" loading="lazy"/assets/images/default-avatar.png" alt="User" class="feed-avatar">
                        <div class="feed-item-meta-wrapper">
                            <span class="feed-item-author-name">Michael Chen</span>
                            <span class="feed-item-verb">is looking for</span>
                            <div class="feed-item-meta">
                                <i class="far fa-clock"></i> 5 hours ago
                                <span class="meta-separator">·</span>
                                <i class="fas fa-map-marker-alt"></i> Westside
                            </div>
                        </div>
                        <span class="feed-type-badge badge-request">Request</span>
                    </div>
                    <div class="feed-item-body">
                        <h3 class="feed-item-title">Help Moving Furniture This Weekend</h3>
                        <p class="feed-item-description">
                            I'm moving to a new apartment and need help with some heavy furniture.
                            Looking for 2-3 people to help for about 3 hours on Saturday morning.
                            Will provide refreshments!
                        </p>
                        <div class="feed-item-tags">
                            <span class="tag">Moving</span>
                            <span class="tag">Physical Help</span>
                            <span class="tag">3 hours</span>
                        </div>
                    </div>
                    <div class="feed-reactions-row">
                        <span class="reaction-count"><i class="fas fa-heart"></i> 12</span>
                        <span class="reaction-count"><i class="fas fa-comment"></i> 5 comments</span>
                    </div>
                    <div class="feed-action-bar">
                        <button class="feed-action-btn"><i class="far fa-heart"></i> Like</button>
                        <button class="feed-action-btn"><i class="far fa-comment"></i> Comment</button>
                        <button class="feed-action-btn"><i class="far fa-share-square"></i> Share</button>
                        <button class="feed-action-btn feed-action-primary"><i class="fas fa-hand-holding-heart"></i> Offer Help</button>
                    </div>
                </article>

                <!-- Sample Feed Item: Event -->
                <article class="fb-card feed-item" data-type="event">
                    <div class="feed-item-header">
                        <img src="<?= $basePath ?>" loading="lazy"/assets/images/default-avatar.png" alt="User" class="feed-avatar">
                        <div class="feed-item-meta-wrapper">
                            <span class="feed-item-author-name">Community Center</span>
                            <span class="feed-item-verb">created an event</span>
                            <div class="feed-item-meta">
                                <i class="far fa-clock"></i> 1 day ago
                            </div>
                        </div>
                        <span class="feed-type-badge badge-event">Event</span>
                    </div>
                    <div class="feed-item-body">
                        <div class="event-date-box">
                            <span class="event-month">JAN</span>
                            <span class="event-day">15</span>
                        </div>
                        <div class="event-details">
                            <h3 class="feed-item-title">Community Skills Workshop</h3>
                            <p class="feed-item-description">
                                Join us for a day of skill sharing! Learn from community members
                                and share your own talents. Everyone is welcome.
                            </p>
                            <div class="event-info">
                                <span><i class="far fa-clock"></i> 10:00 AM - 4:00 PM</span>
                                <span><i class="fas fa-map-marker-alt"></i> Community Hall</span>
                                <span><i class="fas fa-users"></i> 45 attending</span>
                            </div>
                        </div>
                    </div>
                    <div class="feed-reactions-row">
                        <span class="reaction-count"><i class="fas fa-heart"></i> 56</span>
                        <span class="reaction-count"><i class="fas fa-comment"></i> 12 comments</span>
                    </div>
                    <div class="feed-action-bar">
                        <button class="feed-action-btn"><i class="far fa-heart"></i> Like</button>
                        <button class="feed-action-btn"><i class="far fa-comment"></i> Comment</button>
                        <button class="feed-action-btn"><i class="far fa-share-square"></i> Share</button>
                        <button class="feed-action-btn feed-action-primary"><i class="fas fa-calendar-check"></i> RSVP</button>
                    </div>
                </article>

                <!-- Load More -->
                <div class="load-more-container">
                    <button class="load-more-btn" onclick="loadMorePosts()">
                        <i class="fas fa-sync-alt"></i> Load More
                    </button>
                </div>
            </div>
        </main>

        <!-- Sidebar -->
        <aside class="sidebar-column">
            <!-- Quick Stats Card -->
            <?php if ($isLoggedIn): ?>
            <div class="htb-card sidebar-card">
                <div class="sidebar-card-header">
                    <h3><i class="fas fa-chart-line"></i> Your Activity</h3>
                </div>
                <div class="sidebar-card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value">12</span>
                            <span class="stat-label">Offers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">8</span>
                            <span class="stat-label">Requests</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">24</span>
                            <span class="stat-label">Hours Given</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">18</span>
                            <span class="stat-label">Hours Received</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Trending Tags -->
            <div class="htb-card sidebar-card">
                <div class="sidebar-card-header">
                    <h3><i class="fas fa-fire"></i> Trending</h3>
                </div>
                <div class="sidebar-card-body">
                    <div class="trending-tags">
                        <a href="#" class="trending-tag">#gardening</a>
                        <a href="#" class="trending-tag">#tutoring</a>
                        <a href="#" class="trending-tag">#cooking</a>
                        <a href="#" class="trending-tag">#repairs</a>
                        <a href="#" class="trending-tag">#childcare</a>
                    </div>
                </div>
            </div>

            <!-- Active Members -->
            <div class="htb-card sidebar-card">
                <div class="sidebar-card-header">
                    <h3><i class="fas fa-users"></i> Active Members</h3>
                </div>
                <div class="sidebar-card-body">
                    <div class="member-list">
                        <div class="member-item">
                            <img src="<?= $basePath ?>" loading="lazy"/assets/images/default-avatar.png" alt="Member" class="member-avatar">
                            <div class="member-info">
                                <span class="member-name">Emma Wilson</span>
                                <span class="member-skills">Cooking, Baking</span>
                            </div>
                        </div>
                        <div class="member-item">
                            <img src="<?= $basePath ?>" loading="lazy"/assets/images/default-avatar.png" alt="Member" class="member-avatar">
                            <div class="member-info">
                                <span class="member-name">James Taylor</span>
                                <span class="member-skills">Tech Support, Web Design</span>
                            </div>
                        </div>
                        <div class="member-item">
                            <img src="<?= $basePath ?>" loading="lazy"/assets/images/default-avatar.png" alt="Member" class="member-avatar">
                            <div class="member-info">
                                <span class="member-name">Lisa Park</span>
                                <span class="member-skills">Languages, Translation</span>
                            </div>
                        </div>
                    </div>
                    <a href="<?= $basePath ?>/members" class="view-all-link">View All Members →</a>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="htb-card sidebar-card">
                <div class="sidebar-card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Events</h3>
                </div>
                <div class="sidebar-card-body">
                    <div class="upcoming-events">
                        <div class="upcoming-event">
                            <div class="event-date-mini">
                                <span class="month">JAN</span>
                                <span class="day">15</span>
                            </div>
                            <div class="event-info-mini">
                                <span class="event-title">Skills Workshop</span>
                                <span class="event-time">10:00 AM</span>
                            </div>
                        </div>
                        <div class="upcoming-event">
                            <div class="event-date-mini">
                                <span class="month">JAN</span>
                                <span class="day">20</span>
                            </div>
                            <div class="event-info-mini">
                                <span class="event-title">Community Potluck</span>
                                <span class="event-time">6:00 PM</span>
                            </div>
                        </div>
                    </div>
                    <a href="<?= $basePath ?>/events" class="view-all-link">View All Events →</a>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Composer Modal -->
<div class="modal" role="dialog" aria-modal="true"-overlay" id="composerModal">
    <div class="modal" role="dialog" aria-modal="true"-container">
        <div class="modal" role="dialog" aria-modal="true"-header">
            <h2>Create Post</h2>
            <button class="modal" role="dialog" aria-modal="true"-close" onclick="closeComposerModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal" role="dialog" aria-modal="true"-body">
            <div class="composer-type-tabs">
                <button class="type-tab active" data-type="offer">
                    <i class="fas fa-hand-holding-heart"></i> Offer
                </button>
                <button class="type-tab" data-type="request">
                    <i class="fas fa-hand-paper"></i> Request
                </button>
                <button class="type-tab" data-type="event">
                    <i class="fas fa-calendar-plus"></i> Event
                </button>
                <button class="type-tab" data-type="post">
                    <i class="fas fa-edit"></i> Post
                </button>
            </div>
            <form id="composerForm" class="composer-form">
                <input type="hidden" name="type" id="postType" value="offer">
                <div class="form-group">
                    <input type="text" name="title" placeholder="Title" class="form-input" required>
                </div>
                <div class="form-group">
                    <textarea name="description" placeholder="Describe what you're offering or looking for..." class="form-textarea" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <input type="text" name="tags" placeholder="Tags (comma separated)" class="form-input">
                </div>
                <div class="form-group media-upload">
                    <label for="mediaInput" class="media-upload-label">
                        <i class="fas fa-image"></i>
                        <span>Add Photo</span>
                    </label>
                    <input type="file" id="mediaInput" name="media" accept="image/*" hidden>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeComposerModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Composer Modal Functions
function openComposerModal(type = 'offer') {
    const modal = document.getElementById('composerModal');
    const typeInput = document.getElementById('postType');
    const tabs = document.querySelectorAll('.type-tab');

    modal.classList.add('active');
    typeInput.value = type;

    tabs.forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });

    document.body.style.overflow = 'hidden';
}

function closeComposerModal() {
    const modal = document.getElementById('composerModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Type Tab Switching
document.querySelectorAll('.type-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.type-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('postType').value = tab.dataset.type;
    });
});

// Filter Tabs
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        const filter = tab.dataset.filter;
        const items = document.querySelectorAll('.feed-item');

        items.forEach(item => {
            if (filter === 'all' || item.dataset.type === filter.slice(0, -1)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
});

// Load More (placeholder)
function loadMorePosts() {
    const btn = document.querySelector('.load-more-btn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

    // Simulate loading
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Load More';
    }, 1500);
}

// Close modal on outside click
document.getElementById('composerModal').addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        closeComposerModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeComposerModal();
    }
});
</script>

<?php require __DIR__ . '/../layouts/modern/footer.php'; ?>
