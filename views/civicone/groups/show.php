<?php
// CivicOne Group Details View
$pageTitle = htmlspecialchars($group['name']) . ' - Nexus TimeBank';
require __DIR__ . '/../../layouts/civicone/header.php';

// Helper for avatars
function get_avatar_civic($url)
{
    return $url ?: ''; // Empty returns trigger SVG fallback
}
?>

<style>
    /* Tab System Styling */
    .civic-tab-nav {
        display: flex;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 30px;
        gap: 20px;
    }

    .civic-tab-link {
        padding: 12px 16px;
        font-weight: 600;
        color: var(--civic-text-muted, #6b7280);
        text-decoration: none;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        cursor: pointer;
        font-size: 1rem;
    }

    .civic-tab-link:hover {
        color: var(--civic-text-main, #111827);
    }

    .civic-tab-link.active {
        color: var(--civic-brand, #00796B);
        border-bottom-color: var(--civic-brand, #00796B);
    }

    .civic-tab-pane {
        display: none;
        animation: fadeIn 0.3s ease-in-out;
    }

    .civic-tab-pane.active {
        display: block;
    }

    /* Hero / Header Styling */
    .civic-group-header {
        background: #ffffff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        border: 1px solid #e5e7eb;
    }

    .civic-group-cover {
        height: 200px;
        background: linear-gradient(135deg, var(--civic-brand) 0%, #004d40 100%);
        position: relative;
    }

    .civic-group-info {
        padding: 20px 30px;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile Responsive Styles */
    .civic-group-content-grid {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 30px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .civic-group-content-grid {
            grid-template-columns: 1fr;
        }
        .civic-group-content-grid aside {
            order: -1;
        }
    }

    @media (max-width: 600px) {
        .civic-tab-nav {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            gap: 0;
        }
        .civic-tab-nav::-webkit-scrollbar {
            display: none;
        }
        .civic-tab-link {
            white-space: nowrap;
            padding: 12px 14px;
            flex-shrink: 0;
        }
        .civic-group-info {
            padding: 16px !important;
        }
        .civic-group-info > div:first-child h1 {
            font-size: 1.5rem !important;
        }
        .civic-group-header-content {
            flex-direction: column;
            align-items: stretch !important;
            gap: 16px !important;
        }
        .civic-group-header-content > div:last-child {
            width: 100%;
        }
        .civic-group-header-content .civic-btn {
            width: 100%;
            text-align: center;
            justify-content: center;
        }
    }
</style>

<div class="civic-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">

    <!-- Group Header Card -->
    <div class="civic-group-header">
        <div class="civic-group-cover">
            <?php if (!empty($group['image_url'])): ?>
                <img src="<?= htmlspecialchars($group['image_url']) ?>" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;">
            <?php else: ?>
                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            <?php endif; ?>
            <span style="position: absolute; top: 20px; left: 20px; background: rgba(0,0,0,0.6); color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">Local Hub</span>
        </div>
        <div class="civic-group-info civic-group-header-content" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin: 0; font-size: 2rem; color: var(--civic-text-main, #111827);"><?= htmlspecialchars($group['name']) ?></h1>
                <p style="margin: 5px 0 0 0; color: var(--civic-text-muted, #6b7280);">
                    <?= count($members) ?> Members · Managed by <?= htmlspecialchars($group['owner_name'] ?? 'Organizer') ?>
                </p>
            </div>

            <!-- Action Buttons -->
            <div>
                <?php if ($isMember): ?>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form" style="display:inline;">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button type="submit" class="civic-btn" style="background: transparent; border: 1px solid #ef4444; color: #ef4444; border-radius: 30px; padding: 8px 20px;">
                            Leave Hub
                        </button>
                    </form>
                <?php else: ?>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/join" method="POST" class="ajax-form" style="display:inline;">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button type="submit" class="civic-btn" style="border-radius: 30px; padding: 10px 30px;">
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
            <div class="civic-tab-nav">
                <?php if ($hasSubHubs): ?>
                    <a href="#tab-subhubs" class="civic-tab-link <?= $defaultTab === 'tab-subhubs' ? 'active' : '' ?>" onclick="switchTab(event, 'tab-subhubs')">Sub-Hubs</a>
                <?php endif; ?>
                <a href="#tab-feed" class="civic-tab-link <?= $defaultTab === 'tab-feed' ? 'active' : '' ?>" onclick="switchTab(event, 'tab-feed')">Activity</a>
                <a href="#tab-about" class="civic-tab-link" onclick="switchTab(event, 'tab-about')">About</a>
                <a href="#tab-members" class="civic-tab-link" onclick="switchTab(event, 'tab-members')">Members (<?= count($members) ?>)</a>
            </div>

            <!-- Tab: Sub-Hubs -->
            <?php if ($hasSubHubs): ?>
                <div id="tab-subhubs" class="civic-tab-pane <?= $defaultTab === 'tab-subhubs' ? 'active' : '' ?>">
                    <div class="civic-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                        <?php foreach ($subGroups as $sub): ?>
                            <?php
                            $sName = htmlspecialchars($sub['name']);
                            $sImg = !empty($sub['image_url']) ? $sub['image_url'] : '';
                            ?>
                            <div class="civic-card" style="
                            display: flex; 
                            flex-direction: column; 
                            align-items: center; 
                            text-align: center; 
                            padding: 0; 
                            background: var(--civic-bg-card, #f7f7f7); 
                            border: none; 
                            border-radius: 6px; 
                            overflow: hidden;
                            margin-bottom: 0;
                            box-shadow: none;
                        ">
                                <!-- Image Section -->
                                <div style="padding: 20px 20px 10px 20px; width: 100%; display: flex; justify-content: center;">
                                    <?php if ($sImg): ?>
                                        <img src="<?= $sImg ?>" alt="<?= $sName ?>" style="
                                        width: 100px; 
                                        height: 100px; 
                                        border-radius: 50%; 
                                        object-fit: cover; 
                                        border: 4px solid var(--civic-bg-page, #ffffff);
                                        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                                    ">
                                    <?php else: ?>
                                        <!-- SVG Placeholder -->
                                        <div style="
                                        width: 100px; 
                                        height: 100px; 
                                        border-radius: 50%; 
                                        border: 4px solid var(--civic-bg-page, #ffffff);
                                        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                                        background: #e5e7eb;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        color: #9ca3af;
                                    ">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Content -->
                                <div style="padding: 0 20px 25px 20px; width: 100%;">
                                    <h3 style="
                                    margin: 0 0 5px 0; 
                                    font-size: 1rem; 
                                    font-weight: 600; 
                                    color: var(--civic-brand, #00796B);
                                ">
                                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $sub['id'] ?>" style="text-decoration: none; color: inherit;">
                                            <?= $sName ?>
                                        </a>
                                    </h3>
                                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $sub['id'] ?>" class="civic-btn" style="
                                    display: inline-block;
                                    margin-top: 10px;
                                    padding: 6px 16px;
                                    background: transparent;
                                    color: var(--civic-brand, #00796B);
                                    border: 1px solid var(--civic-brand, #00796B);
                                    border-radius: 30px;
                                    font-weight: 600;
                                    font-size: 0.8rem;
                                    text-decoration: none;
                                    transition: all 0.2s;
                                ">Visit Hub</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tab: Activity (Placeholder for Feed) -->
            <div id="tab-feed" class="civic-tab-pane <?= $defaultTab === 'tab-feed' ? 'active' : '' ?>">
                <div class="civic-card" style="padding: 40px; text-align: center; background: #f9fafb; border: 1px dashed #d1d5db; margin-bottom: 0;">
                    <h3 style="color: var(--civic-text-muted);">Recent Activity</h3>
                    <p>There is no recent activity in this hub.</p>
                </div>
            </div>

            <!-- Tab: About -->
            <div id="tab-about" class="civic-tab-pane">
                <div class="civic-card" style="background: var(--civic-bg-card, #f7f7f7); border: none; padding: 30px; border-radius: 8px; margin-bottom: 0;">
                    <h3 style="margin-top: 0;">Running this hub</h3>
                    <p style="line-height: 1.6; color: var(--civic-text-main);"><?= nl2br(htmlspecialchars($group['description'])) ?></p>
                </div>
            </div>

            <!-- Tab: Members -->
            <div id="tab-members" class="civic-tab-pane">
                <div class="civic-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px;">
                    <?php if (empty($members)): ?>
                        <p>No members yet.</p>
                    <?php else: ?>
                        <?php foreach ($members as $mem): ?>
                            <div class="civic-card" style="text-align: center; padding: 20px; background: #f7f7f7; border: none; border-radius: 6px; box-shadow:none; margin-bottom:0;">
                                <?php if (!empty($mem['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($mem['avatar_url']) ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <?php else: ?>
                                    <!-- SVG Fallback -->
                                    <div style="width: 80px; height: 80px; margin: 0 auto; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <h4 style="margin: 10px 0 5px 0; font-size: 0.95rem;">
                                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $mem['id'] ?>" style="text-decoration: none; color: var(--civic-text-main);"><?= htmlspecialchars($mem['name']) ?></a>
                                </h4>
                                <span style="font-size: 0.8rem; color: var(--civic-text-muted);"><?= htmlspecialchars($mem['location'] ?? 'Member') ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Right Column: Sidebar -->
        <aside>
            <div class="civic-card" style="background: white; border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px; margin-bottom:0;">
                <h4 style="margin-top: 0;">Hub Manager</h4>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <!-- Manager Avatar Logic could go here -->
                    <div>
                        <strong style="display: block;"><?= htmlspecialchars($group['owner_name'] ?? 'Organizer') ?></strong>
                        <span style="font-size: 0.85rem; color: #6b7280;">Organizer</span>
                    </div>
                </div>
                <button class="civic-btn" style="width: 100%; background: white; color: var(--civic-brand); border: 1px solid var(--civic-brand);">Contact (Coming Soon)</button>
            </div>
        </aside>

    </div>

</div>

<script>
    function switchTab(e, tabId) {
        e.preventDefault();

        // Hide all tabs
        document.querySelectorAll('.civic-tab-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.civic-tab-link').forEach(el => el.classList.remove('active'));

        // Show target
        document.getElementById(tabId).classList.add('active');
        e.currentTarget.classList.add('active');
    }
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>