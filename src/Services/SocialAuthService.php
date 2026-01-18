<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;

class SocialAuthService
{
    public function handleUser($provider, $collabUser)
    {
        $tenantId = TenantContext::getId();
        $email = $collabUser['email'];
        $providerId = $collabUser['id'];
        $avatar = !empty($collabUser['avatar']) ? $collabUser['avatar'] : null;

        // 1. Check if Social Identity already exists
        $sql = "SELECT * FROM social_identities 
                JOIN users ON users.id = social_identities.user_id 
                WHERE provider = ? AND provider_id = ? AND users.tenant_id = ?";
        $existing = Database::query($sql, [$provider, $providerId, $tenantId])->fetch();

        if ($existing) {
            $userId = $existing['user_id'];

            // LOGIC: Only update avatar if source has one.
            if ($avatar) {
                // Update Name AND Avatar
                $updateSql = "UPDATE users SET first_name = ?, last_name = ?, avatar_url = ? WHERE id = ? AND tenant_id = ?";
                Database::query($updateSql, [
                    $collabUser['first_name'],
                    $collabUser['last_name'],
                    $avatar,
                    $userId,
                    $tenantId
                ]);
            } else {
                // Update Name ONLY (Skip Avatar)
                $updateSql = "UPDATE users SET first_name = ?, last_name = ? WHERE id = ? AND tenant_id = ?";
                Database::query($updateSql, [
                    $collabUser['first_name'],
                    $collabUser['last_name'],
                    $userId,
                    $tenantId
                ]);
            }

            return User::findById($userId);
        }

        // 2. Check if Email Exists (Link Account)
        $user = User::findByEmail($email);

        if ($user) {
            // Link Account
            $this->linkIdentity($user['id'], $provider, $providerId);

            // LOGIC: Sync Avatar if missing locally AND present in source
            if (empty($user['avatar_url']) && $avatar) {
                Database::query("UPDATE users SET avatar_url = ? WHERE id = ?", [$avatar, $user['id']]);
            }
            return $user;
        }

        // 3. Create New User
        $sql = "INSERT INTO users (tenant_id, first_name, last_name, email, password_hash, is_approved, role, profile_type, avatar_url, created_at) 
                VALUES (?, ?, ?, ?, NULL, 1, 'member', 'individual', ?, NOW())";

        Database::query($sql, [
            $tenantId,
            $collabUser['first_name'],
            $collabUser['last_name'],
            $email,
            $avatar // Can be NULL here, which is fine for new users
        ]);

        $newUserId = Database::getConnection()->lastInsertId();

        // Link Identity
        $this->linkIdentity($newUserId, $provider, $providerId);

        return User::findById($newUserId);
    }

    private function linkIdentity($userId, $provider, $providerId)
    {
        $sql = "INSERT INTO social_identities (user_id, provider, provider_id) VALUES (?, ?, ?)";
        Database::query($sql, [$userId, $provider, $providerId]);
    }
}
