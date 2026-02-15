<?php
// views/admin-legacy/partials/analytics_chart.php

// Data expected: $monthly_stats (array of ['month' => '2023-10', 'volume' => 120])
$data = $monthly_stats ?? [];

// Config
$height = 200;
$width = 600;
$padding = 20;
$barWidth = 40;
$gap = 20;

// Calculate Max for Scaling
$maxVol = 0;
foreach ($data as $d) {
    if ($d['volume'] > $maxVol) $maxVol = $d['volume'];
}
$maxVol = $maxVol > 0 ? $maxVol : 10; // Prevent div by zero

// Branding Colors
$color = '#002d72'; // Government Blue

?>
<div class="glass-panel" style="margin-bottom: 20px;">
    <header style="display:flex; justify-content:space-between; align-items:center;">
        <h3 style="margin:0; color: #002d72;">Community Hours Exchanged</h3>
        <span style="font-size:0.8rem; color:#666;">Last 6 Months</span>
    </header>

    <div style="width:100%; overflow-x:auto;">
        <svg width="100%" height="<?= $height + 40 ?>" viewBox="0 0 <?= $width ?> <?= $height + 40 ?>" preserveAspectRatio="none" role="img" aria-label="Bar chart showing monthly hours exchanged">
            <!-- Axis lines -->
            <line x1="<?= $padding ?>" y1="<?= $height ?>" x2="<?= $width ?>" y2="<?= $height ?>" stroke="#ccc" stroke-width="1" />

            <?php
            $x = $padding + 20;
            foreach ($data as $d):
                $h = ($d['volume'] / $maxVol) * ($height - 20);
                $y = $height - $h;
                $label = date('M', strtotime($d['month'] . '-01'));
            ?>
                <!-- Bar -->
                <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $barWidth ?>" height="<?= $h ?>" fill="<?= $color ?>" rx="4">
                    <title><?= $label ?>: <?= $d['volume'] ?> Hours</title>
                    <animate attributeName="height" from="0" to="<?= $h ?>" dur="0.8s" fill="freeze" />
                    <animate attributeName="y" from="<?= $height ?>" to="<?= $y ?>" dur="0.8s" fill="freeze" />
                </rect>

                <!-- Label -->
                <text x="<?= $x + ($barWidth / 2) ?>" y="<?= $height + 20 ?>" font-family="sans-serif" font-size="12" text-anchor="middle" fill="#666"><?= $label ?></text>

                <!-- Value -->
                <text x="<?= $x + ($barWidth / 2) ?>" y="<?= $y - 5 ?>" font-family="sans-serif" font-size="12" font-weight="bold" text-anchor="middle" fill="#333"><?= (int)$d['volume'] ?></text>

                <?php $x += $barWidth + $gap; ?>
            <?php endforeach; ?>

            <?php if (empty($data)): ?>
                <text x="<?= $width / 2 ?>" y="<?= $height / 2 ?>" text-anchor="middle" fill="#999">No Data Available</text>
            <?php endif; ?>
        </svg>
    </div>
</div>