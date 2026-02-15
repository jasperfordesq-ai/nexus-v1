<?php
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Edit Deliverable';
$adminPageSubtitle = 'Deliverability Tracking';
$adminPageIcon = 'fa-pen';

require dirname(dirname(__DIR__)) . '/admin-legacy/partials/admin-header.php';

$deliverable = $deliverable ?? [];
$users = $users ?? [];
$groups = $groups ?? [];

// Convert tags array to string
$tagsString = !empty($deliverable['tags']) && is_array($deliverable['tags'])
    ? implode(', ', $deliverable['tags'])
    : '';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-pen"></i>
            Edit Deliverable
        </h1>
        <p class="admin-page-subtitle"><?= htmlspecialchars($deliverable['title'] ?? '') ?></p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/deliverability/view/<?= $deliverable['id'] ?>" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to View
        </a>
    </div>
</div>

<!-- Form Card - Enhanced with FDS Gold styling -->
<div class="admin-glass-card" style="max-width: 900px; margin: 0 auto; border: 1px solid rgba(6, 182, 212, 0.25); box-shadow: 0 8px 32px rgba(6, 182, 212, 0.15);">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.4);">
            <i class="fa-solid fa-file-pen"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title" style="font-size: 1.5rem; letter-spacing: -0.02em;">Deliverable Details</h3>
            <p class="admin-card-subtitle">Update the information below • ID #<?= $deliverable['id'] ?></p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin-legacy/deliverability/update/<?= $deliverable['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

            <!-- Title -->
            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" required
                       value="<?= htmlspecialchars($deliverable['title'] ?? '') ?>">
                <small>Enter a clear, descriptive title for this deliverable</small>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($deliverable['description'] ?? '') ?></textarea>
                <small>Explain the scope, requirements, and any important details</small>
            </div>

            <!-- Two Column Layout -->
            <div class="form-row">
                <!-- Category -->
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category"
                           value="<?= htmlspecialchars($deliverable['category'] ?? 'general') ?>">
                    <small>Categorize this deliverable</small>
                </div>

                <!-- Priority -->
                <div class="form-group">
                    <label for="priority">Priority *</label>
                    <select id="priority" name="priority" required>
                        <option value="low" <?= ($deliverable['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($deliverable['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($deliverable['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="urgent" <?= ($deliverable['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                    <small>Set the priority level</small>
                </div>
            </div>

            <!-- Assignment Row -->
            <div class="form-row">
                <!-- Assigned To (User) -->
                <div class="form-group">
                    <label for="assigned_to">Assign to User</label>
                    <select id="assigned_to" name="assigned_to">
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"
                                    <?= ($deliverable['assigned_to'] ?? '') == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Assign to an individual user</small>
                </div>

                <!-- Assigned To (Group) -->
                <div class="form-group">
                    <label for="assigned_group_id">Assign to Group</label>
                    <select id="assigned_group_id" name="assigned_group_id">
                        <option value="">-- Select Group --</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= $group['id'] ?>"
                                    <?= ($deliverable['assigned_group_id'] ?? '') == $group['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Or assign to a group</small>
                </div>
            </div>

            <!-- Dates Row -->
            <div class="form-row">
                <!-- Start Date -->
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date"
                           value="<?= !empty($deliverable['start_date']) ? date('Y-m-d', strtotime($deliverable['start_date'])) : '' ?>">
                    <small>When should work begin?</small>
                </div>

                <!-- Due Date -->
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date"
                           value="<?= !empty($deliverable['due_date']) ? date('Y-m-d', strtotime($deliverable['due_date'])) : '' ?>">
                    <small>Deadline for completion</small>
                </div>
            </div>

            <!-- Status and Hours Row -->
            <div class="form-row">
                <!-- Status -->
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= ($deliverable['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="ready" <?= ($deliverable['status'] ?? '') === 'ready' ? 'selected' : '' ?>>Ready</option>
                        <option value="in_progress" <?= ($deliverable['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="blocked" <?= ($deliverable['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                        <option value="review" <?= ($deliverable['status'] ?? '') === 'review' ? 'selected' : '' ?>>Review</option>
                        <option value="completed" <?= ($deliverable['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= ($deliverable['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="on_hold" <?= ($deliverable['status'] ?? '') === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                    </select>
                    <small>Current state of this deliverable</small>
                </div>

                <!-- Estimated Hours -->
                <div class="form-group">
                    <label for="estimated_hours">Estimated Hours</label>
                    <input type="number" id="estimated_hours" name="estimated_hours"
                           min="0" step="0.5"
                           value="<?= htmlspecialchars($deliverable['estimated_hours'] ?? '') ?>">
                    <small>Estimated time to complete</small>
                </div>
            </div>

            <!-- Actual Hours (only show if in progress or completed) -->
            <?php if (in_array($deliverable['status'] ?? '', ['in_progress', 'review', 'completed'])): ?>
            <div class="form-group">
                <label for="actual_hours">Actual Hours Spent</label>
                <input type="number" id="actual_hours" name="actual_hours"
                       min="0" step="0.5"
                       value="<?= htmlspecialchars($deliverable['actual_hours'] ?? '') ?>">
                <small>Actual time spent on this deliverable</small>
            </div>
            <?php endif; ?>

            <!-- Tags -->
            <div class="form-group">
                <label for="tags">Tags</label>
                <input type="text" id="tags" name="tags"
                       value="<?= htmlspecialchars($tagsString) ?>">
                <small>Comma-separated tags for organization</small>
            </div>

            <!-- Risk Assessment Section -->
            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(99,102,241,0.2);">
                <h4 style="color: #fff; margin-bottom: 16px; font-size: 16px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Risk Assessment
                </h4>

                <div class="form-row">
                    <!-- Delivery Confidence -->
                    <div class="form-group">
                        <label for="delivery_confidence">Delivery Confidence</label>
                        <select id="delivery_confidence" name="delivery_confidence">
                            <option value="low" <?= ($deliverable['delivery_confidence'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= ($deliverable['delivery_confidence'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= ($deliverable['delivery_confidence'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                        </select>
                        <small>Confidence in on-time delivery</small>
                    </div>

                    <!-- Risk Level -->
                    <div class="form-group">
                        <label for="risk_level">Risk Level</label>
                        <select id="risk_level" name="risk_level">
                            <option value="low" <?= ($deliverable['risk_level'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= ($deliverable['risk_level'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= ($deliverable['risk_level'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="critical" <?= ($deliverable['risk_level'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                        <small>Overall risk assessment</small>
                    </div>
                </div>

                <!-- Risk Notes -->
                <div class="form-group">
                    <label for="risk_notes">Risk Notes</label>
                    <textarea id="risk_notes" name="risk_notes" rows="3"><?= htmlspecialchars($deliverable['risk_notes'] ?? '') ?></textarea>
                    <small>Document potential risks and mitigation strategies</small>
                </div>
            </div>

            <!-- Form Actions - Enhanced with gradients -->
            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(6,182,212,0.15);">
                <a href="<?= $basePath ?>/admin-legacy/deliverability/view/<?= $deliverable['id'] ?>"
                   class="admin-btn admin-btn-secondary"
                   style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(6, 182, 212, 0.25); transition: all 0.25s ease;"
                   onmouseover="this.style.background='rgba(255, 255, 255, 0.12)'; this.style.borderColor='rgba(6, 182, 212, 0.4)';"
                   onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(6, 182, 212, 0.25)';">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit"
                        class="admin-btn admin-btn-primary"
                        style="background: linear-gradient(135deg, #06b6d4, #22d3ee); border: 1px solid rgba(6, 182, 212, 0.5); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.4); transition: all 0.25s ease;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(6, 182, 212, 0.5)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(6, 182, 212, 0.4)';">
                    <i class="fa-solid fa-check"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Enhanced Form Input Styling -->
<style>
.form-group input[type="text"]:focus,
.form-group input[type="number"]:focus,
.form-group input[type="date"]:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: rgba(6, 182, 212, 0.5);
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1), 0 4px 12px rgba(6, 182, 212, 0.2);
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
    transition: all 0.25s ease;
}
</style>

<!-- Delete Section - Enhanced with danger gradient -->
<div class="admin-glass-card" style="max-width: 900px; margin: 24px auto 0; border: 1px solid rgba(239, 68, 68, 0.4); box-shadow: 0 4px 20px rgba(239, 68, 68, 0.15); background: rgba(239, 68, 68, 0.02);">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);">
            <i class="fa-solid fa-trash"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title" style="color: #ef4444; font-size: 1.25rem; letter-spacing: -0.02em;">Danger Zone</h3>
            <p class="admin-card-subtitle">Irreversible actions</p>
        </div>
    </div>
    <div class="admin-card-body">
        <p style="color: rgba(255,255,255,0.7); margin-bottom: 16px;">
            Deleting this deliverable will permanently remove all associated milestones, comments, and history.
            This action cannot be undone.
        </p>
        <form action="<?= $basePath ?>/admin-legacy/deliverability/delete/<?= $deliverable['id'] ?>" method="POST"
              onsubmit="return confirm('⚠️ Are you absolutely sure?\n\nThis will permanently delete:\n• All milestones\n• All comments\n• Complete history\n\nThis CANNOT be undone!');">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <button type="submit"
                    class="admin-btn admin-btn-danger"
                    style="background: linear-gradient(135deg, #ef4444, #dc2626); border: 1px solid rgba(239, 68, 68, 0.5); box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4); transition: all 0.25s ease;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(239, 68, 68, 0.5)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(239, 68, 68, 0.4)';">
                <i class="fa-solid fa-trash"></i> Delete Deliverable Permanently
            </button>
        </form>
    </div>
</div>

<?php require dirname(dirname(__DIR__)) . '/admin-legacy/partials/admin-footer.php'; ?>
