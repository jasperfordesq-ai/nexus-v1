/**
 * Onboarding Flow JavaScript  
 * CivicOne Theme
 */

// Avatar preview
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar" id="avatarImg" loading="lazy">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Track if form is being submitted
let isSubmitting = false;

// Form validation
document.getElementById('onboardingForm').addEventListener('submit', function(e) {
    const bio = document.getElementById('bio').value.trim();

    if (!bio) {
        e.preventDefault();
        alert('Please add a bio to help your neighbors get to know you.');
        document.getElementById('bio').focus();
        return false;
    }

    if (bio.length < 10) {
        e.preventDefault();
        alert('Please write a bit more about yourself (at least 10 characters).');
        document.getElementById('bio').focus();
        return false;
    }

    // Mark as submitting to disable beforeunload warning
    isSubmitting = true;

    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Setting up your profile...';
});

// Prevent accidental navigation (but not when form is being submitted)
window.addEventListener('beforeunload', function (e) {
    if (isSubmitting) {
        // Allow navigation when form is being submitted
        return undefined;
    }
    e.preventDefault();
    e.returnValue = 'Are you sure you want to leave? You must complete your profile to access the platform.';
    return e.returnValue;
});

// Disable ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        alert('Please complete your profile to continue. You cannot skip this step.');
    }
});

// Disable back button navigation
history.pushState(null, '', location.href);
window.addEventListener('popstate', function() {
    history.pushState(null, '', location.href);
    alert('Please complete your profile to continue. You cannot go back.');
});
