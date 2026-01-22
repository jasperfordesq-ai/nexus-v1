// Configuration
const orgMembers = <?= json_encode($members) ?>;
const basePath = '<?= \Nexus\Core\TenantContext::getBasePath() ?>';
const orgId = <?= $org['id'] ?>;
const userBalance = <?= $user['balance'] ?>;
const orgBalance = <?= $summary['balance'] ?>;
const monthlyStats = <?= !empty($monthlyStats) ? json_encode($monthlyStats) : '[]' ?>;

// Toggle recipient select
function toggleRecipientSelect(select) {
    const group = document.getElementById('memberSelectGroup');
    const recipientId = document.getElementById('requestRecipientId');

    if (select.value === 'other') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        recipientId.value = '0';
        clearMemberSelection();
    }
}

// Member search autocomplete for request form
const memberSearch = document.getElementById('memberSearch');
const memberResults = document.getElementById('memberResults');

if (memberSearch) {
    memberSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (query.length < 1) {
            memberResults.classList.remove('show');
            return;
        }

        const filtered = orgMembers.filter(m =>
            (m.display_name || '').toLowerCase().includes(query) ||
            (m.email || '').toLowerCase().includes(query)
        );

        if (filtered.length > 0) {
            memberResults.innerHTML = filtered.map(m => `
                <div class="org-member-result" onclick="selectMember(${m.user_id}, '${escapeHtml(m.display_name)}', '${m.role}', '${escapeHtml(m.avatar_url || '')}')">
                    <div class="org-member-avatar">
                        ${m.avatar_url ? `<img src="${m.avatar_url}" alt="" loading="lazy">` : (m.display_name || '?')[0].toUpperCase()}
                    </div>
                    <div class="org-member-info">
                        <div class="org-member-name">${escapeHtml(m.display_name)}</div>
                        <div class="org-member-role">${m.role}</div>
                    </div>
                </div>
            `).join('');
            memberResults.classList.add('show');
        } else {
            memberResults.innerHTML = '<div style="padding: 12px; text-align: center; color: #9ca3af;">No members found</div>';
            memberResults.classList.add('show');
        }
    });
}

function selectMember(id, name, role, avatar) {
    document.getElementById('requestRecipientId').value = id;
    document.getElementById('selectedMemberName').textContent = name;
    document.getElementById('selectedMemberRole').textContent = role;
    document.getElementById('selectedMemberAvatar').innerHTML = avatar
        ? `<img src="${avatar}" alt="" loading="lazy">`
        : name[0].toUpperCase();

    document.getElementById('selectedMember').classList.add('show');
    document.getElementById('memberSearchWrapper').style.display = 'none';
    memberResults.classList.remove('show');
}

function clearMemberSelection() {
    document.getElementById('requestRecipientId').value = '';
    document.getElementById('selectedMember').classList.remove('show');
    document.getElementById('memberSearchWrapper').style.display = 'block';
    document.getElementById('memberSearch').value = '';
}

// Direct transfer member search (Admin)
const directSearch = document.getElementById('directMemberSearch');
const directResults = document.getElementById('directMemberResults');

if (directSearch) {
    directSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (query.length < 1) {
            directResults.classList.remove('show');
            return;
        }

        const filtered = orgMembers.filter(m =>
            (m.display_name || '').toLowerCase().includes(query) ||
            (m.email || '').toLowerCase().includes(query)
        );

        if (filtered.length > 0) {
            directResults.innerHTML = filtered.map(m => `
                <div class="org-member-result" onclick="selectDirectMember(${m.user_id}, '${escapeHtml(m.display_name)}', '${m.role}', '${escapeHtml(m.avatar_url || '')}')">
                    <div class="org-member-avatar">
                        ${m.avatar_url ? `<img src="${m.avatar_url}" alt="" loading="lazy">` : (m.display_name || '?')[0].toUpperCase()}
                    </div>
                    <div class="org-member-info">
                        <div class="org-member-name">${escapeHtml(m.display_name)}</div>
                        <div class="org-member-role">${m.role}</div>
                    </div>
                </div>
            `).join('');
            directResults.classList.add('show');
        } else {
            directResults.innerHTML = '<div style="padding: 12px; text-align: center; color: #9ca3af;">No members found</div>';
            directResults.classList.add('show');
        }
    });
}

function selectDirectMember(id, name, role, avatar) {
    document.getElementById('directRecipientId').value = id;
    document.getElementById('directSelectedName').textContent = name;
    document.getElementById('directSelectedRole').textContent = role;
    document.getElementById('directSelectedAvatar').innerHTML = avatar
        ? `<img src="${avatar}" alt="" loading="lazy">`
        : name[0].toUpperCase();

    document.getElementById('directSelectedMember').classList.add('show');
    document.getElementById('directSearchWrapper').style.display = 'none';
    directResults.classList.remove('show');
}

function clearDirectSelection() {
    document.getElementById('directRecipientId').value = '';
    document.getElementById('directSelectedMember').classList.remove('show');
    document.getElementById('directSearchWrapper').style.display = 'block';
    document.getElementById('directMemberSearch').value = '';
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.org-member-search-wrapper')) {
        memberResults?.classList.remove('show');
        directResults?.classList.remove('show');
    }
});

