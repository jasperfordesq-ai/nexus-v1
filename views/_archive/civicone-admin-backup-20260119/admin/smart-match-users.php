<?php
/**
 * Smart Match Users to Groups - Admin Interface
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

$tenantId = TenantContext::getId();
$basePath = TenantContext::getBasePath();

// Get total count needing matching
$totalCount = Database::query("
    SELECT COUNT(*) as total
    FROM users u
    WHERE u.tenant_id = ?
    AND u.status = 'active'
    AND (u.location IS NOT NULL OR (u.latitude IS NOT NULL AND u.longitude IS NOT NULL))
", [$tenantId])->fetch()['total'];

// Admin header configuration
$adminPageTitle = 'Smart Match Users';
$adminPageSubtitle = 'Community';
$adminPageIcon = 'fa-users-between-lines';

require __DIR__ . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-users-between-lines"></i>
            Smart Match Users to Groups
        </h1>
        <p class="admin-page-subtitle">Automatically assign users to their local hub groups using geographic matching</p>
    </div>
</div>

<div class="admin-glass-card">
    <div class="admin-card-body">
        <div class="info-box">
            <i class="fa-solid fa-info-circle"></i>
            <div>
                <strong>How it works:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li><strong>Geographic matching</strong> - Uses GPS coordinates to find nearest group (preferred, most accurate)</li>
                    <li><strong>Text matching</strong> - Fuzzy matching of location names (fallback)</li>
                    <li><strong>Parent cascade</strong> - Automatically adds users to parent groups (counties, provinces)</li>
                    <li><strong>50km threshold</strong> - Only assigns if within reasonable distance</li>
                </ul>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="totalUsers"><?= $totalCount ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="matchedCount">0</div>
                    <div class="stat-label">Successfully Matched</div>
                </div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="skippedCount">0</div>
                    <div class="stat-label">Skipped</div>
                </div>
            </div>
        </div>

        <button id="startBtn" class="admin-btn admin-btn-primary admin-btn-lg" onclick="startMatching()" style="width: 100%;">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            Start Smart Matching
        </button>

        <div class="progress-container" id="progressContainer" style="display: none;">
            <div class="progress-bar-wrapper">
                <div class="progress-bar" id="progressBar">
                    <span id="progressPercent">0%</span>
                </div>
            </div>
            <p class="progress-text" id="progressText">Initializing...</p>
            <div class="match-log" id="log"></div>
        </div>

        <div class="success-box" id="successMessage" style="display: none;">
            <i class="fa-solid fa-check-circle"></i>
            <div>
                <h3>✅ Smart Matching Complete!</h3>
                <p><strong>Summary:</strong></p>
                <ul>
                    <li><strong><span id="finalMatched">0</span> users</strong> successfully matched to groups</li>
                    <li><strong><span id="finalSkipped">0</span> users</strong> skipped (no suitable match found)</li>
                    <li>Users have been added to their local groups and parent groups automatically</li>
                </ul>
                <p style="margin-top: 1rem;">Check your <a href="<?= $basePath ?>/groups" style="color: #60a5fa;">groups page</a> to see updated member counts!</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Gold Standard FDS Enhancements */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

.info-box {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    color: #60a5fa;
    animation: fadeInUp 0.5s ease-out;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
    transition: all 0.3s ease;
}

.info-box:hover {
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.4);
}

.info-box i {
    font-size: 1.5rem;
    flex-shrink: 0;
    animation: pulse 2s ease-in-out infinite;
}

.info-box ul {
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.6;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: fadeInUp 0.5s ease-out backwards;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2), 0 0 0 1px rgba(99, 102, 241, 0.3);
    border-color: rgba(99, 102, 241, 0.3);
}

.stat-card:hover::before {
    left: 100%;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
}

.stat-primary .stat-icon {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.stat-success .stat-icon {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-warning .stat-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    transition: all 0.3s ease;
}

.stat-card:hover .stat-value {
    transform: scale(1.05);
}

.stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    transition: color 0.3s ease;
}

.stat-card:hover .stat-label {
    color: rgba(255, 255, 255, 0.8);
}

.admin-btn-lg {
    padding: 1rem 2rem;
    font-size: 1.1rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-btn-lg::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s ease, height 0.6s ease;
}

.admin-btn-lg:hover::before {
    width: 300px;
    height: 300px;
}

.admin-btn-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

.admin-btn-lg:active {
    transform: translateY(0);
}

