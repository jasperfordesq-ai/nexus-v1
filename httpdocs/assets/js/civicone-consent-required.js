/**
 * Consent Required JavaScript
 * Form interactions
 * CivicOne Theme
 */

        try {
            const response = await fetch('<?= $basePath ?>/consent/accept', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.getElementById('csrf_token').value
                },
                body: JSON.stringify({
                    consents: consents,
                    csrf_token: document.getElementById('csrf_token').value
                })
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.error || 'Failed to save consent. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Accept and Continue';
            }
        } catch (err) {
            console.error('Consent submission error:', err);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Accept and Continue';
        }
    });
});
</script>

