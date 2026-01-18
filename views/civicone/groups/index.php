<?php require __DIR__ . '/../../layouts/civicone/header.php'; ?>

<div class="civic-container">
    <?php
    $breadcrumbs = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Local Hubs']
    ];
    require __DIR__ . '/../../layouts/civicone/partials/breadcrumb.php';
    ?>

    <div class="civic-groups-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 12px;">
        <h1 style="margin: 0;">Local Hubs</h1>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/create-group" class="civic-btn">Start a Hub</a>
    </div>

    <style>
        @media (max-width: 600px) {
            .civic-groups-header {
                flex-direction: column;
                align-items: stretch !important;
            }
            .civic-groups-header .civic-btn {
                text-align: center;
            }
        }
    </style>

    <?php if (empty($groups)): ?>
        <p>No hubs found.</p>
    <?php else: ?>
        <div class="civic-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 50px;">
            <?php foreach ($groups as $group): ?>
                <?php
                // Safe Data
                $gName = htmlspecialchars($group['name']);
                $gDesc = htmlspecialchars(substr($group['description'] ?? '', 0, 100)) . '...';
                $hasImg = !empty($group['image_path']);
                ?>
                <div class="civic-card group-card" style="
                    display: flex; 
                    flex-direction: column; 
                    align-items: center; 
                    text-align: center; 
                    padding: 0; 
                    background: var(--civic-bg-card, #f7f7f7); 
                    border: none; 
                    border-radius: 6px; 
                    overflow: hidden;
                    margin-bottom: 0;
                    box-shadow: none;
                ">
                    <!-- Image Section -->
                    <div style="padding: 30px 20px 10px 20px; width: 100%; display: flex; justify-content: center;">
                        <?php if ($hasImg): ?>
                            <img src="<?= $group['image_path'] ?>" alt="<?= $gName ?>" style="
                                width: 130px; 
                                height: 130px; 
                                border-radius: 50%; 
                                object-fit: cover; 
                                border: 4px solid var(--civic-bg-page, #ffffff);
                                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                                background: white;
                            ">
                        <?php else: ?>
                            <!-- SVG Placeholder (Accessible & Robust) -->
                            <div style="
                                width: 130px; 
                                height: 130px; 
                                border-radius: 50%; 
                                border: 4px solid var(--civic-bg-page, #ffffff);
                                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                                background: #e5e7eb;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: #9ca3af;
                            ">
                                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content Section -->
                    <div style="padding: 10px 20px 30px 20px; width: 100%; flex-grow: 1; display: flex; flex-direction: column;">
                        <h3 style="
                            margin: 0 0 5px 0; 
                            font-size: 1.1rem; 
                            font-weight: 600; 
                            color: var(--civic-brand, #00796B);
                        ">
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>" style="text-decoration: none; color: inherit;">
                                <?= $gName ?>
                            </a>
                        </h3>

                        <p style="
                            margin: 0 0 20px 0; 
                            font-size: 0.9rem; 
                            color: var(--civic-text-muted, #6B7280);
                            flex-grow: 1;
                        "><?= $gDesc ?></p>

                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>" class="civic-btn" style="
                            display: inline-block;
                            width: auto;
                            padding: 8px 24px;
                            background: transparent;
                            color: var(--civic-brand, #00796B);
                            border: 1px solid var(--civic-brand, #00796B);
                            border-radius: 30px;
                            font-weight: 600;
                            font-size: 0.9rem;
                            text-decoration: none;
                            transition: all 0.2s;
                            align-self: center;
                        ">Visit Hub</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>