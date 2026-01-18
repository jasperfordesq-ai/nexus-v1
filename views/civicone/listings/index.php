<?php require __DIR__ . '/../../layouts/civicone/header.php'; ?>

<style>
    /* Listings grid mobile responsive */
    .civic-listings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
    }
    @media (max-width: 600px) {
        .civic-listings-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        .civic-listings-header {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .civic-listings-header .civic-btn {
            text-align: center;
        }
    }
</style>

<div class="civic-container">
    <?php
    $breadcrumbs = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Offers & Requests']
    ];
    require __DIR__ . '/../../layouts/civicone/partials/breadcrumb.php';
    ?>

    <div class="civic-listings-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
        <h1 style="margin: 0;">Offers & Requests</h1>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/create" class="civic-btn">Post an Ad</a>
    </div>

    <?php if (empty($listings)): ?>
        <p>No active listings found.</p>
    <?php else: ?>
        <div class="civic-grid civic-listings-grid">
            <?php foreach ($listings as $listing): ?>
                <?php
                // Accessible GDS Colors:
                $isOffer = $listing['type'] === 'offer';
                $badgeBg = $isOffer ? '#00703C' : '#F47738';
                $badgeText = $isOffer ? '#FFFFFF' : '#0B0C0C';
                ?>
                <div class="civic-card listing-card" style="
                    display: flex; 
                    flex-direction: column; 
                    padding: 0; 
                    background: var(--civic-bg-card, #f7f7f7); 
                    border: none; 
                    border-radius: 6px; 
                    overflow: hidden;
                    box-shadow: none;
                    height: 100%;
                ">
                    <div style="padding: 25px 25px 15px 25px; flex-grow: 1;">
                        <span style="
                            background: <?= $badgeBg ?>; 
                            color: <?= $badgeText ?>; 
                            padding: 4px 10px; 
                            font-weight: 700; 
                            font-size: 0.75em; 
                            text-transform: uppercase; 
                            display: inline-block; 
                            margin-bottom: 12px;
                            border-radius: 4px;
                        ">
                            <?= htmlspecialchars($listing['type']) ?>
                        </span>

                        <h3 style="margin: 0 0 10px 0; font-size: 1.25rem;">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/<?= $listing['id'] ?>"
                               aria-label="View <?= htmlspecialchars($listing['type']) ?>: <?= htmlspecialchars($listing['title']) ?> by <?= htmlspecialchars($listing['author_name']) ?>"
                               style="
                                text-decoration: none;
                                color: var(--civic-brand, #00796B);
                                font-weight: 600;
                            ">
                                <?= htmlspecialchars($listing['title']) ?>
                            </a>
                        </h3>

                        <p style="
                            color: var(--civic-text-main, #374151); 
                            font-size: 0.95rem; 
                            line-height: 1.5;
                            margin-bottom: 20px;
                        "><?= htmlspecialchars(substr($listing['description'], 0, 100)) ?>...</p>
                    </div>

                    <div style="
                        padding: 15px 25px; 
                        background: rgba(0,0,0,0.02); 
                        border-top: 1px solid rgba(0,0,0,0.05); 
                        display: flex; 
                        justify-content: space-between; 
                        align-items: center;
                    ">
                        <div style="font-size: 0.85em; color: var(--civic-text-muted, #6B7280);">
                            By <strong><?= htmlspecialchars($listing['author_name']) ?></strong>
                        </div>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/<?= $listing['id'] ?>"
                           class="civic-btn"
                           aria-label="View details for <?= htmlspecialchars($listing['title']) ?>"
                           style="
                            padding: 6px 18px;
                            font-size: 0.85em;
                            background: transparent;
                            color: var(--civic-brand, #00796B);
                            border: 1px solid var(--civic-brand, #00796B);
                            border-radius: 20px;
                            font-weight: 600;
                            text-decoration: none;
                        ">View</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>