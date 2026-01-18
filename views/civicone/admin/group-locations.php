<?php
/**
 * Group Location Manager - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

// Admin check
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ' . TenantContext::getBasePath() . '/');
    exit;
}

$tenantId = TenantContext::getId();
$basePath = TenantContext::getBasePath();

// Irish county mappings
$countyPatterns = [
    'Cork' => 'County Cork, Ireland',
    'Dublin' => 'County Dublin, Ireland',
    'Galway' => 'County Galway, Ireland',
    'Limerick' => 'County Limerick, Ireland',
    'Kerry' => 'County Kerry, Ireland',
    'Mayo' => 'County Mayo, Ireland',
    'Clare' => 'County Clare, Ireland',
    'Tipperary' => 'County Tipperary, Ireland',
    'Waterford' => 'County Waterford, Ireland',
    'Wexford' => 'County Wexford, Ireland',
    'Wicklow' => 'County Wicklow, Ireland',
    'Kilkenny' => 'County Kilkenny, Ireland',
    'Kildare' => 'County Kildare, Ireland',
    'Meath' => 'County Meath, Ireland',
    'Louth' => 'County Louth, Ireland',
    'Westmeath' => 'County Westmeath, Ireland',
    'Offaly' => 'County Offaly, Ireland',
    'Laois' => 'County Laois, Ireland',
    'Carlow' => 'County Carlow, Ireland',
    'Longford' => 'County Longford, Ireland',
    'Cavan' => 'County Cavan, Ireland',
    'Monaghan' => 'County Monaghan, Ireland',
    'Donegal' => 'County Donegal, Ireland',
    'Sligo' => 'County Sligo, Ireland',
    'Leitrim' => 'County Leitrim, Ireland',
    'Roscommon' => 'County Roscommon, Ireland',
    'Antrim' => 'County Antrim, Northern Ireland',
    'Armagh' => 'County Armagh, Northern Ireland',
    'Down' => 'County Down, Northern Ireland',
    'Derry' => 'County Derry, Northern Ireland',
    'Fermanagh' => 'County Fermanagh, Northern Ireland',
    'Tyrone' => 'County Tyrone, Northern Ireland',
];

function extractLocationFromName($name, $countyPatterns) {
    foreach ($countyPatterns as $county => $location) {
        if (stripos($name, $county) !== false) {
            return $location;
        }
    }
    return null;
}

function buildGroupLocation($groupName, $countyLocation) {
    $parts = explode(',', $countyLocation);
    $county = trim($parts[0]);
    $country = isset($parts[1]) ? trim($parts[1]) : 'Ireland';

    if (stripos($groupName, 'County') !== false) {
        return $countyLocation;
    }

    $countyName = str_ireplace('County ', '', $county);
    if (stripos($groupName, $countyName) !== false) {
        return $groupName . ', ' . $country;
    }

    return $groupName . ', ' . $county . ', ' . $country;
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_all') {
        $updated = 0;
        $errors = 0;

        $groups = Database::query(
            "SELECT g.id, g.name, g.location, g.parent_id, p.name as parent_name, p.location as parent_location
             FROM `groups` g
             LEFT JOIN `groups` p ON g.parent_id = p.id
             WHERE g.tenant_id = ? AND (g.location IS NULL OR g.location = '')",
            [$tenantId]
        )->fetchAll();

        foreach ($groups as $group) {
            $newLocation = null;
            $countyLocation = null;

            if (!empty($group['parent_location'])) {
                $countyLocation = $group['parent_location'];
            } elseif (!empty($group['parent_name'])) {
                $countyLocation = extractLocationFromName($group['parent_name'], $countyPatterns);
            }
            if (!$countyLocation) {
                $countyLocation = extractLocationFromName($group['name'], $countyPatterns);
            }

            if ($countyLocation) {
                $newLocation = buildGroupLocation($group['name'], $countyLocation);
            }

            if ($newLocation) {
                try {
                    Database::query("UPDATE `groups` SET location = ? WHERE id = ?", [$newLocation, $group['id']]);
                    $updated++;
                } catch (Exception $e) {
                    $errors++;
                }
            }
        }

        $message = "Updated {$updated} groups" . ($errors > 0 ? ", {$errors} errors" : "");
        $messageType = $errors > 0 ? 'warning' : 'success';
    }
    elseif ($_POST['action'] === 'update_single' && isset($_POST['group_id'], $_POST['location'])) {
        try {
            Database::query(
                "UPDATE `groups` SET location = ? WHERE id = ? AND tenant_id = ?",
                [$_POST['location'], $_POST['group_id'], $tenantId]
            );
            $message = "Group location updated";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Error updating group: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch all groups
$sql = "SELECT g.id, g.name, g.location, g.parent_id, p.name as parent_name, p.location as parent_location
        FROM `groups` g
        LEFT JOIN `groups` p ON g.parent_id = p.id
        WHERE g.tenant_id = ?
        ORDER BY g.parent_id, g.name";

$groups = Database::query($sql, [$tenantId])->fetchAll();

// Analyze groups
$withLocation = [];
$canAutoMap = [];
$needsManual = [];

foreach ($groups as $group) {
    if (!empty($group['location'])) {
        $withLocation[] = $group;
        continue;
    }

    $countyLocation = null;
    $source = '';

    if (!empty($group['parent_location'])) {
        $countyLocation = $group['parent_location'];
        $source = 'parent location';
    } elseif (!empty($group['parent_name'])) {
        $countyLocation = extractLocationFromName($group['parent_name'], $countyPatterns);
        $source = 'parent name';
    }
    if (!$countyLocation) {
        $countyLocation = extractLocationFromName($group['name'], $countyPatterns);
        $source = 'group name';
    }

    if ($countyLocation) {
        $group['suggested_location'] = buildGroupLocation($group['name'], $countyLocation);
        $group['source'] = $source;
        $canAutoMap[] = $group;
    } else {
        $needsManual[] = $group;
    }
}

// Admin header configuration
$adminPageTitle = 'Group Locations';
$adminPageSubtitle = 'Community';
$adminPageIcon = 'fa-location-dot';

require __DIR__ . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-location-dot"></i>
            Group Locations
        </h1>
        <p class="admin-page-subtitle">Manage geographic boundaries for groups</p>
    </div>
</div>

<?php if ($message): ?>
<div class="admin-alert admin-alert-<?= $messageType ?>">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i>
    </div>
    <div class="admin-alert-content">
        <?= htmlspecialchars($message) ?>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-check"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count($withLocation) ?></div>
            <div class="admin-stat-label">Have Location</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count($canAutoMap) ?></div>
            <div class="admin-stat-label">Can Auto-Map</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-hand-pointer"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count($needsManual) ?></div>
            <div class="admin-stat-label">Need Manual</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-pink">
        <div class="admin-stat-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count($groups) ?></div>
            <div class="admin-stat-label">Total Groups</div>
        </div>
    </div>
</div>

<!-- Can Auto-Map Section -->
<?php if (!empty($canAutoMap)): ?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Can Be Auto-Mapped</h3>
            <p class="admin-card-subtitle"><?= count($canAutoMap) ?> groups ready for automatic location assignment</p>
        </div>
        <form method="POST" style="margin-left: auto;">
            <input type="hidden" name="action" value="update_all">
            <button type="submit" class="admin-btn admin-btn-primary" onclick="return confirm('Update all <?= count($canAutoMap) ?> groups with suggested locations?')">
                <i class="fa-solid fa-bolt"></i>
                Apply All
            </button>
        </form>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th class="hide-mobile">Parent</th>
                        <th>Suggested Location</th>
                        <th class="hide-tablet">Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($canAutoMap as $group): ?>
                    <tr>
                        <td><strong style="color: #fff;"><?= htmlspecialchars($group['name']) ?></strong></td>
                        <td class="hide-mobile" style="color: rgba(255,255,255,0.6);"><?= htmlspecialchars($group['parent_name'] ?? '-') ?></td>
                        <td>
                            <span style="color: #22c55e;"><i class="fa-solid fa-arrow-right"></i></span>
                            <span style="color: #a5b4fc;"><?= htmlspecialchars($group['suggested_location']) ?></span>
                        </td>
                        <td class="hide-tablet">
                            <span class="admin-source-badge"><?= $group['source'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Needs Manual Section -->
<?php if (!empty($needsManual)): ?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fa-solid fa-hand-pointer"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Need Manual Assignment</h3>
            <p class="admin-card-subtitle"><?= count($needsManual) ?> groups require manual location entry</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th class="hide-mobile">Parent</th>
                        <th>Set Location</th>
                        <th style="width: 100px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($needsManual as $group): ?>
                    <tr>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_single">
                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                            <td><strong style="color: #fff;"><?= htmlspecialchars($group['name']) ?></strong></td>
                            <td class="hide-mobile" style="color: rgba(255,255,255,0.6);"><?= htmlspecialchars($group['parent_name'] ?? '-') ?></td>
                            <td>
                                <input type="text" name="location" class="admin-input" placeholder="Enter location...">
                            </td>
                            <td>
                                <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-save"></i> Save
                                </button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Already Have Location Section -->
<?php if (!empty($withLocation)): ?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Already Have Location</h3>
            <p class="admin-card-subtitle"><?= count($withLocation) ?> groups with assigned locations</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Location</th>
                        <th class="hide-mobile">Parent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withLocation as $group): ?>
                    <tr>
                        <td><strong style="color: #fff;"><?= htmlspecialchars($group['name']) ?></strong></td>
                        <td>
                            <span class="admin-location-badge"><?= htmlspecialchars($group['location']) ?></span>
                        </td>
                        <td class="hide-mobile" style="color: rgba(255,255,255,0.6);"><?= htmlspecialchars($group['parent_name'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Gold Standard FDS Animations */
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

