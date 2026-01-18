<?php
$hTitle = "Community Members";
$hSubtitle = "Connect with timebankers in your community";
$hType = "Directory";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Community Members']
];
require __DIR__ . '/../../layouts/civicone/partials/breadcrumb.php';
?>

<!-- Search Bar - MadeOpen Style -->
<div class="civic-search-bar">
    <div class="civic-search-row">
        <div class="civic-search-input-wrapper">
            <span class="civic-search-icon dashicons dashicons-search"></span>
            <input type="text" id="civic-search" class="civic-search-input" placeholder="Search members by name or location...">
            <div id="civic-spinner" style="display: none; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; border: 2px solid #E5E7EB; border-top-color: var(--civic-brand); border-radius: 50%; animation: cv-spin 0.8s linear infinite;"></div>
        </div>
    </div>
</div>

<!-- Results Count -->
<p class="civic-results-count" id="civic-count">
    Showing <strong><?= count($members) ?></strong> of <strong><?= $total_members ?? count($members) ?></strong> members
</p>

<!-- Members Grid -->
<div id="civic-grid" class="civic-members-grid">
    <?php foreach ($members as $mem): ?>
        <?= render_civic_member_card($mem) ?>
    <?php endforeach; ?>
</div>

<?php if (empty($members)): ?>
    <div class="civic-empty-state" id="civic-empty">
        <div class="civic-empty-state-icon">
            <span class="dashicons dashicons-groups" style="font-size: 48px;"></span>
        </div>
        <h3 class="civic-empty-state-title">No members found</h3>
        <p class="civic-empty-state-text">Try adjusting your search or check back later.</p>
    </div>
<?php else: ?>
    <div class="civic-empty-state" id="civic-empty" style="display: none;">
        <div class="civic-empty-state-icon">
            <span class="dashicons dashicons-groups" style="font-size: 48px;"></span>
        </div>
        <h3 class="civic-empty-state-title">No members found</h3>
        <p class="civic-empty-state-text">Try adjusting your search.</p>
    </div>
<?php endif; ?>

    <!-- Pagination -->
    <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
        <nav class="civic-pagination" aria-label="Member list pagination">
            <?php
            $current = $pagination['current_page'];
            $total = $pagination['total_pages'];
            $base = $pagination['base_path'];
            $range = 2;
            ?>

            <?php if ($current > 1): ?>
                <a href="<?= $base ?>?page=<?= $current - 1 ?>" class="civic-pagination-btn civic-pagination-prev" aria-label="Go to previous page">
                    <span aria-hidden="true">&larr;</span> Prev
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total; $i++): ?>
                <?php if ($i == 1 || $i == $total || ($i >= $current - $range && $i <= $current + $range)): ?>
                    <?php if ($i == $current): ?>
                        <span class="civic-pagination-btn civic-pagination-current" aria-current="page">
                            <?= $i ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= $base ?>?page=<?= $i ?>" class="civic-pagination-btn" aria-label="Go to page <?= $i ?>">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php elseif ($i == $current - $range - 1 || $i == $current + $range + 1): ?>
                    <span class="civic-pagination-ellipsis" aria-hidden="true">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($current < $total): ?>
                <a href="<?= $base ?>?page=<?= $current + 1 ?>" class="civic-pagination-btn civic-pagination-next" aria-label="Go to next page">
                    Next <span aria-hidden="true">&rarr;</span>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php
