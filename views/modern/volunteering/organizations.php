<?php
// Organizations Listing - Glassmorphism 2025
$pageTitle = "Organizations";
$pageSubtitle = "Find volunteer organizations in your community";
$hideHero = true;

Nexus\Core\SEO::setTitle('Volunteer Organizations - Find Causes You Care About');
Nexus\Core\SEO::setDescription('Browse volunteer organizations in your community. Join teams, discover causes, and make a meaningful impact.');

require __DIR__ . '/../../layouts/modern/header.php';

$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = $hasTimebanking ?? \Nexus\Core\TenantContext::hasFeature('wallet');
?>

<div class="htb-container-full">
<div id="org-glass-wrapper">

    <style>
        /* ===================================
           GLASSMORPHISM ORGANIZATIONS INDEX
           ================================= */

        /* Main Content Card Wrapper */
        #org-glass-wrapper {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.85),
                rgba(255, 255, 255, 0.7));
            backdrop-filter: blur(24px) saturate(120%);
            -webkit-backdrop-filter: blur(24px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 8px 40px rgba(31, 38, 135, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] #org-glass-wrapper {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.75),
                rgba(30, 41, 59, 0.65));
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        @media (max-width: 768px) {
            #org-glass-wrapper {
                padding: 20px;
                padding-bottom: 100px;
                border-radius: 16px;
            }
        }

        /* Animated Gradient Background */
        #org-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #org-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.08) 0%,
                rgba(139, 92, 246, 0.08) 25%,
                rgba(13, 148, 136, 0.08) 50%,
                rgba(20, 184, 166, 0.08) 75%,
                rgba(99, 102, 241, 0.08) 100%);
            background-size: 400% 400%;
            animation: orgGradientShift 15s ease infinite;
        }

        [data-theme="dark"] #org-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(99, 102, 241, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(13, 148, 136, 0.12) 0%, transparent 50%);
        }

        @keyframes orgGradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Page Header */
        #org-glass-wrapper .org-page-header {
            text-align: center;
            padding: 40px 20px 30px;
        }

        #org-glass-wrapper .org-page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 10px 0;
            background: linear-gradient(135deg, #6366f1 0%, #0d9488 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        #org-glass-wrapper .org-page-subtitle {
            font-size: 1.1rem;
            color: var(--htb-text-secondary);
            margin: 0;
        }

        /* Back Link */
        #org-glass-wrapper .org-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--htb-text-secondary);
            text-decoration: none;
            font-weight: 600;
            padding: 10px 0;
            margin-bottom: 10px;
            transition: color 0.2s;
        }

        #org-glass-wrapper .org-back-link:hover {
            color: #0d9488;
        }

        /* Glass Search Card */
        #org-glass-wrapper .glass-search-card {
            position: relative;
            z-index: 10;
            padding: 20px 25px;
            border-radius: 20px;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(20px) saturate(120%);
            -webkit-backdrop-filter: blur(20px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15),
                        inset 0 0 0 1px rgba(255, 255, 255, 0.4);
            margin-bottom: 30px;
        }

        [data-theme="dark"] #org-glass-wrapper .glass-search-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 80px rgba(99, 102, 241, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        #org-glass-wrapper .search-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        #org-glass-wrapper .search-input-wrapper {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        #org-glass-wrapper .search-input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--htb-text-secondary);
            font-size: 1.1rem;
        }

        #org-glass-wrapper .glass-search-input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 14px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(31, 38, 135, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
            box-sizing: border-box;
            color: var(--htb-text-main);
        }

        #org-glass-wrapper .glass-search-input:focus {
            border-color: rgba(99, 102, 241, 0.6);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15),
                        0 8px 24px rgba(99, 102, 241, 0.2);
            outline: none;
            background: rgba(255, 255, 255, 0.8);
        }

        [data-theme="dark"] #org-glass-wrapper .glass-search-input {
            background: rgba(15, 23, 42, 0.7);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: #f8fafc;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] #org-glass-wrapper .glass-search-input:focus {
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2),
                        0 8px 24px rgba(99, 102, 241, 0.3);
            background: rgba(15, 23, 42, 0.85);
        }

        #org-glass-wrapper .glass-btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #org-glass-wrapper .glass-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        /* Organizations Grid */
        #org-glass-wrapper .org-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        @media (max-width: 400px) {
            #org-glass-wrapper .org-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Organization Card */
        #org-glass-wrapper .org-card {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.8),
                rgba(255, 255, 255, 0.65));
            backdrop-filter: blur(20px) saturate(120%);
            -webkit-backdrop-filter: blur(20px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        #org-glass-wrapper .org-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 48px rgba(99, 102, 241, 0.2),
                        0 0 0 1px rgba(99, 102, 241, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
            border-color: rgba(99, 102, 241, 0.3);
        }

        [data-theme="dark"] #org-glass-wrapper .org-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.7),
                rgba(30, 41, 59, 0.6));
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] #org-glass-wrapper .org-card:hover {
            box-shadow: 0 16px 48px rgba(99, 102, 241, 0.25),
                        0 0 40px rgba(99, 102, 241, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            border-color: rgba(99, 102, 241, 0.4);
        }

        /* Card Header with Logo */
        #org-glass-wrapper .org-card-header {
            padding: 24px 24px 16px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        #org-glass-wrapper .org-logo {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            object-fit: cover;
            flex-shrink: 0;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        #org-glass-wrapper .org-logo img {
            width: 100%;
            height: 100%;
            border-radius: 14px;
            object-fit: cover;
        }

        #org-glass-wrapper .org-card-title-area {
            flex: 1;
            min-width: 0;
        }

        #org-glass-wrapper .org-card-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 6px 0;
            color: var(--htb-text-main);
            line-height: 1.3;
        }

        #org-glass-wrapper .org-card-owner {
            font-size: 0.85rem;
            color: var(--htb-text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Card Body */
        #org-glass-wrapper .org-card-body {
            padding: 0 24px 20px;
            flex: 1;
        }

        #org-glass-wrapper .org-card-description {
            font-size: 0.95rem;
            color: var(--htb-text-secondary);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin: 0;
        }

        /* Card Stats */
        #org-glass-wrapper .org-card-stats {
            display: flex;
            gap: 16px;
            padding: 16px 24px;
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.05),
                rgba(13, 148, 136, 0.05));
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        [data-theme="dark"] #org-glass-wrapper .org-card-stats {
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.1),
                rgba(13, 148, 136, 0.1));
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        #org-glass-wrapper .org-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: var(--htb-text-secondary);
        }

        #org-glass-wrapper .org-stat i {
            color: #6366f1;
            font-size: 0.9rem;
        }

        #org-glass-wrapper .org-stat-value {
            font-weight: 700;
            color: var(--htb-text-main);
        }

        /* Empty State */
        #org-glass-wrapper .org-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] #org-glass-wrapper .org-empty-state {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        #org-glass-wrapper .org-empty-icon {
            font-size: 4rem;
            color: #6366f1;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        #org-glass-wrapper .org-empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            color: var(--htb-text-main);
        }

        #org-glass-wrapper .org-empty-text {
            color: var(--htb-text-secondary);
            margin: 0;
        }

        /* Result count */
        #org-glass-wrapper .org-result-count {
            font-size: 0.95rem;
            color: var(--htb-text-secondary);
            margin-bottom: 15px;
        }

        #org-glass-wrapper .org-result-count strong {
            color: var(--htb-text-main);
        }
    </style>

    <!-- Page Header -->
    <div class="org-page-header">
        <a href="<?= $base ?>/volunteering" class="org-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Opportunities
        </a>
        <h1 class="org-page-title">
            <i class="fa-solid fa-building-columns"></i>
            Organizations
        </h1>
        <p class="org-page-subtitle">Discover groups making a difference in your community</p>
    </div>

    <!-- Search Card -->
    <div class="glass-search-card">
        <form class="search-form" method="GET" action="<?= $base ?>/volunteering/organizations">
            <div class="search-input-wrapper">
                <i class="fa-solid fa-search"></i>
                <input type="text"
                       name="q"
                       class="glass-search-input"
                       placeholder="Search organizations by name or cause..."
                       value="<?= htmlspecialchars($query ?? '') ?>">
            </div>
            <button type="submit" class="glass-btn-primary">
                <i class="fa-solid fa-search"></i>
                Search
            </button>
        </form>
    </div>

    <?php if (!empty($query)): ?>
        <p class="org-result-count">
            Found <strong><?= count($organizations) ?></strong> organization<?= count($organizations) !== 1 ? 's' : '' ?>
            matching "<?= htmlspecialchars($query) ?>"
        </p>
    <?php endif; ?>

    <?php if (empty($organizations)): ?>
        <!-- Empty State -->
        <div class="org-empty-state">
            <div class="org-empty-icon">
                <i class="fa-solid fa-building-circle-xmark"></i>
            </div>
            <h2 class="org-empty-title">No Organizations Found</h2>
            <p class="org-empty-text">
                <?php if (!empty($query)): ?>
                    No organizations match your search. Try different keywords.
                <?php else: ?>
                    There are no organizations yet. Be the first to register one!
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Organizations Grid -->
        <div class="org-grid">
            <?php foreach ($organizations as $org): ?>
                <a href="<?= $base ?>/volunteering/organization/<?= $org['id'] ?>" class="org-card">
                    <div class="org-card-header">
                        <div class="org-logo">
                            <?php if (!empty($org['logo'])): ?>
                                <img src="<?= htmlspecialchars($org['logo']) ?>" loading="lazy" alt="<?= htmlspecialchars($org['name']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($org['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="org-card-title-area">
                            <h3 class="org-card-name"><?= htmlspecialchars($org['name']) ?></h3>
                            <div class="org-card-owner">
                                <i class="fa-solid fa-user"></i>
                                <?= htmlspecialchars($org['owner_name'] ?? 'Unknown') ?>
                            </div>
                        </div>
                    </div>
                    <div class="org-card-body">
                        <p class="org-card-description">
                            <?= htmlspecialchars(substr($org['description'], 0, 200)) ?><?= strlen($org['description']) > 200 ? '...' : '' ?>
                        </p>
                    </div>
                    <div class="org-card-stats">
                        <div class="org-stat">
                            <i class="fa-solid fa-briefcase"></i>
                            <span class="org-stat-value"><?= (int)($org['opportunity_count'] ?? 0) ?></span>
                            Opportunities
                        </div>
                        <?php if ($hasTimebanking && isset($org['member_count'])): ?>
                            <div class="org-stat">
                                <i class="fa-solid fa-users"></i>
                                <span class="org-stat-value"><?= (int)$org['member_count'] ?></span>
                                Members
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
