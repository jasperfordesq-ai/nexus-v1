<?php
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Create Deliverable';
$adminPageSubtitle = 'Deliverability Tracking';
$adminPageIcon = 'fa-plus';

require dirname(dirname(__DIR__)) . '/admin-legacy/partials/admin-header.php';

$users = $users ?? [];
$groups = $groups ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-plus"></i>
            Create New Deliverable
        </h1>
        <p class="admin-page-subtitle">Define a new project deliverable</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/deliverability/list" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<!-- Form Card - Enhanced with FDS Gold styling -->
<div class="admin-glass-card" style="max-width: 900px; margin: 0 auto; border: 1px solid rgba(99, 102, 241, 0.25); box-shadow: 0 8px 32px rgba(99, 102, 241, 0.15);">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);">
            <i class="fa-solid fa-file-plus"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title" style="font-size: 1.5rem; letter-spacing: -0.02em;">Deliverable Details</h3>
            <p class="admin-card-subtitle">Fill in the information below to create a new deliverable</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin-legacy/deliverability/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

            <!-- Title -->
            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" required placeholder="e.g., Build user authentication system">
                <small>Enter a clear, descriptive title for this deliverable</small>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"
                          placeholder="Provide detailed information about what needs to be delivered..."></textarea>
                <small>Explain the scope, requirements, and any important details</small>
            </div>

            <!-- Two Column Layout -->
            <div class="form-row">
                <!-- Category -->
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" placeholder="e.g., development, design"
                           value="general">
                    <small>Categorize this deliverable</small>
                </div>

                <!-- Priority -->
                <div class="form-group">
                    <label for="priority">Priority *</label>
                    <select id="priority" name="priority" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
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
                            <option value="<?= $user['id'] ?>">
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
                            <option value="<?= $group['id'] ?>">
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
                    <input type="date" id="start_date" name="start_date">
                    <small>When should work begin?</small>
                </div>

                <!-- Due Date -->
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date">
                    <small>Deadline for completion</small>
                </div>
            </div>

            <!-- Status and Hours Row -->
            <div class="form-row">
                <!-- Initial Status -->
                <div class="form-group">
                    <label for="status">Initial Status</label>
                    <select id="status" name="status">
                        <option value="draft" selected>Draft</option>
                        <option value="ready">Ready</option>
                        <option value="in_progress">In Progress</option>
                    </select>
                    <small>Current state of this deliverable</small>
                </div>

                <!-- Estimated Hours -->
                <div class="form-group">
                    <label for="estimated_hours">Estimated Hours</label>
                    <input type="number" id="estimated_hours" name="estimated_hours"
                           min="0" step="0.5" placeholder="e.g., 40">
                    <small>Estimated time to complete</small>
                </div>
            </div>

            <!-- Tags -->
            <div class="form-group">
                <label for="tags">Tags</label>
                <input type="text" id="tags" name="tags" placeholder="backend, api, authentication">
                <small>Comma-separated tags for organization (e.g., backend, urgent, v2.0)</small>
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
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                        <small>Confidence in on-time delivery</small>
                    </div>

                    <!-- Risk Level -->
                    <div class="form-group">
                        <label for="risk_level">Risk Level</label>
                        <select id="risk_level" name="risk_level">
                            <option value="low" selected>Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <small>Overall risk assessment</small>
                    </div>
                </div>

                <!-- Risk Notes -->
                <div class="form-group">
                    <label for="risk_notes">Risk Notes</label>
                    <textarea id="risk_notes" name="risk_notes" rows="3"
                              placeholder="Describe any risks, dependencies, or blockers..."></textarea>
                    <small>Document potential risks and mitigation strategies</small>
                </div>
            </div>

            <!-- Form Actions - Enhanced with gradients -->
            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(99,102,241,0.15);">
                <a href="<?= $basePath ?>/admin-legacy/deliverability/list"
                   class="admin-btn admin-btn-secondary"
                   style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(99, 102, 241, 0.25); transition: all 0.25s ease;"
                   onmouseover="this.style.background='rgba(255, 255, 255, 0.12)'; this.style.borderColor='rgba(99, 102, 241, 0.4)';"
                   onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'; this.style.borderColor='rgba(99, 102, 241, 0.25)';">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit"
                        class="admin-btn admin-btn-primary"
                        style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: 1px solid rgba(99, 102, 241, 0.5); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4); transition: all 0.25s ease;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(99, 102, 241, 0.5)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(99, 102, 241, 0.4)';">
                    <i class="fa-solid fa-check"></i> Create Deliverable
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
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1), 0 4px 12px rgba(99, 102, 241, 0.2);
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
    transition: all 0.25s ease;
}
</style>

<!-- Help Card -->
<div class="admin-glass-card" style="max-width: 900px; margin: 24px auto 0;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-circle-info"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Quick Tips</h3>
        </div>
    </div>
    <div class="admin-card-body">
        <ul style="margin: 0; padding-left: 20px; color: rgba(255,255,255,0.7); line-height: 1.8;">
            <li><strong>Title:</strong> Use clear, action-oriented titles (e.g., "Build", "Design", "Implement")</li>
            <li><strong>Priority:</strong> Reserve "Urgent" for true emergencies requiring immediate action</li>
            <li><strong>Assignment:</strong> Assign to either a user OR a group, not both</li>
            <li><strong>Tags:</strong> Use consistent tags across deliverables for better organization</li>
            <li><strong>Risk Assessment:</strong> Update risk levels as the project progresses</li>
        </ul>
    </div>
</div>

<?php require dirname(dirname(__DIR__)) . '/admin-legacy/partials/admin-footer.php'; ?>
