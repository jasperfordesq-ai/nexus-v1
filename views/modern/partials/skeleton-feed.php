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

<style>
.skeleton-feed-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding: 1rem 0;
    animation: fadeInUp 0.5s cubic-bezier(0.4, 0.0, 0.2, 1);
}

.skeleton-feed-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    animation: fadeInUp 0.4s cubic-bezier(0.4, 0.0, 0.2, 1) backwards;
}

.skeleton-feed-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.skeleton-feed-author {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.skeleton-feed-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.skeleton-feed-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
}

/* Hide skeleton when content loads */
.skeleton-feed-container.hidden {
    animation: fadeOut 0.3s cubic-bezier(0.4, 0.0, 0.2, 1) forwards;
}

@keyframes fadeOut {
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}
</style>

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
