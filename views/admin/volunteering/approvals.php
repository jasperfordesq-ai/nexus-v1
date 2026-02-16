<?php

// Admin: Volunteering Approvals
$pageTitle = "Organization Approvals";
require __DIR__ . '/../../layouts/admin-header.php';
?>

<div class="admin-header">
    <h1>Organization Approvals</h1>
    <p>Review and verify new organizations before they can post publicly.</p>
</div>

<div class="admin-card">
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
        <div class="alert alert-success">Organization approved successfully.</div>
    <?php endif; ?>

    <?php if (empty($pending)): ?>
        <p style="color: #666; padding: 20px; text-align: center;">No pending requests.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Contact</th>
                    <th>Description</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $org): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($org['name']) ?></strong><br>
                            <small><a href="<?= htmlspecialchars($org['website']) ?>" target="_blank"><?= htmlspecialchars($org['website']) ?></a></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($org['contact_email']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(substr($org['description'], 0, 100)) ?>...
                        </td>
                        <td>
                            <form action="/admin-legacy/volunteering/approve" method="POST" style="display:inline-block;">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                <button class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form action="/admin-legacy/volunteering/decline" method="POST" style="display:inline-block; margin-left: 5px;">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                <button class="btn btn-sm btn-danger" onclick="return confirm('Reject this organization?');">Decline</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layouts/admin-footer.php'; ?>