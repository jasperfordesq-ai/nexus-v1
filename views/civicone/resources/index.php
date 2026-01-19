<?php
// CivicOne View: Resources Index - WCAG 2.1 AA Compliant
// CSS extracted to civicone-mini-modules.css
$heroTitle = "Resource Library";
$heroSub = "Tools, guides, and documents for the community.";
$heroType = 'Library';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-module-header">
        <h2>Community Files</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/create" class="civic-btn">+ Upload</a>
    </div>

    <?php if (empty($resources)): ?>
        <div class="civic-card civic-module-empty">
            <p class="civic-module-empty-icon" aria-hidden="true">📚</p>
            <p class="civic-module-empty-title">Library is empty.</p>
            <p class="civic-module-empty-text">Share the first guide or toolkit!</p>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/create" class="civic-btn">Upload Resource</a>
        </div>
    <?php else: ?>
        <div class="civic-module-grid civic-module-grid--cards" role="list">
            <?php foreach ($resources as $res): ?>
                <?php
                $icon = '📄';
                if (strpos($res['file_type'], 'image') !== false) $icon = '🖼️';
                if (strpos($res['file_type'], 'zip') !== false) $icon = '📦';

                $size = round($res['file_size'] / 1024) . ' KB';
                if ($res['file_size'] > 1024 * 1024) $size = round($res['file_size'] / 1024 / 1024, 1) . ' MB';
                ?>
                <article class="civic-card civic-resource-card" role="listitem">
                    <div>
                        <div class="civic-resource-header">
                            <span class="civic-resource-icon" aria-hidden="true"><?= $icon ?></span>
                            <span class="civic-resource-size"><?= $size ?></span>
                        </div>

                        <h3><?= htmlspecialchars($res['title']) ?></h3>
                        <p class="civic-resource-description"><?= htmlspecialchars($res['description']) ?></p>
                    </div>

                    <div>
                        <div class="civic-resource-meta">
                            Uploaded by <strong><?= htmlspecialchars($res['uploader_name']) ?></strong><br>
                            <?= $res['downloads'] ?> downloads
                        </div>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/<?= $res['id'] ?>/download"
                           class="civic-btn civic-resource-download"
                           aria-label="Download <?= htmlspecialchars($res['title']) ?>">Download</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
