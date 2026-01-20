/**
 * Federation Settings - JavaScript
 * WCAG 2.1 AA Compliant
 */
(function() {
    'use strict';

    var config = window.federationSettingsConfig || {};
    var basePath = config.basePath || '';
    var csrfToken = config.csrfToken || '';
    var isOptedIn = config.isOptedIn || false;

    var form = document.getElementById('settingsForm');
    var saveBtn = document.getElementById('saveBtn');
    var statusToggle = document.getElementById('statusToggle');
    var toast = document.getElementById('toast');

    if (!form) return;

    // Privacy level selection
    document.querySelectorAll('.fed-privacy-option').forEach(function(option) {
        option.addEventListener('click', function() {
            document.querySelectorAll('.fed-privacy-option').forEach(function(o) {
                o.classList.remove('selected');
            });
            this.classList.add('selected');
            var input = this.querySelector('input');
            if (input) input.checked = true;
        });
    });

    // Service reach selection
    document.querySelectorAll('.fed-reach-option').forEach(function(option) {
        option.addEventListener('click', function() {
            document.querySelectorAll('.fed-reach-option').forEach(function(o) {
                o.classList.remove('selected');
            });
            this.classList.add('selected');
            var input = this.querySelector('input');
            if (input) input.checked = true;
        });
    });

    // Show toast
    function showToast(message, type) {
        type = type || 'success';
        toast.textContent = message;
        toast.className = 'fed-toast ' + type + ' visible';
        setTimeout(function() {
            toast.classList.remove('visible');
        }, 3000);
    }

    // Status toggle (enable/disable federation)
    if (statusToggle) {
        statusToggle.addEventListener('click', function() {
            var action = isOptedIn ? 'disable' : 'enable';
            var confirmMsg = isOptedIn
                ? 'Are you sure you want to disable federation? Your profile will be hidden from all partner timebanks.'
                : 'Enable federation to make your profile visible to partner timebanks?';

            if (!confirm(confirmMsg)) return;

            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Processing...';

            fetch(basePath + '/federation/settings/' + action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({})
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    if (data.redirect) {
                        setTimeout(function() { window.location.href = data.redirect; }, 1000);
                    } else {
                        setTimeout(function() { window.location.reload(); }, 1000);
                    }
                } else {
                    showToast(data.error || 'Failed to update', 'error');
                    btn.disabled = false;
                    btn.textContent = isOptedIn ? 'Disable Federation' : 'Enable Federation';
                }
            })
            .catch(function() {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.textContent = isOptedIn ? 'Disable Federation' : 'Enable Federation';
            });
        });
    }

    // Save settings
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving...';

        var privacyInput = form.querySelector('input[name="privacy_level"]:checked');
        var reachInput = form.querySelector('input[name="service_reach"]:checked');

        var formData = {
            federation_optin: isOptedIn,
            privacy_level: privacyInput ? privacyInput.value : 'discovery',
            service_reach: reachInput ? reachInput.value : 'local_only',
            appear_in_search: form.querySelector('input[name="appear_in_search"]').checked,
            profile_visible: form.querySelector('input[name="profile_visible"]').checked,
            show_location: form.querySelector('input[name="show_location"]').checked,
            show_skills: form.querySelector('input[name="show_skills"]').checked,
            messaging_enabled: form.querySelector('input[name="messaging_enabled"]').checked,
            transactions_enabled: form.querySelector('input[name="transactions_enabled"]').checked
        };

        fetch(basePath + '/federation/settings/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(formData)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.error || 'Failed to save settings', 'error');
            }
        })
        .catch(function() {
            showToast('Network error. Please try again.', 'error');
        })
        .finally(function() {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Save Settings';
        });
    });

    // Offline indicator
    var offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        window.addEventListener('online', function() { offlineBanner.classList.remove('visible'); });
        window.addEventListener('offline', function() { offlineBanner.classList.add('visible'); });
        if (!navigator.onLine) offlineBanner.classList.add('visible');
    }
})();
