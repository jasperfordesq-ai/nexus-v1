<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * OnboardingService - Business logic for post-registration onboarding wizard
 *
 * Handles:
 * - Checking onboarding completion status
 * - Saving user interests (categories they're interested in)
 * - Saving user skills (offers and needs by category)
 * - Auto-creating listings from skill selections
 * - Marking onboarding as complete
 *
 * All methods are tenant-scoped following the project service pattern.
 *
 * @package Nexus\Services
 */
class OnboardingService
{
    /**
     * Check if a user has completed onboarding
     *
     * @param int $userId
     * @return bool
     */
    public static function isOnboardingComplete(int $userId): bool
    {
        $stmt = Database::query(
            "SELECT onboarding_completed FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        );
        $row = $stmt->fetch();
        return $row && (bool)$row['onboarding_completed'];
    }

    /**
     * Get all user interests grouped by type
     *
     * @param int $userId
     * @return array Array of interest rows with category_name joined
     */
    public static function getUserInterests(int $userId): array
    {
        $stmt = Database::query(
            "SELECT ui.*, c.name as category_name
             FROM user_interests ui
             JOIN categories c ON c.id = ui.category_id
             WHERE ui.user_id = ?
             ORDER BY ui.interest_type, c.name",
            [$userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Save user interests (general category interests from Step 2)
     *
     * Replaces all existing 'interest' type entries for the user.
     *
     * @param int $userId
     * @param array $categoryIds Array of category IDs
     */
    public static function saveInterests(int $userId, array $categoryIds): void
    {
        // Delete existing interests for this user, then insert new ones
        Database::query(
            "DELETE FROM user_interests WHERE user_id = ? AND interest_type = 'interest'",
            [$userId]
        );

        foreach ($categoryIds as $catId) {
            Database::query(
                "INSERT IGNORE INTO user_interests (user_id, category_id, interest_type) VALUES (?, ?, 'interest')",
                [$userId, (int)$catId]
            );
        }
    }

    /**
     * Save user skills (offers and needs from Step 3)
     *
     * Replaces all existing 'skill_offer' and 'skill_need' entries for the user.
     *
     * @param int $userId
     * @param array $offers Array of category IDs the user can offer
     * @param array $needs Array of category IDs the user needs help with
     */
    public static function saveSkills(int $userId, array $offers, array $needs): void
    {
        // Delete existing skills
        Database::query(
            "DELETE FROM user_interests WHERE user_id = ? AND interest_type IN ('skill_offer', 'skill_need')",
            [$userId]
        );

        foreach ($offers as $catId) {
            Database::query(
                "INSERT IGNORE INTO user_interests (user_id, category_id, interest_type) VALUES (?, ?, 'skill_offer')",
                [$userId, (int)$catId]
            );
        }

        foreach ($needs as $catId) {
            Database::query(
                "INSERT IGNORE INTO user_interests (user_id, category_id, interest_type) VALUES (?, ?, 'skill_need')",
                [$userId, (int)$catId]
            );
        }
    }

    /**
     * Mark onboarding as complete for a user
     *
     * @param int $userId
     */
    public static function completeOnboarding(int $userId): void
    {
        Database::query(
            "UPDATE users SET onboarding_completed = 1 WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        );
    }

    /**
     * Auto-create listings from skill selections
     *
     * Creates 'offer' listings for each skill the user can provide,
     * and 'request' listings for each skill they need help with.
     *
     * @param int $userId
     * @param array $offers Array of category IDs for offer listings
     * @param array $needs Array of category IDs for request listings
     * @return array Array of created listing IDs
     */
    public static function autoCreateListings(int $userId, array $offers, array $needs): array
    {
        $tenantId = TenantContext::getId();
        $createdIds = [];

        // Get category names for listing titles
        $categories = [];
        if (!empty($offers) || !empty($needs)) {
            $allCatIds = array_unique(array_merge(
                array_map('intval', $offers),
                array_map('intval', $needs)
            ));

            if (!empty($allCatIds)) {
                $placeholders = implode(',', array_fill(0, count($allCatIds), '?'));
                $params = array_values($allCatIds);
                $params[] = $tenantId;

                $stmt = Database::query(
                    "SELECT id, name FROM categories WHERE id IN ($placeholders) AND tenant_id = ?",
                    $params
                );

                while ($row = $stmt->fetch()) {
                    $categories[(int)$row['id']] = $row['name'];
                }
            }
        }

        // Create offer listings
        foreach ($offers as $catId) {
            $catId = (int)$catId;
            $catName = $categories[$catId] ?? 'Service';
            Database::query(
                "INSERT INTO listings (title, description, type, category_id, user_id, tenant_id, status, created_at)
                 VALUES (?, ?, 'offer', ?, ?, ?, 'active', NOW())",
                [
                    "I can help with {$catName}",
                    "I'm available to help with {$catName}. Get in touch to arrange!",
                    $catId,
                    $userId,
                    $tenantId,
                ]
            );
            $createdIds[] = (int)Database::lastInsertId();
        }

        // Create request listings
        foreach ($needs as $catId) {
            $catId = (int)$catId;
            $catName = $categories[$catId] ?? 'Service';
            Database::query(
                "INSERT INTO listings (title, description, type, category_id, user_id, tenant_id, status, created_at)
                 VALUES (?, ?, 'request', ?, ?, ?, 'active', NOW())",
                [
                    "Looking for help with {$catName}",
                    "I'm looking for someone who can help me with {$catName}.",
                    $catId,
                    $userId,
                    $tenantId,
                ]
            );
            $createdIds[] = (int)Database::lastInsertId();
        }

        return $createdIds;
    }
}