.admin-btn-lg:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.progress-container {
    margin-top: 2rem;
    padding: 1.5rem;
    background: rgba(15, 23, 42, 0.5);
    border-radius: 12px;
    border: 1px solid rgba(99, 102, 241, 0.1);
    animation: fadeInUp 0.5s ease-out;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.progress-bar-wrapper {
    background: rgba(0, 0, 0, 0.3);
    height: 36px;
    border-radius: 18px;
    overflow: hidden;
    margin-bottom: 1rem;
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.progress-bar {
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    height: 100%;
    width: 0%;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    position: relative;
    overflow: hidden;
}

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    background-size: 200% 100%;
    animation: shimmer 2s linear infinite;
}

.progress-text {
    text-align: center;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.95rem;
    margin-bottom: 1rem;
    font-weight: 500;
}

.match-log {
    background: rgba(0, 0, 0, 0.4);
    border-radius: 8px;
    padding: 1rem;
    max-height: 400px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    line-height: 1.8;
    border: 1px solid rgba(255, 255, 255, 0.05);
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
}

.match-log::-webkit-scrollbar {
    width: 8px;
}

.match-log::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
}

.match-log::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.5);
    border-radius: 4px;
}

.match-log::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.7);
}

.match-log > div {
    animation: fadeInUp 0.3s ease-out;
}

.log-success {
    color: #10b981;
    text-shadow: 0 0 8px rgba(16, 185, 129, 0.3);
}

.log-skip {
    color: #f59e0b;
    text-shadow: 0 0 8px rgba(245, 158, 11, 0.3);
}

.log-error {
    color: #ef4444;
    text-shadow: 0 0 8px rgba(239, 68, 68, 0.3);
}

.log-info {
    color: #60a5fa;
    text-shadow: 0 0 8px rgba(96, 165, 250, 0.3);
}

.success-box {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    color: #10b981;
    animation: fadeInUp 0.5s ease-out;
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.15);
    transition: all 0.3s ease;
}

.success-box:hover {
    box-shadow: 0 6px 24px rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.4);
}

.success-box i {
    font-size: 2rem;
    flex-shrink: 0;
    animation: pulse 2s ease-in-out infinite;
}

.success-box h3 {
    margin-bottom: 0.75rem;
    color: #fff;
    font-weight: 700;
}

.success-box ul {
    margin-left: 1.5rem;
    margin-top: 0.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.success-box li {
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

.success-box li:hover {
    color: #fff;
    transform: translateX(4px);
}

.success-box a {
    color: #60a5fa;
    text-decoration: underline;
    transition: all 0.2s ease;
}

.success-box a:hover {
    color: #93c5fd;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }
}
</style>

<script>
let totalUsers = <?= $totalCount ?>;
let processed = 0;
let matched = 0;
let skipped = 0;
let offset = 0;
let failureCount = 0;
let maxRetries = 3;
let isPaused = false;
const STORAGE_KEY = 'smart_match_progress';

// Load saved progress on page load
window.addEventListener('DOMContentLoaded', function() {
    loadProgress();
});

function saveProgress() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
        processed,
        matched,
        skipped,
        offset,
        totalUsers,
        timestamp: Date.now()
    }));
}

function loadProgress() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (!saved) return;

    const data = JSON.parse(saved);
    const age = Date.now() - data.timestamp;

    // Only resume if less than 1 hour old
    if (age < 3600000 && data.offset > 0) {
        if (confirm(`Found incomplete matching session from ${Math.round(age / 60000)} minutes ago.\n\nProcessed: ${data.processed}/${data.totalUsers}\nMatched: ${data.matched}\nSkipped: ${data.skipped}\n\nResume from where you left off?`)) {
            processed = data.processed;
            matched = data.matched;
            skipped = data.skipped;
            offset = data.offset;
            updateProgress();
            log(`📥 Resumed from previous session (offset: ${offset})`, 'info');
        } else {
            clearProgress();
        }
    }
}

function clearProgress() {
    localStorage.removeItem(STORAGE_KEY);
}

function log(message, type = 'info') {
    const logEl = document.getElementById('log');
    const className = type === 'success' ? 'log-success' : (type === 'skip' ? 'log-skip' : (type === 'error' ? 'log-error' : 'log-info'));
    logEl.innerHTML += `<div class="${className}">${message}</div>`;
    logEl.scrollTop = logEl.scrollHeight;
}

function updateProgress() {
    const percent = totalUsers > 0 ? Math.round((processed / totalUsers) * 100) : 0;
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
    document.getElementById('matchedCount').textContent = matched;
    document.getElementById('skippedCount').textContent = skipped;
    document.getElementById('progressText').textContent =
        `Processing ${processed} of ${totalUsers} users... (${matched} matched, ${skipped} skipped)`;

    // Save progress after each update
    saveProgress();
}

