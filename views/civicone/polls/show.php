<?php
// CivicOne Poll Detail View
if (session_status() === PHP_SESSION_NONE) session_start();

$hTitle = "Poll";
$hSubtitle = $poll['question'];
$hType = 'Community Poll';

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-container" style="max-width: 700px;">

    <div class="civic-card">

        <?php if (!empty($poll['description'])): ?>
            <p style="font-size: 1.25rem; margin-bottom: 30px; border-left: 5px solid #000; padding-left: 15px; background: #f9f9f9; padding: 15px;">
                <?= htmlspecialchars($poll['description']) ?>
            </p>
        <?php endif; ?>

        <?php if ($hasVoted): ?>
            <!-- RESULTS VIEW -->
            <div style="background: #e6ffFA; border: 2px solid #000; padding: 20px; margin-bottom: 30px;">
                <h3 style="margin-top: 0; color: #006400;">✅ Thank you for voting!</h3>
                <p style="margin-bottom: 0;">Here are the current results:</p>
            </div>

            <div class="poll-results">
                <?php foreach ($options as $opt): ?>
                    <?php
                    $percent = $totalVotes > 0 ? round(($opt['vote_count'] / $totalVotes) * 100) : 0;
                    ?>
                    <div style="margin-bottom: 25px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-weight: 700; font-size: 1.1rem;">
                            <span><?= htmlspecialchars($opt['label']) ?></span>
                            <span><?= $percent ?>% (<?= $opt['vote_count'] ?>)</span>
                        </div>
                        <div style="background: #ccc; height: 30px; border: 1px solid #000; width: 100%;">
                            <div style="background: #000; width: <?= $percent ?>%; height: 100%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top: 30px; border-top: 2px solid #000; padding-top: 15px; text-align: center; font-weight: bold; font-size: 1.2rem;">
                    Total Votes Cast: <?= $totalVotes ?>
                </div>
            </div>

        <?php else: ?>
            <!-- VOTING VIEW -->
            <?php if (!isset($_SESSION['user_id'])): ?>
                <p style="font-size: 1.2rem; text-align: center;">
                    Please <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" style="font-weight: bold;">login</a> to cast your vote.
                </p>
            <?php else: ?>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls/vote" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">

                    <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 40px;">
                        <?php foreach ($options as $opt): ?>
                            <label style="display: flex; align-items: center; gap: 20px; padding: 20px; border: 2px solid #000; cursor: pointer; background: #fff;">
                                <input type="radio" name="option_id" value="<?= $opt['id'] ?>" required style="transform: scale(2); margin-left: 5px;">
                                <span style="font-size: 1.25rem; font-weight: 600;"><?= htmlspecialchars($opt['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="civic-btn" style="width: 100%; font-size: 1.3rem; padding: 20px;">
                        Submit My Vote
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Social Interactions -->
        <?php
        $targetType = 'poll';
        $targetId = $poll['id'];
        include dirname(__DIR__) . '/partials/social_interactions.php';
        ?>

    </div>

</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>