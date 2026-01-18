<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class VolOpportunity
{
    public static function create($tenantId, $userId, $orgId, $title, $description, $location, $skills, $start, $end, $categoryId = null)
    {
        $sql = "INSERT INTO vol_opportunities (tenant_id, created_by, organization_id, title, description, location, skills_needed, start_date, end_date, category_id, status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', 1)";
        Database::query($sql, [$tenantId, $userId, $orgId, $title, $description, $location, $skills, $start, $end, $categoryId]);
        return Database::lastInsertId();
    }

    public static function update($id, $title, $description, $location, $skills, $start, $end, $categoryId)
    {
        $sql = "UPDATE vol_opportunities SET title = ?, description = ?, location = ?, skills_needed = ?, start_date = ?, end_date = ?, category_id = ? WHERE id = ?";
        Database::query($sql, [$title, $description, $location, $skills, $start, $end, $categoryId, $id]);
    }

    public static function search($tenantId, $query = null, $catId = null, $isRemote = false)
    {
        // Join with Org to check Tenant + Get Org Name
        $sql = "SELECT opp.*, org.name as org_name, org.logo_url 
                FROM vol_opportunities opp
                JOIN vol_organizations org ON opp.organization_id = org.id
                WHERE org.tenant_id = ? AND opp.is_active = 1 AND org.status = 'approved'";

        $params = [$tenantId];

        if ($query) {
            $sql .= " AND (opp.title LIKE ? OR opp.description LIKE ? OR org.name LIKE ?)";
            $params[] = "%$query%";
            $params[] = "%$query%";
            $params[] = "%$query%";
        }

        if ($catId) {
            $sql .= " AND opp.category_id = ?";
            $params[] = $catId;
        }

        if ($isRemote) {
            $sql .= " AND (opp.is_remote = 1 OR opp.location LIKE '%Remote%')";
        }

        $sql .= " ORDER BY opp.created_at DESC";

        return Database::query($sql, $params)->fetchAll();
    }

    public static function find($id)
    {
        $sql = "SELECT opp.*, org.name as org_name, org.website as org_website, org.contact_email as org_email, org.user_id as org_owner_id
                FROM vol_opportunities opp
                JOIN vol_organizations org ON opp.organization_id = org.id
                WHERE opp.id = ?";
        return Database::query($sql, [$id])->fetch();
    }

    public static function getForOrg($orgId)
    {
        return Database::query("SELECT * FROM vol_opportunities WHERE organization_id = ? ORDER BY created_at DESC", [$orgId])->fetchAll();
    }
}