/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
    .admin-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .admin-stats-grid { grid-template-columns: 1fr; }
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.5s ease-out backwards;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2), 0 0 0 1px rgba(99, 102, 241, 0.3);
    border-color: rgba(99, 102, 241, 0.3);
}

.admin-stat-card:nth-child(1) { animation-delay: 0.1s; }
.admin-stat-card:nth-child(2) { animation-delay: 0.15s; }
.admin-stat-card:nth-child(3) { animation-delay: 0.2s; }
.admin-stat-card:nth-child(4) { animation-delay: 0.25s; }

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-orange { --stat-color: #f59e0b; }
.admin-stat-pink { --stat-color: #ec4899; }

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.admin-stat-card:hover .admin-stat-icon {
    transform: scale(1.1) rotate(5deg);
}

.admin-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    transition: all 0.3s ease;
}

.admin-stat-card:hover .admin-stat-value {
    transform: scale(1.05);
}

.admin-stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    transition: color 0.3s ease;
}

.admin-stat-card:hover .admin-stat-label {
    color: rgba(255, 255, 255, 0.8);
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    animation: fadeInUp 0.5s ease-out;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.admin-alert:hover {
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.admin-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.admin-alert-icon {
    font-size: 1.25rem;
    animation: pulse 2s ease-in-out infinite;
}

/* Table */
.admin-table-wrapper { overflow-x: auto; }

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

/* Input */
.admin-input {
    padding: 0.5rem 0.75rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.8);
    color: #fff;
    font-size: 0.85rem;
    width: 200px;
}

.admin-input:focus {
    outline: none;
    border-color: #6366f1;
}

.admin-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

/* Badges */
.admin-source-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #a5b4fc;
    text-transform: capitalize;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
}

