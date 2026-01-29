/**
 * Volunteering: My Applications - Modal Interactions
 * WCAG 2.1 AA Compliant
 */

function openLogModal(orgId, oppId, orgName, oppTitle) {
    var modal = document.getElementById('logHoursModal');
    document.getElementById('log_org_id').value = orgId;
    document.getElementById('log_opp_id').value = oppId;
    document.getElementById('log_org_name').textContent = orgName;
    document.getElementById('log_opp_title').textContent = oppTitle;
    modal.classList.remove('govuk-!-display-none');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('js-overflow-hidden');
}

function closeLogModal() {
    var modal = document.getElementById('logHoursModal');
    modal.classList.add('govuk-!-display-none');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('js-overflow-hidden');
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLogModal();
    }
});
