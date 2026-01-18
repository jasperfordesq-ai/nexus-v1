/**
 * Nexus UI - Lightweight AJAX Handler
 * 
 * Usage:
 * Add class="ajax-form" to any <form>
 * Optional: data-success-text="Done!" (Changes button text on success)
 * Optional: data-reload="true" (Reloads page after success)
 */

document.body.addEventListener('submit', function (e) {
    if (e.target.matches('form.ajax-form')) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn ? btn.innerHTML : '';

        // Lock UI
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '...';
        }

        // Prepare Data
        const formData = new FormData(form);
        formData.append('ajax', '1'); // Tell backend it's AJAX

        // Fetch
        fetch(form.action, {
            method: form.method || 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    // CRITICAL: Capture the text if it's not JSON (e.g. PHP Fatal Error)
                    return response.text().then(text => {
                        throw new Error("Server response was not JSON: " + text.substring(0, 500));
                    });
                }
            })
            .then(data => {
                if (data.status === 'success' || data.success) {
                    // Success State
                    if (btn) {
                        const successText = form.getAttribute('data-success-text') || '✓ Done';
                        btn.innerHTML = successText;
                        btn.classList.add('btn-success');
                    }

                    if (form.getAttribute('data-reload') === 'true') {
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } else {
                    // Logic Error (e.g. Permission denied)
                    alert(data.message || 'Action failed.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                }
            })
            .catch(err => {
                // Network/Server Error
                console.error(err);
                alert('Error: ' + (err.message || err));
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
    }
});
