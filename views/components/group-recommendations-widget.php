<?php
/**
 * Group Recommendations Widget
 *
 * Displays personalized "Groups You Might Like" on dashboard/groups pages
 *
 * Usage:
 * <?php include __DIR__ . '/components/group-recommendations-widget.php'; ?>
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$widgetId = $widgetId ?? 'group-recommendations-' . uniqid();
$limit = $limit ?? 6;
$showTitle = $showTitle ?? true;
?>

<div class="group-recommendations-widget" id="<?= $widgetId ?>">
    <?php if ($showTitle): ?>
    <div class="widget-header">
        <h3 class="widget-title">
            <i class="fa-solid fa-sparkles"></i>
            Groups You Might Like
        </h3>
        <a href="<?= $basePath ?>/groups" class="widget-link">
            See all groups <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <?php endif; ?>

    <div class="widget-loading" id="<?= $widgetId ?>-loading">
        <div class="loading-spinner"></div>
        <p>Finding groups for you...</p>
    </div>

    <div class="widget-content" id="<?= $widgetId ?>-content" style="display: none;">
        <!-- Recommendations will be loaded here -->
    </div>

    <div class="widget-empty" id="<?= $widgetId ?>-empty" style="display: none;">
        <div class="empty-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <p>No recommendations available yet.</p>
        <a href="<?= $basePath ?>/groups" class="btn btn-primary">
            Explore All Groups
        </a>
    </div>
</div>

<style>
.group-recommendations-widget {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}

.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f3f4f6;
}

.widget-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.widget-title i {
    color: #fbbf24;
}

.widget-link {
    color: #6366f1;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.widget-link:hover {
    text-decoration: underline;
}

.widget-loading {
    text-align: center;
    padding: 40px 20px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f4f6;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.widget-content {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.recommendation-card {
    background: #f9fafb;
    border-radius: 8px;
    padding: 16px;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
    border: 2px solid transparent;
}

.recommendation-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #6366f1;
}

.recommendation-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.recommendation-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
}

.recommendation-info {
    flex: 1;
    min-width: 0;
}

.recommendation-name {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 4px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recommendation-meta {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recommendation-meta i {
    font-size: 11px;
}

.recommendation-reason {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.recommendation-reason i {
    color: #fbbf24;
    font-size: 12px;
}

.recommendation-score {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #059669;
    font-weight: 600;
}

.recommendation-score-bar {
    flex: 1;
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    overflow: hidden;
}

.recommendation-score-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 0.6s ease;
}

.widget-empty {
    text-align: center;
    padding: 40px 20px;
}

.empty-icon {
    font-size: 48px;
    color: #d1d5db;
    margin-bottom: 16px;
}

.widget-empty p {
    color: #6b7280;
    margin-bottom: 16px;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .widget-content {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function() {
    const widgetId = '<?= $widgetId ?>';
    const limit = <?= $limit ?>;
    const basePath = '<?= $basePath ?>';

    // Load recommendations
    fetch(`${basePath}/api/recommendations/groups?limit=${limit}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById(`${widgetId}-loading`).style.display = 'none';

            if (data.success && data.recommendations && data.recommendations.length > 0) {
                renderRecommendations(data.recommendations);
                document.getElementById(`${widgetId}-content`).style.display = 'grid';
            } else {
                document.getElementById(`${widgetId}-empty`).style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Failed to load recommendations:', error);
            document.getElementById(`${widgetId}-loading`).style.display = 'none';
            document.getElementById(`${widgetId}-empty`).style.display = 'block';
        });

    function renderRecommendations(recommendations) {
        const container = document.getElementById(`${widgetId}-content`);
        container.innerHTML = '';

        recommendations.forEach(group => {
            const card = createRecommendationCard(group);
            container.appendChild(card);
        });
    }

    function createRecommendationCard(group) {
        const card = document.createElement('div');
        card.className = 'recommendation-card';
        card.onclick = () => visitGroup(group.id);

        const initials = group.name.substring(0, 2).toUpperCase();
        const score = Math.round((group.recommendation_score || 0) * 100);

        card.innerHTML = `
            <div class="recommendation-header">
                <div class="recommendation-icon">${initials}</div>
                <div class="recommendation-info">
                    <h4 class="recommendation-name">${escapeHtml(group.name)}</h4>
                    <div class="recommendation-meta">
                        <span><i class="fa-solid fa-users"></i> ${group.member_count || 0}</span>
                        ${group.type_name ? `<span><i class="fa-solid fa-tag"></i> ${escapeHtml(group.type_name)}</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="recommendation-reason">
                <i class="fa-solid fa-sparkles"></i>
                ${escapeHtml(group.recommendation_reason || 'Recommended for you')}
            </div>
            <div class="recommendation-score">
                <span>${score}% match</span>
                <div class="recommendation-score-bar">
                    <div class="recommendation-score-fill" style="width: ${score}%"></div>
                </div>
            </div>
        `;

        return card;
    }

    function visitGroup(groupId) {
        // Track click
        fetch(`${basePath}/api/recommendations/track`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({group_id: groupId, action: 'clicked'})
        }).catch(() => {});

        // Navigate
        window.location.href = `${basePath}/groups/${groupId}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
