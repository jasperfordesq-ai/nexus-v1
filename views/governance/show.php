<?php include __DIR__ . '/../layouts/modern/header.php'; ?>

<main class="htb-container-full htb-section">

    <div class="htb-card" style="max-width: 800px; margin: 0 auto; padding: 40px;">

        <!-- Header -->
        <span class="htb-badge" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
            Proposal #<?= $proposal['id'] ?> • <?= htmlspecialchars($proposal['asgroup_name'] ?? 'General') ?>
        </span>

        <h1 style="margin-top: 20px; font-size: 2.5rem; color: var(--htb-text-main);">
            <?= htmlspecialchars($proposal['title']) ?>
        </h1>

        <div style="margin-top: 10px; color: var(--htb-text-muted); display: flex; gap: 20px; font-size: 0.9rem;">
            <span><i class="dashicons dashicons-admin-users"></i> Proposed by <?= htmlspecialchars($proposal['author_name']) ?></span>
            <span><i class="dashicons dashicons-calendar"></i> <?= date('M j, Y', strtotime($proposal['created_at'])) ?></span>
            <?php if ($proposal['deadline']): ?>
                <span><i class="dashicons dashicons-clock"></i> Ends <?= date('M j', strtotime($proposal['deadline'])) ?></span>
            <?php endif; ?>
        </div>

        <hr style="border-color: rgba(255,255,255,0.1); margin: 30px 0;">

        <!-- Description -->
        <div style="font-size: 1.1rem; line-height: 1.6; color: var(--htb-text-main);">
            <?= nl2br(htmlspecialchars($proposal['description'])) ?>
        </div>

        <div style="margin-top: 50px;"></div>

        <!-- Voting Section -->
        <h3 style="margin-bottom: 20px;">Current Standings</h3>

        <!-- Progress Bars -->
        <?php
        $yesPct = $totalVotes > 0 ? round(($stats['yes'] / $totalVotes) * 100) : 0;
        $noPct = $totalVotes > 0 ? round(($stats['no'] / $totalVotes) * 100) : 0;
        // Abstain handled visually but maybe not main bar
        ?>

        <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px;">

            <!-- YES -->
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong>In Favor</strong>
                    <span><?= $stats['yes'] ?> votes (<?= $yesPct ?>%)</span>
                </div>
                <div style="height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; overflow: hidden;">
                    <div style="height: 100%; width: <?= $yesPct ?>%; background: #4caf50; transition: width 1s ease;"></div>
                </div>
            </div>

            <!-- NO -->
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong>Against</strong>
                    <span><?= $stats['no'] ?> votes (<?= $noPct ?>%)</span>
                </div>
                <div style="height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; overflow: hidden;">
                    <div style="height: 100%; width: <?= $noPct ?>%; background: #f44336; transition: width 1s ease;"></div>
                </div>
            </div>

            <div style="text-align: center; color: #888; font-size: 0.9rem;">
                Total Votes: <?= $totalVotes ?> • Abstentions: <?= $stats['abstain'] ?>
            </div>
        </div>

        <!-- Action Area -->
        <div style="margin-top: 40px; text-align: center;">
            <?php if ($userVote): ?>
                <div style="padding: 20px; background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 10px; color: #81c784;">
                    <span class="dashicons dashicons-yes-alt"></span> You voted <strong><?= strtoupper($userVote) ?></strong> on this proposal.
                </div>
            <?php else: ?>
                <h4 style="margin-bottom: 20px;">Cast Your Vote</h4>
                <form action="/proposals/vote" method="POST" style="display: flex; gap: 10px; justify-content: center;">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="proposal_id" value="<?= $proposal['id'] ?>">

                    <button name="choice" value="yes" class="htb-btn" style="background: #4caf50; border: none; flex: 1; max-width: 150px;">
                        Vote YES
                    </button>
                    <button name="choice" value="no" class="htb-btn" style="background: #f44336; border: none; flex: 1; max-width: 150px;">
                        Vote NO
                    </button>
                    <button name="choice" value="abstain" class="htb-btn" style="background: #999; border: none; flex: 1; max-width: 150px;">
                        Abstain
                    </button>
                </form>
            <?php endif; ?>
        </div>

    </div>

</main>

<?php include __DIR__ . '/../layouts/modern/footer.php'; ?>