// ==============================================
// FORM VALIDATION & SUBMISSION
// ==============================================

// Deposit form validation
function validateAndSubmitDeposit(event) {
    event.preventDefault();
    const form = document.getElementById('depositForm');
    const amountInput = document.getElementById('depositAmount');
    const amount = parseFloat(amountInput.value);
    const btn = document.getElementById('depositBtn');

    // Validate amount
    let isValid = true;
    if (isNaN(amount) || amount < 0.5) {
        OrgUI.validation.setInvalid(document.getElementById('depositAmountGroup'), 'Minimum amount is 0.5 HRS');
        isValid = false;
    } else if (amount > userBalance) {
        OrgUI.validation.setInvalid(document.getElementById('depositAmountGroup'), `You only have ${userBalance.toFixed(1)} HRS available`);
        isValid = false;
    } else {
        OrgUI.validation.setValid(document.getElementById('depositAmountGroup'));
    }

    if (!isValid) return false;

    // Show loading state
    OrgUI.loading.setButton(btn, true);

    // Submit form
    form.submit();
    return true;
}

// Request form validation (add to form submit)
document.getElementById('requestTransferForm')?.addEventListener('submit', function(event) {
    event.preventDefault();
    const form = this;
    const amountInput = document.getElementById('requestAmount');
    const descInput = document.getElementById('requestDesc');
    const amount = parseFloat(amountInput.value);
    const desc = descInput.value.trim();
    const btn = document.getElementById('requestBtn');

    let isValid = true;

    // Validate amount
    if (isNaN(amount) || amount < 0.5) {
        OrgUI.validation.setInvalid(document.getElementById('requestAmountGroup'), 'Minimum amount is 0.5 HRS');
        isValid = false;
    } else if (amount > orgBalance) {
        OrgUI.validation.setInvalid(document.getElementById('requestAmountGroup'), `Organization only has ${orgBalance.toFixed(1)} HRS available`);
        isValid = false;
    } else {
        OrgUI.validation.setValid(document.getElementById('requestAmountGroup'));
    }

    // Validate description
    if (desc.length < 10) {
        OrgUI.validation.setInvalid(document.getElementById('requestDescGroup'), 'Please provide a reason (at least 10 characters)');
        isValid = false;
    } else {
        OrgUI.validation.setValid(document.getElementById('requestDescGroup'));
    }

    // Check recipient if "other" is selected
    const recipientType = form.querySelector('select[name="recipient_type"]')?.value;
    if (recipientType === 'other' && !document.getElementById('requestRecipientId').value) {
        OrgUI.toast.error('Missing recipient', 'Please select a member to receive the transfer');
        isValid = false;
    }

    if (!isValid) return false;

    // Show loading state
    OrgUI.loading.setButton(btn, true);

    // Submit form
    form.submit();
    return true;
});

// ==============================================
// CONFIRMATION MODALS FOR APPROVE/REJECT
// ==============================================

async function confirmApprove(requestId, requesterName, amount) {
    const confirmed = await OrgUI.modal.confirm(
        `Approve transfer of ${amount.toFixed(1)} HRS requested by ${requesterName}?`,
        'Approve Transfer Request'
    );

    if (confirmed) {
        const form = document.getElementById('approveForm');
        form.action = `${basePath}/organizations/${orgId}/wallet/approve/${requestId}`;
        form.submit();
    }
}

async function confirmReject(requestId, requesterName) {
    const reason = await OrgUI.modal.prompt(
        `Please provide a reason for rejecting ${requesterName}'s request (optional):`,
        'Reject Transfer Request',
        'e.g. Insufficient documentation'
    );

    if (reason !== null) {
        const form = document.getElementById('rejectForm');
        form.action = `${basePath}/organizations/${orgId}/wallet/reject/${requestId}`;
        document.getElementById('rejectReasonInput').value = reason;
        form.submit();
    }
}

// ==============================================
// BULK SELECTION FOR REQUESTS
// ==============================================

// Initialize bulk selection
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('pendingRequestsContainer');
    if (container) {
        OrgUI.bulkSelect.init('#pendingRequestsContainer', {
            onSelectionChange: (selected) => {
                const bar = document.getElementById('bulkActionBar');
                const count = container.querySelector('.org-select-count');
                if (selected.length > 0) {
                    bar.classList.add('show');
                    count.textContent = `${selected.length} selected`;
                } else {
                    bar.classList.remove('show');
                }
            }
        });
    }
});

async function bulkApprove() {
    const selected = OrgUI.bulkSelect.getSelected();
    if (selected.length === 0) {
        OrgUI.toast.warning('No selection', 'Please select at least one request');
        return;
    }

    const confirmed = await OrgUI.modal.confirm(
        `Approve ${selected.length} transfer request(s)?`,
        'Bulk Approve'
    );

    if (confirmed) {
        // Submit bulk approve
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/organizations/${orgId}/wallet/bulk-approve`;
        form.innerHTML = `<input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">`;
        selected.forEach(id => {
            form.innerHTML += `<input type="hidden" name="request_ids[]" value="${id}">`;
        });
        document.body.appendChild(form);
        form.submit();
    }
}

