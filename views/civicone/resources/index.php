<?php
// CivicOne View: Resources Index
$heroTitle = "Resource Library";
$heroSub = "Tools, guides, and documents for the community.";
$heroType = 'Library';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #000; padding-bottom: 10px; margin-bottom: 30px;">
        <h2 style="margin: 0; text-transform: uppercase; letter-spacing: 1px;">Community Files</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/create" class="civic-btn" style="padding: 10px 20px;">+ Upload</a>
    </div>

    <?php if (empty($resources)): ?>
        <div class="civic-card" style="text-align: center; padding: 40px;">
            <p style="font-size: 1.5rem; margin-bottom: 10px;">📚 Library is empty.</p>
            <p style="margin-bottom: 20px;">Share the first guide or toolkit!</p>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/create" class="civic-btn">Upload Resource</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
            <?php foreach ($resources as $res): ?>
                <?php
                $icon = '📄';
                if (strpos($res['file_type'], 'image') !== false) $icon = '🖼️';
                if (strpos($res['file_type'], 'zip') !== false) $icon = '📦';

                $size = round($res['file_size'] / 1024) . ' KB';
                if ($res['file_size'] > 1024 * 1024) $size = round($res['file_size'] / 1024 / 1024, 1) . ' MB';
                ?>
                <div class="civic-card" style="display: flex; flex-direction: column; justify-content: space-between;">

                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                            <span style="font-size: 2.5rem;"><?= $icon ?></span>
                            <span style="background: #000; color: #fff; padding: 4px 8px; font-weight: bold; border-radius: 4px; font-size: 0.8rem;">
                                <?= $size ?>
                            </span>
                        </div>

                        <h3 style="margin: 0 0 10px; font-size: 1.3rem;">
                            <?= htmlspecialchars($res['title']) ?>
                        </h3>
                        <p style="font-size: 1rem; line-height: 1.4; margin-bottom: 10px;">
                            <?= htmlspecialchars($res['description']) ?>
                        </p>
                    </div>

                    <div style="margin-top: auto;">
                        <div style="font-size: 0.85rem; border-top: 1px solid #ccc; padding-top: 10px; margin-bottom: 15px;">
                            Uploaded by <strong><?= htmlspecialchars($res['uploader_name']) ?></strong><br>
                            <?= $res['downloads'] ?> downloads
                        </div>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/<?= $res['id'] ?>/download" class="civic-btn" style="width: 100%; display: block; text-align: center;">Download</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>