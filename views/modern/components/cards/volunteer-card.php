<?php

/**
 * Component: Volunteer Card
 *
 * Card for displaying volunteer opportunities.
 *
 * @param array $opportunity Opportunity data with keys: id, title, description, organization, location, skills, shifts, applications_count
 * @param bool $showApply Show apply button (default: true)
 * @param bool $showSkills Show required skills (default: true)
 * @param bool $showOrg Show organization info (default: true)
 * @param string $class Additional CSS classes
 * @param string $baseUrl Base URL for opportunity links (default: '')
 */

$opportunity = $opportunity ?? [];
$showApply = $showApply ?? true;
$showSkills = $showSkills ?? true;
$showOrg = $showOrg ?? true;
$class = $class ?? '';
$baseUrl = $baseUrl ?? '';

// Extract opportunity data with defaults
$id = $opportunity['id'] ?? 0;
$title = $opportunity['title'] ?? 'Untitled Opportunity';
$description = $opportunity['description'] ?? '';
$organization = $opportunity['organization'] ?? [];
$location = $opportunity['location'] ?? '';
$skills = $opportunity['skills'] ?? [];
$shifts = $opportunity['shifts'] ?? [];
$shiftsCount = $opportunity['shifts_count'] ?? count($shifts);
$applicationsCount = $opportunity['applications_count'] ?? 0;
$hasApplied = $opportunity['has_applied'] ?? false;
$status = $opportunity['status'] ?? 'open'; // 'open', 'filled', 'closed'

$opportunityUrl = $baseUrl . '/volunteering/' . $id;
$cssClass = trim('glass-volunteer-card vol-card ' . $class);
?>

<article class="<?= e($cssClass) ?>">
    <div class="vol-card-header">
        <?php if ($showOrg && !empty($organization)): ?>
            <div class="vol-org-badge">
                <?php if (!empty($organization['logo'])): ?>
                    <?= webp_image($organization['logo'], $organization['name'] ?? '', 'vol-org-logo') ?>
                <?php else: ?>
                    <div class="vol-org-initial"><?= e(substr($organization['name'] ?? 'O', 0, 1)) ?></div>
                <?php endif; ?>
                <span class="vol-org-name"><?= e($organization['name'] ?? 'Organization') ?></span>
            </div>
        <?php endif; ?>

        <?php if ($status !== 'open'): ?>
            <span class="vol-status-badge vol-status-<?= e($status) ?>">
                <?= ucfirst(e($status)) ?>
            </span>
        <?php endif; ?>
    </div>

    <h3 class="vol-card-title">
        <a href="<?= e($opportunityUrl) ?>"><?= e($title) ?></a>
    </h3>

    <?php if ($description): ?>
        <p class="vol-card-description"><?= e(mb_strimwidth(strip_tags($description), 0, 120, '...')) ?></p>
    <?php endif; ?>

    <?php if ($location): ?>
        <div class="vol-card-location">
            <i class="fa-solid fa-location-dot"></i>
            <span><?= e($location) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($showSkills && !empty($skills)): ?>
        <div class="vol-skills">
            <?php
            $displaySkills = array_slice($skills, 0, 3);
            foreach ($displaySkills as $skill):
            ?>
                <span class="vol-skill-tag"><?= e(is_array($skill) ? $skill['name'] : $skill) ?></span>
            <?php endforeach; ?>
            <?php if (count($skills) > 3): ?>
                <span class="vol-skill-tag vol-skill-more">+<?= count($skills) - 3 ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="vol-card-footer">
        <div class="vol-card-meta">
            <?php if ($shiftsCount > 0): ?>
                <span class="vol-shifts-count">
                    <i class="fa-solid fa-clock"></i>
                    <?= $shiftsCount ?> shift<?= $shiftsCount !== 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
            <?php if ($applicationsCount > 0): ?>
                <span class="vol-applications-count">
                    <i class="fa-solid fa-users"></i>
                    <?= $applicationsCount ?> applicant<?= $applicationsCount !== 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($showApply && $status === 'open'): ?>
            <?php if ($hasApplied): ?>
                <span class="vol-applied-badge">
                    <i class="fa-solid fa-check"></i> Applied
                </span>
            <?php else: ?>
                <a href="<?= e($opportunityUrl) ?>" class="vol-apply-btn">
                    <i class="fa-solid fa-hand-holding-heart"></i> Apply
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</article>
