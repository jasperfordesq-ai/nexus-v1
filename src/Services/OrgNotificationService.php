<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;
use Nexus\Models\User;
use Nexus\Models\OrgMember;
use Nexus\Models\VolOrganization;

/**
 * OrgNotificationService
 *
 * Handles platform (bell) and email notifications for organization-related events:
 * - Wallet transfers (payments, deposits, requests)
 * - Membership changes (added, removed, role changes)
 * - Transfer request approvals/rejections
 * - Organization creation by admin
 */
class OrgNotificationService
{
    /**
     * Notify recipient when they receive a payment from org wallet
     */
    public static function notifyPaymentReceived($recipientId, $organizationId, $amount, $description = '', $senderId = null)
    {
        try {
            $recipient = User::findById($recipientId);
            $org = VolOrganization::find($organizationId);
            if (!$recipient || !$org) return;

            $orgName = $org['name'];
            $formattedAmount = number_format($amount, 2);
            $message = "You received $formattedAmount credits from {$orgName}";
            if ($description) {
                $message .= ": " . (strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description);
            }

            $basePath = TenantContext::getBasePath();
            $link = $basePath . '/wallet';

            // Platform notification
            self::createNotification($recipientId, $message, $link, 'org_payment');

            // Email notification
            if (self::shouldSendEmail($recipientId, 'org_payment')) {
                self::sendEmail(
                    $recipient,
                    "Payment Received from {$orgName}",
                    "You've received credits from an organization",
                    "<strong>Amount:</strong> {$formattedAmount} credits<br><br>" .
                    "<strong>From:</strong> {$orgName}<br><br>" .
                    ($description ? "<strong>Description:</strong> " . htmlspecialchars($description) : ""),
                    "View Wallet",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyPaymentReceived error: " . $e->getMessage());
        }
    }

    /**
     * Notify admins when a member deposits credits to org wallet
     */
    public static function notifyDepositReceived($organizationId, $depositorId, $amount, $description = '')
    {
        try {
            $depositor = User::findById($depositorId);
            $org = VolOrganization::find($organizationId);
            if (!$depositor || !$org) return;

            $depositorName = $depositor['name'] ?? 'A member';
            $orgName = $org['name'];
            $formattedAmount = number_format($amount, 2);
            $message = "{$depositorName} deposited {$formattedAmount} credits to {$orgName} wallet";

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/organizations/{$organizationId}/wallet";

            // Notify all admins/owners
            $admins = OrgMember::getAdminsAndOwners($organizationId);
            foreach ($admins as $admin) {
                if ($admin['user_id'] == $depositorId) continue; // Don't notify self

                self::createNotification($admin['user_id'], $message, $link, 'org_deposit');

                $adminUser = User::findById($admin['user_id']);
                if ($adminUser && self::shouldSendEmail($admin['user_id'], 'org_deposit')) {
                    self::sendEmail(
                        $adminUser,
                        "Deposit Received - {$orgName}",
                        "{$depositorName} made a deposit",
                        "<strong>Amount:</strong> {$formattedAmount} credits<br><br>" .
                        "<strong>From:</strong> {$depositorName}<br><br>" .
                        ($description ? "<strong>Description:</strong> " . htmlspecialchars($description) : ""),
                        "View Wallet",
                        $link
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyDepositReceived error: " . $e->getMessage());
        }
    }

    /**
     * Notify admins when a transfer request is created
     */
    public static function notifyTransferRequestCreated($organizationId, $requesterId, $recipientId, $amount, $description = '')
    {
        try {
            $requester = User::findById($requesterId);
            $recipient = User::findById($recipientId);
            $org = VolOrganization::find($organizationId);
            if (!$requester || !$recipient || !$org) return;

            $requesterName = $requester['name'] ?? 'A member';
            $recipientName = $recipient['name'] ?? 'a member';
            $orgName = $org['name'];
            $formattedAmount = number_format($amount, 2);

            $isSelfRequest = ($requesterId === $recipientId);
            $message = $isSelfRequest
                ? "{$requesterName} requested {$formattedAmount} credits from {$orgName}"
                : "{$requesterName} requested {$formattedAmount} credits for {$recipientName} from {$orgName}";

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/organizations/{$organizationId}/wallet";

            // Notify all admins/owners
            $admins = OrgMember::getAdminsAndOwners($organizationId);
            foreach ($admins as $admin) {
                if ($admin['user_id'] == $requesterId) continue; // Don't notify requester

                self::createNotification($admin['user_id'], $message, $link, 'org_transfer_request');

                $adminUser = User::findById($admin['user_id']);
                if ($adminUser && self::shouldSendEmail($admin['user_id'], 'org_transfer_request')) {
                    self::sendEmail(
                        $adminUser,
                        "Transfer Request - {$orgName}",
                        "A new transfer request needs your approval",
                        "<strong>Amount:</strong> {$formattedAmount} credits<br><br>" .
                        "<strong>Requested by:</strong> {$requesterName}<br><br>" .
                        "<strong>Recipient:</strong> {$recipientName}<br><br>" .
                        ($description ? "<strong>Reason:</strong> " . htmlspecialchars($description) : ""),
                        "Review Request",
                        $link
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyTransferRequestCreated error: " . $e->getMessage());
        }
    }

    /**
     * Notify requester when their transfer request is approved
     */
    public static function notifyTransferRequestApproved($requesterId, $recipientId, $organizationId, $amount, $approverId)
    {
        try {
            $requester = User::findById($requesterId);
            $recipient = User::findById($recipientId);
            $approver = User::findById($approverId);
            $org = VolOrganization::find($organizationId);
            if (!$requester || !$org) return;

            $orgName = $org['name'];
            $formattedAmount = number_format($amount, 2);
            $approverName = $approver['name'] ?? 'An admin';

            $basePath = TenantContext::getBasePath();

            // Notify requester
            $message = "Your transfer request for {$formattedAmount} credits from {$orgName} was approved";
            $link = $basePath . "/organizations/{$organizationId}/wallet";

            self::createNotification($requesterId, $message, $link, 'org_request_approved');

            if (self::shouldSendEmail($requesterId, 'org_request_approved')) {
                self::sendEmail(
                    $requester,
                    "Transfer Request Approved - {$orgName}",
                    "Your request has been approved",
                    "<strong>Amount:</strong> {$formattedAmount} credits<br><br>" .
                    "<strong>Approved by:</strong> {$approverName}<br><br>" .
                    "The credits have been transferred.",
                    "View Wallet",
                    $basePath . '/wallet'
                );
            }

            // If recipient is different from requester, notify them too
            if ($recipientId !== $requesterId && $recipient) {
                self::notifyPaymentReceived($recipientId, $organizationId, $amount, 'Transfer request approved', $approverId);
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyTransferRequestApproved error: " . $e->getMessage());
        }
    }

    /**
     * Notify requester when their transfer request is rejected
     */
    public static function notifyTransferRequestRejected($requesterId, $organizationId, $amount, $approverId, $reason = '')
    {
        try {
            $requester = User::findById($requesterId);
            $approver = User::findById($approverId);
            $org = VolOrganization::find($organizationId);
            if (!$requester || !$org) return;

            $orgName = $org['name'];
            $formattedAmount = number_format($amount, 2);
            $approverName = $approver['name'] ?? 'An admin';

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/organizations/{$organizationId}/wallet";

            $message = "Your transfer request for {$formattedAmount} credits from {$orgName} was rejected";
            if ($reason) {
                $message .= ": " . (strlen($reason) > 30 ? substr($reason, 0, 30) . '...' : $reason);
            }

            self::createNotification($requesterId, $message, $link, 'org_request_rejected');

            if (self::shouldSendEmail($requesterId, 'org_request_rejected')) {
                self::sendEmail(
                    $requester,
                    "Transfer Request Rejected - {$orgName}",
                    "Your request was not approved",
                    "<strong>Amount:</strong> {$formattedAmount} credits<br><br>" .
                    "<strong>Reviewed by:</strong> {$approverName}<br><br>" .
                    ($reason ? "<strong>Reason:</strong> " . htmlspecialchars($reason) : "No reason provided."),
                    "View Organization",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyTransferRequestRejected error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when they are added to an organization
     */
    public static function notifyAddedToOrganization($userId, $organizationId, $role = 'member', $addedById = null)
    {
        try {
            $user = User::findById($userId);
            $org = VolOrganization::find($organizationId);
            if (!$user || !$org) return;

            $orgName = $org['name'];
            $roleLabel = ucfirst($role);

            $addedByName = 'An admin';
            if ($addedById) {
                $addedBy = User::findById($addedById);
                $addedByName = $addedBy['name'] ?? 'An admin';
            }

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/volunteering/organization/{$organizationId}";

            $message = "You've been added to {$orgName} as {$roleLabel}";

            self::createNotification($userId, $message, $link, 'org_member_added');

            if (self::shouldSendEmail($userId, 'org_member_added')) {
                self::sendEmail(
                    $user,
                    "Welcome to {$orgName}!",
                    "You've been added to an organization",
                    "<strong>Organization:</strong> {$orgName}<br><br>" .
                    "<strong>Your role:</strong> {$roleLabel}<br><br>" .
                    "<strong>Added by:</strong> {$addedByName}<br><br>" .
                    "You can now participate in this organization's activities.",
                    "View Organization",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyAddedToOrganization error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when they are made owner of a new organization (admin-created)
     */
    public static function notifyOrganizationCreatedForYou($userId, $organizationId, $createdById = null)
    {
        try {
            $user = User::findById($userId);
            $org = VolOrganization::find($organizationId);
            if (!$user || !$org) return;

            $orgName = $org['name'];

            $createdByName = 'A site administrator';
            if ($createdById) {
                $createdBy = User::findById($createdById);
                $createdByName = $createdBy['name'] ?? 'A site administrator';
            }

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/organizations/{$organizationId}/wallet";

            $message = "An organization '{$orgName}' has been created for you!";

            self::createNotification($userId, $message, $link, 'org_created');

            if (self::shouldSendEmail($userId, 'org_created')) {
                self::sendEmail(
                    $user,
                    "Organization Created for You!",
                    "You're now the owner of a new organization",
                    "<strong>Organization:</strong> {$orgName}<br><br>" .
                    "<strong>Your role:</strong> Owner<br><br>" .
                    "<strong>Created by:</strong> {$createdByName}<br><br>" .
                    "As the owner, you can manage members, access the organization wallet, and configure settings.",
                    "View Your Organization",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyOrganizationCreatedForYou error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when their role in an organization changes
     */
    public static function notifyRoleChanged($userId, $organizationId, $newRole, $changedById = null)
    {
        try {
            $user = User::findById($userId);
            $org = VolOrganization::find($organizationId);
            if (!$user || !$org) return;

            $orgName = $org['name'];
            $roleLabel = ucfirst($newRole);

            $changedByName = 'An admin';
            if ($changedById) {
                $changedBy = User::findById($changedById);
                $changedByName = $changedBy['name'] ?? 'An admin';
            }

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/volunteering/organization/{$organizationId}";

            $message = "Your role in {$orgName} has been changed to {$roleLabel}";

            self::createNotification($userId, $message, $link, 'org_role_changed');

            if (self::shouldSendEmail($userId, 'org_role_changed')) {
                self::sendEmail(
                    $user,
                    "Role Updated - {$orgName}",
                    "Your organization role has changed",
                    "<strong>Organization:</strong> {$orgName}<br><br>" .
                    "<strong>New role:</strong> {$roleLabel}<br><br>" .
                    "<strong>Changed by:</strong> {$changedByName}",
                    "View Organization",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyRoleChanged error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when they are removed from an organization
     */
    public static function notifyRemovedFromOrganization($userId, $organizationId, $removedById = null)
    {
        try {
            $user = User::findById($userId);
            $org = VolOrganization::find($organizationId);
            if (!$user || !$org) return;

            $orgName = $org['name'];

            $removedByName = 'An admin';
            if ($removedById) {
                $removedBy = User::findById($removedById);
                $removedByName = $removedBy['name'] ?? 'An admin';
            }

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/volunteering/organizations";

            $message = "You've been removed from {$orgName}";

            self::createNotification($userId, $message, $link, 'org_member_removed');

            if (self::shouldSendEmail($userId, 'org_member_removed')) {
                self::sendEmail(
                    $user,
                    "Removed from {$orgName}",
                    "Your membership has ended",
                    "<strong>Organization:</strong> {$orgName}<br><br>" .
                    "<strong>Removed by:</strong> {$removedByName}<br><br>" .
                    "You are no longer a member of this organization.",
                    "Browse Organizations",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyRemovedFromOrganization error: " . $e->getMessage());
        }
    }

    /**
     * Notify admins when someone requests to join the organization
     */
    public static function notifyMembershipRequestReceived($organizationId, $requesterId)
    {
        try {
            $requester = User::findById($requesterId);
            $org = VolOrganization::find($organizationId);
            if (!$requester || !$org) return;

            $requesterName = $requester['name'] ?? 'Someone';
            $orgName = $org['name'];

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/organizations/{$organizationId}/members";

            $message = "{$requesterName} has requested to join {$orgName}";

            // Notify all admins/owners
            $admins = OrgMember::getAdminsAndOwners($organizationId);
            foreach ($admins as $admin) {
                self::createNotification($admin['user_id'], $message, $link, 'org_membership_request');

                $adminUser = User::findById($admin['user_id']);
                if ($adminUser && self::shouldSendEmail($admin['user_id'], 'org_membership_request')) {
                    self::sendEmail(
                        $adminUser,
                        "Membership Request - {$orgName}",
                        "Someone wants to join your organization",
                        "<strong>Applicant:</strong> {$requesterName}<br><br>" .
                        "<strong>Organization:</strong> {$orgName}<br><br>" .
                        "Please review this membership request.",
                        "Review Request",
                        $link
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyMembershipRequestReceived error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when their membership request is approved
     */
    public static function notifyMembershipApproved($userId, $organizationId, $approvedById = null)
    {
        try {
            $user = User::findById($userId);
            $org = VolOrganization::find($organizationId);
            if (!$user || !$org) return;

            $orgName = $org['name'];

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/volunteering/organization/{$organizationId}";

            $message = "Your request to join {$orgName} has been approved!";

            self::createNotification($userId, $message, $link, 'org_membership_approved');

            if (self::shouldSendEmail($userId, 'org_membership_approved')) {
                self::sendEmail(
                    $user,
                    "Welcome to {$orgName}!",
                    "Your membership request was approved",
                    "<strong>Organization:</strong> {$orgName}<br><br>" .
                    "You are now a member and can participate in this organization's activities.",
                    "View Organization",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyMembershipApproved error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when their membership request is rejected
     */
    public static function notifyMembershipRejected($userId, $organizationId)
    {
        try {
            $user = User::findById($userId);
            $org = VolOrganization::find($organizationId);
            if (!$user || !$org) return;

            $orgName = $org['name'];

            $basePath = TenantContext::getBasePath();
            $link = $basePath . "/volunteering/organizations";

            $message = "Your request to join {$orgName} was not approved";

            self::createNotification($userId, $message, $link, 'org_membership_rejected');

            if (self::shouldSendEmail($userId, 'org_membership_rejected')) {
                self::sendEmail(
                    $user,
                    "Membership Request Update - {$orgName}",
                    "Your request was not approved",
                    "<strong>Organization:</strong> {$orgName}<br><br>" .
                    "Unfortunately, your membership request was not approved at this time.",
                    "Browse Other Organizations",
                    $link
                );
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::notifyMembershipRejected error: " . $e->getMessage());
        }
    }

    /**
     * Create a platform notification
     */
    private static function createNotification($userId, $message, $link, $type)
    {
        try {
            if (class_exists('\Nexus\Models\Notification')) {
                Notification::create($userId, $message, $link, $type);
            }
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::createNotification error: " . $e->getMessage());
        }
    }

    /**
     * Check if user should receive email for this notification type
     *
     * Notification type mapping:
     * - org_payment, org_request_approved, org_request_rejected: email_org_payments
     * - org_transfer_request: email_org_transfers (for requester updates) or email_org_admin (for admins)
     * - org_deposit: email_org_admin
     * - org_member_added, org_created, org_role_changed, org_member_removed: email_org_membership
     * - org_membership_request, org_membership_approved, org_membership_rejected: email_org_membership (member) or email_org_admin (admin)
     */
    private static function shouldSendEmail($userId, $notificationType)
    {
        try {
            // Check user notification preferences
            $prefs = User::getNotificationPreferences($userId);

            // Map notification types to preference keys
            $preferenceMap = [
                // Payment notifications (when you receive credits from org)
                'org_payment' => 'email_org_payments',
                'org_request_approved' => 'email_org_transfers',
                'org_request_rejected' => 'email_org_transfers',

                // Admin notifications (transfer requests, deposits, membership requests)
                'org_transfer_request' => 'email_org_admin',
                'org_deposit' => 'email_org_admin',
                'org_membership_request' => 'email_org_admin',

                // Membership notifications (added, removed, role changes)
                'org_member_added' => 'email_org_membership',
                'org_created' => 'email_org_membership',
                'org_role_changed' => 'email_org_membership',
                'org_member_removed' => 'email_org_membership',
                'org_membership_approved' => 'email_org_membership',
                'org_membership_rejected' => 'email_org_membership',
            ];

            // Get the preference key for this notification type
            $prefKey = $preferenceMap[$notificationType] ?? 'email_org_payments';

            // Check if the specific preference is disabled
            if (isset($prefs[$prefKey]) && !$prefs[$prefKey]) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // Default to sending
            return true;
        }
    }

    /**
     * Send an email notification
     */
    private static function sendEmail($user, $title, $subtitle, $body, $btnText, $btnUrl)
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullUrl = TenantContext::getFrontendUrl() . $btnUrl;

            $html = EmailTemplate::render(
                $title,
                $subtitle,
                $body,
                $btnText,
                $fullUrl,
                $tenantName
            );

            $mailer = new Mailer();
            $mailer->send($user['email'], "{$title} - {$tenantName}", $html);
        } catch (\Throwable $e) {
            error_log("OrgNotificationService::sendEmail error: " . $e->getMessage());
        }
    }
}