.admin-source-badge:hover {
    background: rgba(99, 102, 241, 0.25);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.admin-location-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 6px;
    font-size: 0.8rem;
    color: #22c55e;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(34, 197, 94, 0.2);
}

.admin-location-badge:hover {
    background: rgba(34, 197, 94, 0.25);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.admin-btn::before {
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

.admin-btn:hover::before {
    width: 300px;
    height: 300px;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #06b6d4, #3b82f6);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 8px 24px rgba(6, 182, 212, 0.4);
    transform: translateY(-2px);
}

.admin-btn-primary:active {
    transform: translateY(0);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.2);
}

.admin-btn-sm {
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
}

/* Glass Cards */
.admin-glass-card {
    animation: fadeInUp 0.5s ease-out backwards;
}

.admin-glass-card:nth-of-type(2) { animation-delay: 0.3s; }
.admin-glass-card:nth-of-type(3) { animation-delay: 0.35s; }

/* Table Enhancements */
.admin-table tbody tr {
    transition: all 0.2s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
    transform: scale(1.005);
}

.admin-table tbody tr:hover td {
    color: #fff;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet { display: none; }
}

@media (max-width: 768px) {
    .hide-mobile { display: none; }
    .admin-input { width: 140px; }
    .admin-table th, .admin-table td { padding: 0.75rem 1rem; }
    .admin-card-header { flex-wrap: wrap; gap: 1rem; }
    .admin-card-header form { width: 100%; }
    .admin-card-header .admin-btn { width: 100%; justify-content: center; }
}
</style>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
