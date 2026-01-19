<!-- Skeleton Loading Screen for Feed -->
<div class="skeleton-feed-container" id="skeletonFeed" aria-label="Loading content" aria-live="polite">
    <?php for ($i = 0; $i < 3; $i++): ?>
    <div class="skeleton-feed-card" style="animation-delay: <?= $i * 0.1 ?>s;">
        <div class="skeleton-feed-header">
            <div class="skeleton skeleton-avatar"></div>
            <div class="skeleton-feed-author">
                <div class="skeleton skeleton-text" style="width: 140px; height: 18px;"></div>
                <div class="skeleton skeleton-text" style="width: 90px; height: 14px;"></div>
            </div>
        </div>

        <div class="skeleton-feed-content">
            <div class="skeleton skeleton-text"></div>
            <div class="skeleton skeleton-text"></div>
            <div class="skeleton skeleton-text" style="width: 70%;"></div>
        </div>

        <div class="skeleton skeleton-card" style="height: 250px; margin-top: 12px;"></div>

        <div class="skeleton-feed-actions">
            <div class="skeleton skeleton-button" style="width: 80px;"></div>
            <div class="skeleton skeleton-button" style="width: 80px;"></div>
            <div class="skeleton skeleton-button" style="width: 80px;"></div>
        </div>
    </div>
    <?php endfor; ?>
</div>

<script>
// Auto-hide skeleton when real content loads
document.addEventListener('DOMContentLoaded', function() {
    const skeleton = document.getElementById('skeletonFeed');
    const realContent = document.querySelector('.feed-container-real, #feed-container');

    if (skeleton && realContent) {
        // Hide skeleton when content appears
        const observer = new MutationObserver(function(mutations) {
            if (realContent.children.length > 0) {
                skeleton.classList.add('hidden');
                setTimeout(() => skeleton.remove(), 300);
                observer.disconnect();
            }
        });

        observer.observe(realContent, {
            childList: true,
            subtree: true
        });

        // Fallback: hide after 3 seconds anyway
        setTimeout(() => {
            if (skeleton && !skeleton.classList.contains('hidden')) {
                skeleton.classList.add('hidden');
                setTimeout(() => skeleton.remove(), 300);
            }
        }, 3000);
    }
});
</script>
