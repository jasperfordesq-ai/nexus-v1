/**
 * Feed Show JavaScript  
 * CivicOne Theme
 */

window.SocialInteractions = window.SocialInteractions || {};
window.SocialInteractions.isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
window.SocialInteractions.config = {
    enableReactions: true,
    enableReplies: true,
    enableMentions: true,
    enableEditDelete: true,
    enableHeartBurst: true,
    enableHaptics: true,
    useCssVariables: false,
    likedColor: '#ec4899',
    unlikedColor: '#6b7280'
};
</script>
<script src="<?= $basePath ?>/assets/js/social-interactions.min.js"></script>

<script>
// Auto-load comments on page load
document.addEventListener('DOMContentLoaded', function() {
    // Expand comments section by default
    const section = document.getElementById('comments-section-<?= $socialTargetType ?>-<?= $socialTargetId ?>');
    if (section) {
        section.style.display = 'block';
        fetchComments('<?= $socialTargetType ?>', <?= $socialTargetId ?>);
    }
});

// Show Post Menu
function showPostMenu(id) {
    SocialInteractions.showToast('Post menu coming soon!');
}

// Show Likers
function showLikers(type, id) {
    SocialInteractions.showToast('Likers list coming soon!');
}