async function bulkReject() {
    const selected = OrgUI.bulkSelect.getSelected();
    if (selected.length === 0) {
        OrgUI.toast.warning('No selection', 'Please select at least one request');
        return;
    }

    const reason = await OrgUI.modal.prompt(
        `Reject ${selected.length} request(s)? Enter a reason (optional):`,
        'Bulk Reject',
        'e.g. Budget constraints'
    );

    if (reason !== null) {
        // Submit bulk reject
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/organizations/${orgId}/wallet/bulk-reject`;
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">
            <input type="hidden" name="reason" value="${escapeHtml(reason)}">
        `;
        selected.forEach(id => {
            form.innerHTML += `<input type="hidden" name="request_ids[]" value="${id}">`;
        });
        document.body.appendChild(form);
        form.submit();
    }
}

// ==============================================
// LIVE UPDATES (Polling)
// ==============================================

document.addEventListener('DOMContentLoaded', function() {
    const indicator = document.getElementById('liveIndicator');
    const balanceValue = document.getElementById('balanceValue');

    if (indicator && balanceValue) {
        // Start polling for balance updates every 30 seconds
        OrgUI.liveUpdate.start({
            url: `${basePath}/api/organizations/${orgId}/wallet/balance`,
            interval: 30000,
            indicator: indicator,
            onUpdate: (data) => {
                if (data && data.balance !== undefined) {
                    const newBalance = parseFloat(data.balance);
                    const currentBalance = parseFloat(balanceValue.textContent.replace(/,/g, ''));
                    if (newBalance !== currentBalance) {
                        balanceValue.textContent = newBalance.toFixed(1);
                        OrgUI.toast.info('Balance updated', `New balance: ${newBalance.toFixed(1)} HRS`);
                    }
                }
            }
        });
    }
});

// ==============================================
// SVG MONTHLY CHART
// ==============================================

function renderMonthlyChart() {
    if (!monthlyStats || monthlyStats.length === 0) return;

    const svg = document.getElementById('monthlyChart');
    if (!svg) return;

    const width = 400;
    const height = 180;
    const padding = { top: 20, right: 20, bottom: 30, left: 50 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;

    // Find max value for scaling
    const maxReceived = Math.max(...monthlyStats.map(d => parseFloat(d.received) || 0));
    const maxPaid = Math.max(...monthlyStats.map(d => parseFloat(d.paid_out) || 0));
    const maxValue = Math.max(maxReceived, maxPaid, 10);

    // Scale functions
    const xScale = (i) => padding.left + (i / (monthlyStats.length - 1 || 1)) * chartWidth;
    const yScale = (v) => padding.top + chartHeight - (v / maxValue) * chartHeight;

    // Generate path for received line
    const receivedPath = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.received) || 0);
        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');

    // Generate path for paid out line
    const paidPath = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.paid_out) || 0);
        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');

    // Generate area fill for received
    const receivedArea = receivedPath +
        ` L ${xScale(monthlyStats.length - 1)} ${padding.top + chartHeight}` +
        ` L ${padding.left} ${padding.top + chartHeight} Z`;

    // Generate month labels
    const labels = monthlyStats.map((d, i) => {
        const x = xScale(i);
        return `<text x="${x}" y="${height - 5}" text-anchor="middle" fill="#9ca3af" font-size="10">${d.month || ''}</text>`;
    }).join('');

    // Generate Y-axis labels
    const yLabels = [0, maxValue / 2, maxValue].map(v => {
        const y = yScale(v);
        return `<text x="${padding.left - 10}" y="${y + 4}" text-anchor="end" fill="#9ca3af" font-size="10">${v.toFixed(0)}</text>`;
    }).join('');

    // Grid lines
    const gridLines = [0, maxValue / 2, maxValue].map(v => {
        const y = yScale(v);
        return `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="rgba(156, 163, 175, 0.2)" stroke-dasharray="4"/>`;
    }).join('');

    // Generate dots for received
    const receivedDots = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.received) || 0);
        return `<circle cx="${x}" cy="${y}" r="4" fill="#10b981"/>`;
    }).join('');

    // Generate dots for paid out
    const paidDots = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.paid_out) || 0);
        return `<circle cx="${x}" cy="${y}" r="4" fill="#ef4444"/>`;
    }).join('');

    svg.innerHTML = `
        ${gridLines}
        ${yLabels}
        ${labels}
        <path d="${receivedArea}" fill="rgba(16, 185, 129, 0.1)"/>
        <path d="${receivedPath}" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="${paidPath}" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        ${receivedDots}
        ${paidDots}
    `;
}

// Render chart on load
document.addEventListener('DOMContentLoaded', renderMonthlyChart);

// ==============================================
// MEMBER SEARCH AUTOCOMPLETE
// ==============================================

// Escape HTML helper
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
