<?php
// CivicOne Group Details View - WCAG 2.1 AA Compliant
// CSS extracted to civicone-groups.css
$pageTitle = htmlspecialchars($group['name']) . ' - Nexus TimeBank';
require __DIR__ . '/../../layouts/civicone/header.php';

// Helper for avatars
function get_avatar_civic($url)
{
    return $url ?: ''; // Empty returns trigger SVG fallback
}
?>

<div class="civic-container civic-group-container">

    <!-- Group Header Card -->
    <div class="civic-group-header">
        <div class="civic-group-cover">
            <?php if (!empty($group['image_url'])): ?>
                <img src="<?= htmlspecialchars($group['image_url']) ?>" alt="<?= htmlspecialchars($group['name']) ?> cover image">
            <?php else: ?>
                <div class="civic-group-cover-placeholder" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            <?php endif; ?>
            <span class="civic-group-cover-badge">Local Hub</span>
        </div>
        <div class="civic-group-info civic-group-header-content">
            <div>
                <h1><?= htmlspecialchars($group['name']) ?></h1>
                <p class="civic-group-info-meta">
                    <?= count($members) ?> Members · Managed by <?= htmlspecialchars($group['owner_name'] ?? 'Organizer') ?>
                </p>
            </div>

            <!-- Action Buttons -->
            <div>
                <?php if ($isMember): ?>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form civic-inline-form">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button type="submit" class="civic-group-action-btn civic-group-leave-btn">
                            Leave Hub
                        </button>
                    </form>
                <?php else: ?>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/join" method="POST" class="ajax-form civic-inline-form">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button type="submit" class="civic-group-action-btn civic-group-join-btn">
                            Join Hub
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="civic-group-content-grid">

        <?php
        $hasSubHubs = !empty($subGroups);
        $defaultTab = $hasSubHubs ? 'tab-subhubs' : 'tab-feed';
        ?>

        <!-- Left Column: Tabs & Content -->
        <main>
            <!-- Tabs -->
            <div class="civic-tab-nav" role="tablist" aria-label="Hub content sections">
                <?php if ($hasSubHubs): ?>
                    <button type="button" role="tab" id="btn-subhubs" class="civic-tab-link <?= $defaultTab === 'tab-subhubs' ? 'active' : '' ?>" aria-selected="<?= $defaultTab === 'tab-subhubs' ? 'true' : 'false' ?>" aria-controls="tab-subhubs" onclick="switchTab(event, 'tab-subhubs')">Sub-Hubs</button>
                <?php endif; ?>
                <button type="button" role="tab" id="btn-feed" class="civic-tab-link <?= $defaultTab === 'tab-feed' ? 'active' : '' ?>" aria-selected="<?= $defaultTab === 'tab-feed' ? 'true' : 'false' ?>" aria-controls="tab-feed" onclick="switchTab(event, 'tab-feed')">Activity</button>
                <button type="button" role="tab" id="btn-about" class="civic-tab-link" aria-selected="false" aria-controls="tab-about" onclick="switchTab(event, 'tab-about')">About</button>
                <button type="button" role="tab" id="btn-members" class="civic-tab-link" aria-selected="false" aria-controls="tab-members" onclick="switchTab(event, 'tab-members')">Members (<?= count($members) ?>)</button>
            </div>

            <!-- Tab: Sub-Hubs -->
            <?php if ($hasSubHubs): ?>
                <div id="tab-subhubs" class="civic-tab-pane <?= $defaultTab === 'tab-subhubs' ? 'active' : '' ?>" role="tabpanel" aria-labelledby="btn-subhubs">
                    <div class="civic-subhubs-grid">
                        <?php foreach ($subGroups as $sub): ?>
                            <?php
                            $sName = htmlspecialchars($sub['name']);
                            $sImg = !empty($sub['image_url']) ? $sub['image_url'] : '';
                            ?>
                            <div class="civic-subhub-card">
                                <!-- Image Section -->
                                <div class="civic-subhub-image">
                                    <?php if ($sImg): ?>
                                        <img src="<?= $sImg ?>" alt="<?= $sName ?>" class="civic-subhub-avatar">
                                    <?php else: ?>
                                        <!-- SVG Placeholder -->
                                        <div class="civic-subhub-placeholder" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Content -->
                                <div class="civic-subhub-content">
                                    <h3 class="civic-subhub-title">
                                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $sub['id'] ?>">
                                            <?= $sName ?>
                                        </a>
                                    </h3>
                                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $sub['id'] ?>" class="civic-subhub-btn" aria-label="Visit <?= $sName ?> hub">Visit Hub</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tab: Activity (Placeholder for Feed) -->
            <div id="tab-feed" class="civic-tab-pane <?= $defaultTab === 'tab-feed' ? 'active' : '' ?>" role="tabpanel" aria-labelledby="btn-feed">
                <div class="civic-group-empty-state">
                    <h3>Recent Activity</h3>
                    <p>There is no recent activity in this hub.</p>
                </div>
            </div>

            <!-- Tab: About -->
            <div id="tab-about" class="civic-tab-pane" role="tabpanel" aria-labelledby="btn-about">
                <div class="civic-group-about-card">
                    <h3>Running this hub</h3>
                    <p><?= nl2br(htmlspecialchars($group['description'])) ?></p>
                </div>
            </div>

            <!-- Tab: Members -->
            <div id="tab-members" class="civic-tab-pane" role="tabpanel" aria-labelledby="btn-members">
                <div class="civic-members-grid">
                    <?php if (empty($members)): ?>
                        <p>No members yet.</p>
                    <?php else: ?>
                        <?php foreach ($members as $mem): ?>
                            <div class="civic-member-card">
                                <?php if (!empty($mem['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($mem['avatar_url']) ?>" alt="<?= htmlspecialchars($mem['name']) ?>" class="civic-member-avatar">
                                <?php else: ?>
                                    <!-- SVG Fallback -->
                                    <div class="civic-member-placeholder" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <h4 class="civic-member-name">
                                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $mem['id'] ?>"><?= htmlspecialchars($mem['name']) ?></a>
                                </h4>
                                <span class="civic-member-location"><?= htmlspecialchars($mem['location'] ?? 'Member') ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Right Column: Sidebar -->
        <aside>
            <div class="civic-group-sidebar-card">
                <h4>Hub Manager</h4>
                <div class="civic-group-manager">
                    <!-- Manager Avatar Logic could go here -->
                    <div>
                        <span class="civic-group-manager-name"><?= htmlspecialchars($group['owner_name'] ?? 'Organizer') ?></span>
                        <span class="civic-group-manager-role">Organizer</span>
                    </div>
                </div>
                <button type="button" class="civic-group-contact-btn" aria-label="Contact hub manager (coming soon)" disabled>Contact (Coming Soon)</button>
            </div>
        </aside>

    </div>

</div>

<script>
    function switchTab(e, tabId) {
        e.preventDefault();

        // Hide all tabs and update ARIA
        document.querySelectorAll('.civic-tab-pane').forEach(function(el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.civic-tab-link').forEach(function(el) {
            el.classList.remove('active');
            el.setAttribute('aria-selected', 'false');
        });

        // Show target and update ARIA
        document.getElementById(tabId).classList.add('active');
        e.currentTarget.classList.add('active');
        e.currentTarget.setAttribute('aria-selected', 'true');
    }

    // Add keyboard navigation for tabs
    document.addEventListener('DOMContentLoaded', function() {
        var tabList = document.querySelector('.civic-tab-nav');
        if (tabList) {
            tabList.addEventListener('keydown', function(e) {
                var tabs = Array.from(tabList.querySelectorAll('.civic-tab-link'));
                var currentIndex = tabs.indexOf(document.activeElement);

                if (currentIndex === -1) return;

                var nextIndex;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    nextIndex = (currentIndex + 1) % tabs.length;
                    tabs[nextIndex].focus();
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                    tabs[nextIndex].focus();
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    tabs[0].focus();
                } else if (e.key === 'End') {
                    e.preventDefault();
                    tabs[tabs.length - 1].focus();
                }
            });
        }
    });
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>