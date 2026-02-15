<?php
/**
 * Geocode Groups - Admin Interface
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

$tenantId = TenantContext::getId();
$basePath = TenantContext::getBasePath();

// Get total count needing geocoding
$totalCount = Database::query("
    SELECT COUNT(*) as total
    FROM `groups`
    WHERE tenant_id = ?
    AND type_id = 26
    AND (visibility IS NULL OR visibility = 'public')
    AND (latitude IS NULL OR longitude IS NULL)
    AND location IS NOT NULL
    AND location != ''
", [$tenantId])->fetch()['total'];

// Admin header configuration
$adminPageTitle = 'Geocode Groups';
$adminPageSubtitle = 'Community';
$adminPageIcon = 'fa-map-location-dot';

require __DIR__ . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-map-location-dot"></i>
            Geocode Groups
        </h1>
        <p class="admin-page-subtitle">Convert group locations to GPS coordinates using OpenStreetMap</p>
    </div>
</div>

<div class="admin-glass-card">
    <div class="admin-card-body">
        <div class="warning-box">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <div>
                <strong>Important:</strong> This will use OpenStreetMap to add accurate coordinates to all groups.
                The process takes about 5-10 minutes (1 second per group for API rate limiting).
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="fa-solid fa-map-marked-alt"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="totalGroups"><?= $totalCount ?></div>
                    <div class="stat-label">Groups to Geocode</div>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="geocodedCount">0</div>
                    <div class="stat-label">Successfully Geocoded</div>
                </div>
            </div>
        </div>

        <button id="startBtn" class="admin-btn admin-btn-primary admin-btn-lg" onclick="startGeocoding()" style="width: 100%;">
            <i class="fa-solid fa-rocket"></i>
            Start Geocoding
        </button>

        <div class="progress-container" id="progressContainer" style="display: none;">
            <div class="progress-bar-wrapper">
                <div class="progress-bar" id="progressBar">
                    <span id="progressPercent">0%</span>
                </div>
            </div>
            <p class="progress-text" id="progressText">Initializing...</p>
            <div class="geocode-log" id="log"></div>
        </div>

        <div class="success-box" id="successMessage" style="display: none;">
            <i class="fa-solid fa-check-circle"></i>
            <div>
                <h3>✅ Geocoding Complete!</h3>
                <p><strong>Next Steps:</strong></p>
                <ol>
                    <li>Go to phpMyAdmin on your server</li>
                    <li>Copy and paste the contents of <code>COMPLETE-WORKFLOW-SAFE.sql</code></li>
                    <li>Click "Go" to assign users to groups</li>
                    <li>Your groups page will now show accurate member counts!</li>
                </ol>
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
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.8;
    }
}

@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

.warning-box {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    color: #f59e0b;
    animation: fadeInUp 0.5s ease-out;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.1);
    transition: all 0.3s ease;
}

.warning-box:hover {
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.15);
    border-color: rgba(245, 158, 11, 0.4);
}

.warning-box i {
    font-size: 1.5rem;
    flex-shrink: 0;
    animation: pulse 2s ease-in-out infinite;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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

.stat-card:nth-child(1) {
    animation-delay: 0.1s;
}

.stat-card:nth-child(2) {
    animation-delay: 0.2s;
}

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

.geocode-log {
    background: rgba(0, 0, 0, 0.4);
    border-radius: 8px;
    padding: 1rem;
    max-height: 300px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    line-height: 1.8;
    border: 1px solid rgba(255, 255, 255, 0.05);
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
}

.geocode-log::-webkit-scrollbar {
    width: 8px;
}

.geocode-log::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
}

.geocode-log::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.5);
    border-radius: 4px;
}

.geocode-log::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.7);
}

.geocode-log > div {
    animation: fadeInUp 0.3s ease-out;
}

.log-success {
    color: #10b981;
    text-shadow: 0 0 8px rgba(16, 185, 129, 0.3);
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

.success-box ol {
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

.success-box code {
    background: rgba(0, 0, 0, 0.3);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    color: #60a5fa;
    font-weight: 600;
    border: 1px solid rgba(96, 165, 250, 0.2);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }
}

<script>
let totalGroups = <?= $totalCount ?>;
let processed = 0;
let successful = 0;
let failed = 0;
let offset = 0;

function log(message, type = 'info') {
    const logEl = document.getElementById('log');
    const className = type === 'success' ? 'log-success' : (type === 'error' ? 'log-error' : 'log-info');
    logEl.innerHTML += `<div class="${className}">${message}</div>`;
    logEl.scrollTop = logEl.scrollHeight;
}

function updateProgress() {
    const percent = totalGroups > 0 ? Math.round((processed / totalGroups) * 100) : 0;
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
    document.getElementById('geocodedCount').textContent = successful;
    document.getElementById('progressText').textContent =
        `Processing ${processed} of ${totalGroups} groups... (${successful} successful, ${failed} failed)`;
}

async function processBatch() {
    try {
        const response = await fetch(`<?= $basePath ?>/admin-legacy/geocode-groups?action=geocode_batch&offset=${offset}`);
        const data = await response.json();

        for (const result of data.batch) {
            processed++;
            if (result.success) {
                successful++;
                log(`✓ ${result.name} → [${result.coords[0].toFixed(4)}, ${result.coords[1].toFixed(4)}]`, 'success');
            } else {
                failed++;
                log(`✗ ${result.name} - ${result.error}`, 'error');
            }
            updateProgress();
        }

        offset += data.batch.length;

        if (data.hasMore) {
            setTimeout(processBatch, 100);
        } else {
            log(`\n🎉 COMPLETE! Geocoded ${successful} groups, ${failed} failed.`, 'success');
            document.getElementById('successMessage').style.display = 'flex';
        }
    } catch (error) {
        log(`Error: ${error.message}`, 'error');
    }
}

function startGeocoding() {
    if (!confirm(`This will geocode ${totalGroups} groups. Continue?`)) {
        return;
    }

    document.getElementById('startBtn').disabled = true;
    document.getElementById('progressContainer').style.display = 'block';
    log('🚀 Starting geocoding process...', 'info');
    log(`📊 Total groups to process: ${totalGroups}\n`, 'info');
    processBatch();
}
</script>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