function render_civic_member_card($mem)
{
    ob_start();
    $hasAvatar = !empty($mem['avatar_url']);
    $basePath = \Nexus\Core\TenantContext::getBasePath();

    // Check online status - active within 5 minutes
    $memberLastActive = $mem['last_active_at'] ?? null;
    $isMemberOnline = $memberLastActive && (strtotime($memberLastActive) > strtotime('-5 minutes'));
?>
    <article class="civic-member-card">
        <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="civic-member-card-link">
            <!-- Avatar -->
            <div class="civic-avatar-wrapper" style="position: relative;">
                <?php if ($hasAvatar): ?>
                    <img src="<?= htmlspecialchars($mem['avatar_url']) ?>" alt="" class="civic-avatar civic-avatar--lg">
                <?php else: ?>
                    <div class="civic-avatar civic-avatar--lg civic-avatar--placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                <?php endif; ?>
                <?php if ($isMemberOnline): ?>
                <span style="position:absolute;bottom:2px;right:2px;width:12px;height:12px;background:#10b981;border:2px solid white;border-radius:50%;box-shadow:0 1px 3px rgba(16,185,129,0.4);" title="Active now"></span>
                <?php endif; ?>
            </div>

            <!-- Member Info -->
            <div class="civic-member-info">
                <h3 class="civic-member-name"><?= htmlspecialchars($mem['display_name'] ?? $mem['name'] ?? $mem['username'] ?? 'Member') ?></h3>
                <?php if (!empty($mem['location'])): ?>
                    <p class="civic-member-location">
                        <span class="dashicons dashicons-location" aria-hidden="true"></span>
                        <?= htmlspecialchars($mem['location']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </a>

        <div class="civic-member-actions">
            <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="civic-btn civic-btn--outline">View Profile</a>
        </div>
    </article>
<?php
    return ob_get_clean();
}
?>

<style>
    @keyframes cv-spin {
        to {
            transform: rotate(360deg);
        }
    }

    #civic-search:focus {
        border-color: var(--civic-brand);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('civic-search');
        const grid = document.getElementById('civic-grid');
        const emptyMsg = document.getElementById('civic-empty');
        const spinner = document.getElementById('civic-spinner');
        let debounceTimer;

        searchInput.addEventListener('keyup', function(e) {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();

            spinner.style.display = 'block';

            debounceTimer = setTimeout(() => {
                fetchMembers(query);
            }, 300);
        });

        function fetchMembers(query) {
            if (query.length === 0) {
                window.location.reload();
                return;
            }

            fetch(basePath + window.location.pathname + '?q=' + encodeURIComponent(query), {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    renderGrid(data.data);
                    document.getElementById('civic-count').textContent = `Showing ${data.data.length} members`;
                    spinner.style.display = 'none';
                })
                .catch(err => {
                    console.error(err);
                    spinner.style.display = 'none';
                });
        }

        // JS Render Logic (Matches PHP style with new MadeOpen components)
        function renderGrid(members) {
            const grid = document.getElementById('civic-grid');
            grid.innerHTML = '';

            if (members.length === 0) {
                emptyMsg.style.display = 'block';
                return;
            }
            emptyMsg.style.display = 'none';

            members.forEach(member => {
                const name = escapeHtml(member.first_name + ' ' + member.last_name);
                const location = member.location ? escapeHtml(member.location) : '';

                // Check online status - active within 5 minutes
                const isOnline = member.last_active_at && (new Date(member.last_active_at) > new Date(Date.now() - 5 * 60 * 1000));
                const onlineIndicator = isOnline ? `<span style="position:absolute;bottom:2px;right:2px;width:12px;height:12px;background:#10b981;border:2px solid white;border-radius:50%;box-shadow:0 1px 3px rgba(16,185,129,0.4);" title="Active now"></span>` : '';

                // Avatar HTML
                let avatarHtml = '';
                if (member.avatar_url) {
                    avatarHtml = `<img src="${escapeHtml(member.avatar_url)}" alt="" class="civic-avatar civic-avatar--lg">`;
                } else {
                    avatarHtml = `
                        <div class="civic-avatar civic-avatar--lg civic-avatar--placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>`;
                }

                // Location HTML
                let locationHtml = '';
                if (location) {
                    locationHtml = `
                        <p class="civic-member-location">
                            <span class="dashicons dashicons-location" aria-hidden="true"></span>
                            ${location}
                        </p>`;
                }

                const card = document.createElement('article');
                card.className = 'civic-member-card';
                card.innerHTML = `
                    <a href="${basePath}/profile/${member.id}" class="civic-member-card-link">
                        <div class="civic-avatar-wrapper" style="position: relative;">
                            ${avatarHtml}
                            ${onlineIndicator}
                        </div>
                        <div class="civic-member-info">
                            <h3 class="civic-member-name">${name}</h3>
                            ${locationHtml}
                        </div>
                    </a>
                    <div class="civic-member-actions">
                        <a href="${basePath}/profile/${member.id}" class="civic-btn civic-btn--outline">View Profile</a>
                    </div>
                `;
                grid.appendChild(card);
            });
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
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>