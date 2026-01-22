// Volunteering: My Applications - Modal Interactions
// WCAG 2.1 AA Compliant

function openLogModal(orgId, oppId, orgName, oppTitle) {
    document.getElementById('log_org_id').value = orgId;
    document.getElementById('log_opp_id').value = oppId;
    document.getElementById('log_org_name').innerText = orgName;
    document.getElementById('log_opp_title').innerText = oppTitle;
    document.getElementById('logHoursModal').classList.add('active');
    document.getElementById('logHoursModal').setAttribute('aria-hidden', 'false');
}

function closeLogModal() {
    document.getElementById('logHoursModal').classList.remove('active');
    document.getElementById('logHoursModal').setAttribute('aria-hidden', 'true');
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLogModal();
    }
});