async function processBatch(retryCount = 0) {
    if (isPaused) {
        log('⏸️  Paused by user', 'info');
        return;
    }

    try {
        const response = await fetch(`<?= $basePath ?>/admin/smart-match-users?action=match_batch&offset=${offset}`);

        // Check if response is ok
        if (!response.ok) {
            const text = await response.text();
            log(`HTTP Error ${response.status}: ${text}`, 'error');

            // Retry logic
            if (retryCount < maxRetries) {
                const waitTime = Math.pow(2, retryCount) * 1000; // Exponential backoff
                log(`Retrying in ${waitTime/1000} seconds... (attempt ${retryCount + 1}/${maxRetries})`, 'info');
                setTimeout(() => processBatch(retryCount + 1), waitTime);
            } else {
                log(`❌ Max retries reached. Progress saved at offset ${offset}.`, 'error');
                log(`You can refresh the page and click "Start Smart Matching" to resume.`, 'info');
                failureCount++;
            }
            return;
        }

        // Try to parse JSON
        let data;
        try {
            data = await response.json();
        } catch (e) {
            const text = await response.text();
            log(`JSON Parse Error: ${text}`, 'error');

            // Retry logic for parse errors
            if (retryCount < maxRetries) {
                const waitTime = Math.pow(2, retryCount) * 1000;
                log(`Retrying in ${waitTime/1000} seconds... (attempt ${retryCount + 1}/${maxRetries})`, 'info');
                setTimeout(() => processBatch(retryCount + 1), waitTime);
            } else {
                log(`❌ Max retries reached. Progress saved at offset ${offset}.`, 'error');
                failureCount++;
            }
            return;
        }

        // Check for error response
        if (data.error) {
            log(`Server Error: ${data.message}`, 'error');
            if (data.trace) log(`Trace: ${data.trace}`, 'error');

            // Retry for server errors
            if (retryCount < maxRetries) {
                const waitTime = Math.pow(2, retryCount) * 1000;
                log(`Retrying in ${waitTime/1000} seconds... (attempt ${retryCount + 1}/${maxRetries})`, 'info');
                setTimeout(() => processBatch(retryCount + 1), waitTime);
            } else {
                log(`❌ Max retries reached. Progress saved at offset ${offset}.`, 'error');
                failureCount++;
            }
            return;
        }

        // Process batch
        if (!data.batch || !Array.isArray(data.batch)) {
            log(`Invalid response format: ${JSON.stringify(data)}`, 'error');
            return;
        }

        // Reset failure count on success
        failureCount = 0;

        for (const result of data.batch) {
            processed++;

            if (result.success) {
                matched++;
                const groupNames = result.groups.map(g => g.name).join(', ');
                const method = result.method === 'geographic' ? '📍' : '🔤';
                log(`${method} ✓ ${result.user_name} → ${groupNames}`, 'success');
            } else {
                skipped++;
                log(`⊘ ${result.user_name} - ${result.message}`, 'skip');
            }

            updateProgress();
        }

        offset += data.batch.length;

        if (data.hasMore) {
            setTimeout(() => processBatch(0), 200); // Reset retry count for next batch
        } else {
            log(`\n🎉 COMPLETE! Matched ${matched} users, skipped ${skipped}.`, 'success');
            document.getElementById('finalMatched').textContent = matched;
            document.getElementById('finalSkipped').textContent = skipped;
            document.getElementById('successMessage').style.display = 'flex';
            clearProgress(); // Clear saved progress on completion

            // Send completion notification
            sendCompletionNotification();
        }
    } catch (error) {
        log(`Fatal Error: ${error.message}`, 'error');
        log(`Stack: ${error.stack}`, 'error');

        // Retry for network errors
        if (retryCount < maxRetries) {
            const waitTime = Math.pow(2, retryCount) * 1000;
            log(`Retrying in ${waitTime/1000} seconds... (attempt ${retryCount + 1}/${maxRetries})`, 'info');
            setTimeout(() => processBatch(retryCount + 1), waitTime);
        } else {
            log(`❌ Max retries reached. Progress saved at offset ${offset}.`, 'error');
            log(`Refresh the page and click "Start Smart Matching" to resume.`, 'info');
            failureCount++;
        }
    }
}

async function sendCompletionNotification() {
    try {
        await fetch(`<?= $basePath ?>/admin/smart-match-users?action=notify_complete`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                matched: matched,
                skipped: skipped,
                total: totalUsers
            })
        });
    } catch (e) {
        console.log('Notification failed (non-critical):', e);
    }
}

function startMatching() {
    if (!confirm(`This will smart-match ${totalUsers} users to groups. Continue?`)) {
        return;
    }

    document.getElementById('startBtn').disabled = true;
    document.getElementById('progressContainer').style.display = 'block';
    log('🚀 Starting smart matching process...', 'info');
    log(`📊 Total users to process: ${totalUsers}\n`, 'info');
    isPaused = false;
    processBatch(0);
}
</script>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
