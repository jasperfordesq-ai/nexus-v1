<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class NewsletterSegment
{
    /** @var array Valid fields for segment rules */
    private static array $validFields = [
        'role', 'profile_type', 'county', 'town', 'geo_radius', 'location',
        'group_membership', 'created_at', 'has_listings', 'listing_count',
        'activity_score', 'login_recency', 'transaction_count', 'community_rank',
        'email_open_rate', 'email_click_rate', 'newsletters_received', 'email_engagement_level',
        'bio', 'avatar'
    ];

    /** @var array Valid operators */
    private static array $validOperators = [
        'equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with',
        'greater_than', 'less_than', 'at_least', 'at_most', 'between',
        'newer_than_days', 'older_than_days', 'in_list', 'not_in_list', 'within_radius',
        'is_empty', 'is_not_empty'
    ];

    /**
     * Validate segment rules
     *
     * @throws \InvalidArgumentException if rules are invalid
     */
    public static function validateRules(array $rules): bool
    {
        if (!isset($rules['match']) || !in_array($rules['match'], ['all', 'any'])) {
            throw new \InvalidArgumentException('Rules must have a valid "match" type (all or any)');
        }

        if (!isset($rules['conditions']) || !is_array($rules['conditions'])) {
            throw new \InvalidArgumentException('Rules must have a "conditions" array');
        }

        if (empty($rules['conditions'])) {
            throw new \InvalidArgumentException('At least one condition is required');
        }

        foreach ($rules['conditions'] as $index => $condition) {
            self::validateCondition($condition, $index);
        }

        return true;
    }

    /**
     * Validate a single condition
     */
    private static function validateCondition(array $condition, int $index): void
    {
        $position = $index + 1;

        if (!isset($condition['field']) || empty($condition['field'])) {
            throw new \InvalidArgumentException("Condition #{$position}: Field is required");
        }

        if (!in_array($condition['field'], self::$validFields)) {
            throw new \InvalidArgumentException("Condition #{$position}: Invalid field '{$condition['field']}'");
        }

        if (!isset($condition['operator']) || empty($condition['operator'])) {
            throw new \InvalidArgumentException("Condition #{$position}: Operator is required");
        }

        if (!in_array($condition['operator'], self::$validOperators)) {
            throw new \InvalidArgumentException("Condition #{$position}: Invalid operator '{$condition['operator']}'");
        }

        // Validate value based on field type
        $field = $condition['field'];
        $value = $condition['value'] ?? null;

        // Numeric fields require numeric values
        $numericFields = ['email_open_rate', 'email_click_rate', 'newsletters_received', 'transaction_count', 'login_recency', 'listing_count'];
        if (in_array($field, $numericFields)) {
            if ($value !== null && !is_numeric($value) && !is_array($value)) {
                throw new \InvalidArgumentException("Condition #{$position}: Field '{$field}' requires a numeric value");
            }

            // Validate percentage ranges
            if (in_array($field, ['email_open_rate', 'email_click_rate']) && is_numeric($value)) {
                if ($value < 0 || $value > 100) {
                    throw new \InvalidArgumentException("Condition #{$position}: Percentage must be between 0 and 100");
                }
            }
        }

        // Select fields require valid option values
        $selectFields = [
            'activity_score' => ['high', 'medium', 'low', 'returning'],
            'community_rank' => ['top_10', 'top_25', 'top_50', 'bottom_25'],
            'email_engagement_level' => ['highly_engaged', 'engaged', 'passive', 'dormant', 'never_opened']
        ];

        if (isset($selectFields[$field]) && $value !== null) {
            if (!in_array($value, $selectFields[$field])) {
                throw new \InvalidArgumentException("Condition #{$position}: Invalid value '{$value}' for field '{$field}'");
            }
        }
    }

    /**
     * Create a new segment
     */
    public static function create($data)
    {
        // Validate rules if provided
        if (!empty($data['rules'])) {
            $rules = is_array($data['rules']) ? $data['rules'] : json_decode($data['rules'], true);
            if ($rules) {
                self::validateRules($rules);
            }
        }

        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO newsletter_segments (tenant_id, name, description, rules, created_by)
                VALUES (?, ?, ?, ?, ?)";

        Database::query($sql, [
            $tenantId,
            $data['name'],
            $data['description'] ?? null,
            is_array($data['rules']) ? json_encode($data['rules']) : $data['rules'],
            $data['created_by'] ?? null
        ]);

        return Database::getConnection()->lastInsertId();
    }

    /**
     * Find segment by ID
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM newsletter_segments WHERE id = ? AND tenant_id = ?";
        $result = Database::query($sql, [$id, $tenantId])->fetch();

        if ($result && $result['rules']) {
            $result['rules'] = json_decode($result['rules'], true);
        }

        return $result;
    }

    /**
     * Get all active segments
     */
    public static function getAll($activeOnly = true)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT * FROM newsletter_segments WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY name ASC";

        $results = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($results as &$result) {
            if ($result['rules']) {
                $result['rules'] = json_decode($result['rules'], true);
            }
        }

        return $results;
    }

    /**
     * Update a segment
     */
    public static function update($id, $data)
    {
        // Validate rules if provided
        if (!empty($data['rules'])) {
            $rules = is_array($data['rules']) ? $data['rules'] : json_decode($data['rules'], true);
            if ($rules) {
                self::validateRules($rules);
            }
        }

        $tenantId = TenantContext::getId();
        $fields = [];
        $params = [];

        $allowedFields = ['name', 'description', 'rules', 'is_active'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                if ($key === 'rules' && is_array($value)) {
                    $params[] = json_encode($value);
                } else {
                    $params[] = $value;
                }
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE newsletter_segments SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        $params[] = $id;
        $params[] = $tenantId;

        Database::query($sql, $params);
        return true;
    }

    /**
     * Delete a segment
     */
    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "DELETE FROM newsletter_segments WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
    }

    /**
     * Build query for segment rules and return matching user IDs
     */
    public static function getMatchingUsers($segmentId)
    {
        $segment = self::findById($segmentId);
        if (!$segment || !$segment['rules']) {
            return [];
        }

        return self::queryUsersByRules($segment['rules']);
    }

    /**
     * Count users matching a segment
     */
    public static function countMatchingUsers($segmentId)
    {
        $segment = self::findById($segmentId);
        if (!$segment || !$segment['rules']) {
            return 0;
        }

        return self::countUsersByRules($segment['rules']);
    }

    /**
     * Preview users matching rules (without saving segment)
     */
    public static function previewRules($rules)
    {
        return self::countUsersByRules($rules);
    }

    /**
     * Query users by rules
     */
    private static function queryUsersByRules($rules)
    {
        $tenantId = TenantContext::getId();
        $conditions = [];
        $params = [$tenantId];
        $matchType = $rules['match'] ?? 'all';

        foreach ($rules['conditions'] ?? [] as $condition) {
            $clause = self::buildConditionClause($condition, $params);
            if ($clause) {
                $conditions[] = $clause;
            }
        }

        // Default: approved users only
        $baseWhere = "tenant_id = ? AND is_approved = 1";

        if (!empty($conditions)) {
            $operator = ($matchType === 'all') ? ' AND ' : ' OR ';
            $baseWhere .= ' AND (' . implode($operator, $conditions) . ')';
        }

        $sql = "SELECT id, email, first_name, last_name FROM users WHERE $baseWhere ORDER BY email";

        return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count users by rules
     */
    private static function countUsersByRules($rules)
    {
        $tenantId = TenantContext::getId();
        $conditions = [];
        $params = [$tenantId];
        $matchType = $rules['match'] ?? 'all';

        foreach ($rules['conditions'] ?? [] as $condition) {
            $clause = self::buildConditionClause($condition, $params);
            if ($clause) {
                $conditions[] = $clause;
            }
        }

        // Default: approved users only
        $baseWhere = "tenant_id = ? AND is_approved = 1";

        if (!empty($conditions)) {
            $operator = ($matchType === 'all') ? ' AND ' : ' OR ';
            $baseWhere .= ' AND (' . implode($operator, $conditions) . ')';
        }

        $sql = "SELECT COUNT(*) as total FROM users WHERE $baseWhere";

        return Database::query($sql, $params)->fetch()['total'];
    }

    /**
     * Build a single condition clause
     */
    private static function buildConditionClause($condition, &$params)
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? '';

        switch ($field) {
            case 'role':
                return self::buildStringCondition('role', $operator, $value, $params);

            case 'profile_type':
                return self::buildStringCondition('profile_type', $operator, $value, $params);

            case 'location':
                return self::buildStringCondition('location', $operator, $value, $params);

            case 'created_at':
                return self::buildDateCondition('created_at', $operator, $value, $params);

            case 'has_listings':
                // Subquery for users with listings
                if ($value == '1' || $value === true || $value === 'yes') {
                    return "id IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')";
                } else {
                    return "id NOT IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')";
                }

            case 'listing_count':
                return self::buildListingCountCondition($operator, $value, $params);

            case 'geo_radius':
                // Geographic radius targeting using lat/lng
                return self::buildGeoRadiusCondition($value, $params);

            case 'county':
                // Irish county targeting (extracted from location field)
                return self::buildCountyCondition($operator, $value, $params);

            case 'group_membership':
                // Group membership targeting
                return self::buildGroupMembershipCondition($operator, $value, $params);

            case 'town':
                // Town/city targeting (location field contains town name)
                return self::buildTownCondition($operator, $value, $params);

            // Engagement-based fields (Algorithm)
            case 'activity_score':
                return self::buildActivityScoreCondition($operator, $value, $params);

            case 'login_recency':
                return self::buildLoginRecencyCondition($operator, $value, $params);

            case 'transaction_count':
                return self::buildTransactionCountCondition($operator, $value, $params);

            // Algorithm score fields
            case 'community_rank':
                return self::buildCommunityRankCondition($operator, $value, $params);

            // Email engagement fields
            case 'email_open_rate':
                return self::buildEmailOpenRateCondition($operator, $value, $params);

            case 'email_click_rate':
                return self::buildEmailClickRateCondition($operator, $value, $params);

            case 'newsletters_received':
                return self::buildNewslettersReceivedCondition($operator, $value, $params);

            case 'email_engagement_level':
                return self::buildEmailEngagementLevelCondition($operator, $value, $params);

            case 'bio':
                return self::buildStringCondition('bio', $operator, $value, $params);

            case 'avatar':
                return self::buildStringCondition('avatar_url', $operator, $value, $params);

            default:
                return null;
        }
    }

    /**
     * Build string comparison condition
     */
    private static function buildStringCondition($field, $operator, $value, &$params)
    {
        switch ($operator) {
            case 'equals':
                $params[] = $value;
                return "$field = ?";

            case 'not_equals':
                $params[] = $value;
                return "$field != ?";

            case 'contains':
                $params[] = '%' . $value . '%';
                return "$field LIKE ?";

            case 'starts_with':
                $params[] = $value . '%';
                return "$field LIKE ?";

            case 'is_empty':
                return "($field IS NULL OR $field = '')";

            case 'is_not_empty':
                return "($field IS NOT NULL AND $field != '')";

            default:
                return null;
        }
    }

    /**
     * Build date comparison condition
     */
    private static function buildDateCondition($field, $operator, $value, &$params)
    {
        switch ($operator) {
            case 'older_than_days':
                $params[] = (int) $value;
                return "$field < DATE_SUB(NOW(), INTERVAL ? DAY)";

            case 'newer_than_days':
                $params[] = (int) $value;
                return "$field > DATE_SUB(NOW(), INTERVAL ? DAY)";

            case 'before':
                $params[] = $value;
                return "$field < ?";

            case 'after':
                $params[] = $value;
                return "$field > ?";

            case 'between':
                if (is_array($value) && count($value) >= 2) {
                    $params[] = $value[0];
                    $params[] = $value[1];
                    return "$field BETWEEN ? AND ?";
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Build listing count condition (requires subquery)
     */
    private static function buildListingCountCondition($operator, $value, &$params)
    {
        $subquery = "(SELECT COUNT(*) FROM listings WHERE listings.user_id = users.id AND listings.status = 'active')";

        switch ($operator) {
            case 'equals':
                $params[] = (int) $value;
                return "$subquery = ?";

            case 'greater_than':
                $params[] = (int) $value;
                return "$subquery > ?";

            case 'less_than':
                $params[] = (int) $value;
                return "$subquery < ?";

            case 'at_least':
                $params[] = (int) $value;
                return "$subquery >= ?";

            case 'at_most':
                $params[] = (int) $value;
                return "$subquery <= ?";

            default:
                return null;
        }
    }

    /**
     * Build geographic radius condition using Haversine formula
     * Value should be: ['lat' => float, 'lng' => float, 'radius_km' => int]
     */
    private static function buildGeoRadiusCondition($value, &$params)
    {
        if (!is_array($value) || !isset($value['lat']) || !isset($value['lng']) || !isset($value['radius_km'])) {
            return null;
        }

        $lat = (float) $value['lat'];
        $lng = (float) $value['lng'];
        $radiusKm = (float) $value['radius_km'];

        // Haversine formula for distance in kilometers
        // 6371 = Earth's radius in km
        $params[] = $lat;
        $params[] = $lng;
        $params[] = $lat;
        $params[] = $radiusKm;

        return "(
            latitude IS NOT NULL
            AND longitude IS NOT NULL
            AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?
        )";
    }

    /**
     * Build county condition (location field contains Irish county)
     */
    private static function buildCountyCondition($operator, $value, &$params)
    {
        // Value can be a single county or an array of counties
        $counties = is_array($value) ? $value : [$value];

        if (empty($counties)) {
            return null;
        }

        $conditions = [];
        foreach ($counties as $county) {
            $params[] = '%' . trim($county) . '%';
            $conditions[] = "location LIKE ?";
        }

        if ($operator === 'not_in') {
            return "NOT (" . implode(' OR ', $conditions) . ")";
        }

        return "(" . implode(' OR ', $conditions) . ")";
    }

    /**
     * Build group membership condition
     * Value should be group ID(s) - single or array
     */
    private static function buildGroupMembershipCondition($operator, $value, &$params)
    {
        // Value can be a single group ID or array of group IDs
        $groupIds = is_array($value) ? $value : [$value];
        $groupIds = array_filter(array_map('intval', $groupIds));

        if (empty($groupIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        foreach ($groupIds as $gid) {
            $params[] = $gid;
        }

        $subquery = "id IN (SELECT user_id FROM group_members WHERE group_id IN ($placeholders) AND status = 'active')";

        if ($operator === 'not_member_of') {
            return "NOT ($subquery)";
        }

        return $subquery;
    }

    /**
     * Build town/city condition (location field contains town name)
     * Value can be a single town or array of towns
     */
    private static function buildTownCondition($operator, $value, &$params)
    {
        // Value can be a single town or an array of towns
        $towns = is_array($value) ? $value : array_map('trim', explode(',', $value));
        $towns = array_filter($towns);

        if (empty($towns)) {
            return null;
        }

        $conditions = [];
        foreach ($towns as $town) {
            $params[] = '%' . trim($town) . '%';
            $conditions[] = "location LIKE ?";
        }

        if ($operator === 'not_in') {
            return "NOT (" . implode(' OR ', $conditions) . ")";
        }

        return "(" . implode(' OR ', $conditions) . ")";
    }

    /**
     * Get list of available groups for segment targeting
     */
    public static function getAvailableGroups()
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT g.id, g.name, COUNT(gm.id) as member_count
                FROM `groups` g
                LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                WHERE g.tenant_id = ?
                GROUP BY g.id
                ORDER BY g.name ASC";
        return Database::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get list of Irish counties for segment targeting
     */
    public static function getIrishCounties()
    {
        return [
            'Antrim', 'Armagh', 'Carlow', 'Cavan', 'Clare', 'Cork', 'Derry',
            'Donegal', 'Down', 'Dublin', 'Fermanagh', 'Galway', 'Kerry',
            'Kildare', 'Kilkenny', 'Laois', 'Leitrim', 'Limerick', 'Longford',
            'Louth', 'Mayo', 'Meath', 'Monaghan', 'Offaly', 'Roscommon',
            'Sligo', 'Tipperary', 'Tyrone', 'Waterford', 'Westmeath',
            'Wexford', 'Wicklow'
        ];
    }

    /**
     * Get list of popular Irish towns/cities for segment targeting suggestions
     */
    public static function getIrishTowns()
    {
        return [
            // Major Cities
            'Dublin', 'Cork', 'Limerick', 'Galway', 'Waterford', 'Belfast', 'Derry',
            // Large Towns
            'Drogheda', 'Dundalk', 'Swords', 'Bray', 'Navan', 'Kilkenny', 'Ennis',
            'Carlow', 'Tralee', 'Newbridge', 'Portlaoise', 'Mullingar', 'Wexford',
            'Letterkenny', 'Sligo', 'Athlone', 'Celbridge', 'Clonmel', 'Greystones',
            'Malahide', 'Leixlip', 'Carrigaline', 'Tullamore', 'Arklow', 'Cobh',
            'Castlebar', 'Midleton', 'Mallow', 'Ashbourne', 'Ballina', 'Enniscorthy',
            'Wicklow', 'Tramore', 'Athenry', 'Shannon', 'Gorey', 'Cavan', 'Thurles',
            'Youghal', 'Dungarvan', 'Longford', 'Monaghan', 'Nenagh', 'Trim', 'Tuam',
            'Edenderry', 'Kildare', 'Fermoy', 'Bandon', 'Kinsale', 'Westport',
            'Roscommon', 'Birr', 'Roscrea', 'Ballinasloe', 'Bundoran', 'Killarney',
            'Kenmare', 'Dingle', 'Bantry', 'Skibbereen', 'Clifden', 'Ballybofey',
            'Donegal', 'Buncrana', 'Carndonagh', 'Ballymena', 'Lisburn', 'Newry',
            'Bangor', 'Craigavon', 'Newtownabbey', 'Carrickfergus', 'Coleraine',
            'Omagh', 'Enniskillen', 'Strabane', 'Cookstown', 'Dungannon', 'Armagh',
            'Downpatrick', 'Ballycastle', 'Portrush', 'Newcastle'
        ];
    }

    /**
     * Create default segments for a tenant
     */
    public static function createDefaults($createdBy = null)
    {
        $defaults = [
            [
                'name' => 'New Members (30 days)',
                'description' => 'Members who joined in the last 30 days',
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'created_at', 'operator' => 'newer_than_days', 'value' => 30]
                    ]
                ]
            ],
            [
                'name' => 'Long-term Members (1+ year)',
                'description' => 'Members who have been active for over a year',
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'created_at', 'operator' => 'older_than_days', 'value' => 365]
                    ]
                ]
            ],
            [
                'name' => 'Active Sellers',
                'description' => 'Members with at least one active listing',
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'has_listings', 'operator' => 'equals', 'value' => '1']
                    ]
                ]
            ],
            [
                'name' => 'Organizations',
                'description' => 'Organization accounts only',
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'profile_type', 'operator' => 'equals', 'value' => 'organisation']
                    ]
                ]
            ],
            [
                'name' => 'Individuals',
                'description' => 'Individual user accounts',
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'profile_type', 'operator' => 'equals', 'value' => 'individual']
                    ]
                ]
            ],
            [
                'name' => 'Never Logged In',
                'description' => 'Members who have never logged in to the app - perfect for re-engagement campaigns',
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'login_recency', 'operator' => 'equals', 'value' => 'never']
                    ]
                ]
            ]
        ];

        $created = [];
        foreach ($defaults as $segment) {
            $segment['created_by'] = $createdBy;
            $id = self::create($segment);
            $created[] = $id;
        }

        return $created;
    }

    // =========================================================================
    // ALGORITHM-BASED CONDITION BUILDERS
    // =========================================================================

    /**
     * Build activity score condition
     * Uses login recency as proxy for activity level
     */
    private static function buildActivityScoreCondition($operator, $value, &$params)
    {
        // Activity thresholds based on login recency
        // High: logged in within 7 days
        // Medium: logged in 8-30 days ago
        // Low: logged in more than 30 days ago (or never)
        // Returning: was inactive 60+ days but logged in within last 14 days
        $thresholds = [
            'high' => ['max_days' => 7],
            'medium' => ['min_days' => 8, 'max_days' => 30],
            'low' => ['min_days' => 31],
            'returning' => ['special' => true]
        ];

        if (!isset($thresholds[$value])) {
            return null;
        }

        $threshold = $thresholds[$value];
        $loginField = "COALESCE(last_login_at, created_at)";
        $daysSinceLogin = "DATEDIFF(NOW(), $loginField)";

        if ($value === 'high') {
            $condition = "$daysSinceLogin <= 7";
        } elseif ($value === 'medium') {
            $condition = "$daysSinceLogin BETWEEN 8 AND 30";
        } elseif ($value === 'returning') {
            // Returning: logged in within 14 days but had a gap of 60+ days before that
            $condition = "last_login_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND DATEDIFF(last_login_at, COALESCE(
                    (SELECT MAX(al.created_at)
                     FROM activity_log al
                     WHERE al.user_id = users.id
                     AND al.action = 'login'
                     AND al.created_at < DATE_SUB(users.last_login_at, INTERVAL 7 DAY)
                    ), users.created_at
                )) >= 60";
        } else { // low
            $condition = "$daysSinceLogin > 30 OR last_login_at IS NULL";
        }

        if ($operator === 'not_equals') {
            return "NOT ($condition)";
        }

        return "($condition)";
    }

    /**
     * Build login recency condition
     */
    private static function buildLoginRecencyCondition($operator, $value, &$params)
    {
        // Special case: never logged in
        if ($operator === 'equals' && $value === 'never') {
            return "last_login_at IS NULL";
        }

        $loginField = "COALESCE(last_login_at, created_at)";
        $daysSinceLogin = "DATEDIFF(NOW(), $loginField)";

        switch ($operator) {
            case 'newer_than_days':
                $params[] = (int) $value;
                return "$daysSinceLogin <= ?";

            case 'older_than_days':
                $params[] = (int) $value;
                return "$daysSinceLogin > ?";

            default:
                return null;
        }
    }

    /**
     * Build transaction count condition
     */
    private static function buildTransactionCountCondition($operator, $value, &$params)
    {
        $subquery = "(SELECT COUNT(*) FROM transactions WHERE transactions.sender_id = users.id OR transactions.receiver_id = users.id)";

        return self::buildNumericSubqueryCondition($subquery, $operator, $value, $params);
    }

    /**
     * Build CommunityRank percentile condition
     * Uses a composite score based on activity, contribution, and reputation
     */
    private static function buildCommunityRankCondition($operator, $value, &$params)
    {
        $tenantId = TenantContext::getId();

        // Optimized CommunityRank calculation using JOINs instead of correlated subqueries
        // This version pre-aggregates data to reduce query complexity

        // Simplified score components using JOINed aggregates
        $activityScore = "
            CASE
                WHEN DATEDIFF(NOW(), COALESCE(u.last_login_at, u.created_at)) <= 7 THEN 1.0
                WHEN DATEDIFF(NOW(), COALESCE(u.last_login_at, u.created_at)) <= 30 THEN 0.7
                ELSE 0.3
            END
        ";

        $contributionScore = "
            LEAST(1.0,
                0.5 +
                LEAST(0.3, COALESCE(listing_counts.cnt, 0) * 0.05) +
                CASE WHEN COALESCE(tx_sent.total, 0) > COALESCE(tx_recv.total, 0) THEN 0.2 ELSE 0 END
            )
        ";

        $reputationScore = "
            GREATEST(0.3,
                LEAST(1.0,
                    0.5 +
                    LEAST(0.2, DATEDIFF(NOW(), u.created_at) / 365.0 * 0.2) +
                    CASE WHEN COALESCE(u.bio, '') != '' THEN 0.1 ELSE 0 END +
                    CASE WHEN COALESCE(u.avatar_url, '') != '' THEN 0.1 ELSE 0 END
                )
            )
        ";

        $communityRankScore = "($activityScore * $contributionScore * $reputationScore)";

        // Map percentile values
        $percentileMap = [
            'top_10' => 90,
            'top_25' => 75,
            'top_50' => 50,
            'bottom_25' => 25
        ];

        if (!isset($percentileMap[$value])) {
            return null;
        }

        $percentile = $percentileMap[$value];

        // Use PERCENT_RANK with optimized JOINs (much faster than correlated subqueries)
        $rankedQuery = "
            SELECT
                u.id,
                PERCENT_RANK() OVER (ORDER BY $communityRankScore) * 100 as percentile_rank
            FROM users u
            LEFT JOIN (
                SELECT user_id, COUNT(*) as cnt
                FROM listings WHERE status = 'active'
                GROUP BY user_id
            ) listing_counts ON listing_counts.user_id = u.id
            LEFT JOIN (
                SELECT sender_id, COALESCE(SUM(amount), 0) as total
                FROM transactions
                GROUP BY sender_id
            ) tx_sent ON tx_sent.sender_id = u.id
            LEFT JOIN (
                SELECT receiver_id, COALESCE(SUM(amount), 0) as total
                FROM transactions
                GROUP BY receiver_id
            ) tx_recv ON tx_recv.receiver_id = u.id
            WHERE u.tenant_id = $tenantId AND u.is_approved = 1
        ";

        if ($value === 'bottom_25') {
            // Bottom 25%: below 25th percentile
            return "users.id IN (
                SELECT ranked.id FROM ($rankedQuery) ranked
                WHERE ranked.percentile_rank <= $percentile
            )";
        } else {
            // Top X%: above (100-X)th percentile
            return "users.id IN (
                SELECT ranked.id FROM ($rankedQuery) ranked
                WHERE ranked.percentile_rank >= $percentile
            )";
        }
    }

    /**
     * Build email open rate condition
     */
    private static function buildEmailOpenRateCondition($operator, $value, &$params)
    {
        // Calculate open rate: (unique opens / newsletters sent) * 100
        $openRateSql = "(
            SELECT CASE
                WHEN COUNT(DISTINCT nq.newsletter_id) = 0 THEN 0
                ELSE ROUND(COUNT(DISTINCT no.id) * 100.0 / COUNT(DISTINCT nq.newsletter_id), 1)
            END
            FROM newsletter_queue nq
            LEFT JOIN newsletter_opens no ON nq.email = no.email AND nq.newsletter_id = no.newsletter_id
            WHERE nq.user_id = users.id AND nq.status = 'sent'
        )";

        return self::buildNumericSubqueryCondition($openRateSql, $operator, $value, $params);
    }

    /**
     * Build email click rate condition
     */
    private static function buildEmailClickRateCondition($operator, $value, &$params)
    {
        // Calculate click rate: (unique clicks / unique opens) * 100
        // Note: Click rate is based on opens, not sends
        $clickRateSql = "(
            SELECT CASE
                WHEN COUNT(DISTINCT no.id) = 0 THEN 0
                ELSE ROUND(COUNT(DISTINCT nc.id) * 100.0 / COUNT(DISTINCT no.id), 1)
            END
            FROM newsletter_queue nq
            LEFT JOIN newsletter_opens no ON nq.email = no.email AND nq.newsletter_id = no.newsletter_id
            LEFT JOIN newsletter_clicks nc ON nq.email = nc.email AND nq.newsletter_id = nc.newsletter_id
            WHERE nq.user_id = users.id AND nq.status = 'sent'
        )";

        return self::buildNumericSubqueryCondition($clickRateSql, $operator, $value, $params);
    }

    /**
     * Build newsletters received condition
     */
    private static function buildNewslettersReceivedCondition($operator, $value, &$params)
    {
        $countSql = "(
            SELECT COUNT(DISTINCT newsletter_id)
            FROM newsletter_queue
            WHERE newsletter_queue.user_id = users.id
            AND newsletter_queue.status = 'sent'
        )";

        return self::buildNumericSubqueryCondition($countSql, $operator, $value, $params);
    }

    /**
     * Build email engagement level condition
     * Engagement levels based on combined open and click behavior
     */
    private static function buildEmailEngagementLevelCondition($operator, $value, &$params)
    {
        // Engagement level calculation:
        // highly_engaged: open_rate >= 70% AND click_rate >= 30%
        // engaged: open_rate >= 40% AND click_rate >= 10%
        // passive: open_rate >= 20%
        // dormant: open_rate > 0 AND open_rate < 20%
        // never_opened: open_rate = 0 (and has received newsletters)

        $engagementSql = "(
            SELECT CASE
                WHEN stats.sent_count = 0 THEN 'no_newsletters'
                WHEN stats.open_rate >= 70 AND stats.click_rate >= 30 THEN 'highly_engaged'
                WHEN stats.open_rate >= 40 AND stats.click_rate >= 10 THEN 'engaged'
                WHEN stats.open_rate >= 20 THEN 'passive'
                WHEN stats.open_rate > 0 THEN 'dormant'
                ELSE 'never_opened'
            END
            FROM (
                SELECT
                    COUNT(DISTINCT nq.newsletter_id) as sent_count,
                    CASE WHEN COUNT(DISTINCT nq.newsletter_id) = 0 THEN 0
                         ELSE ROUND(COUNT(DISTINCT no.id) * 100.0 / COUNT(DISTINCT nq.newsletter_id), 1) END as open_rate,
                    CASE WHEN COUNT(DISTINCT no.id) = 0 THEN 0
                         ELSE ROUND(COUNT(DISTINCT nc.id) * 100.0 / COUNT(DISTINCT no.id), 1) END as click_rate
                FROM newsletter_queue nq
                LEFT JOIN newsletter_opens no ON nq.email = no.email AND nq.newsletter_id = no.newsletter_id
                LEFT JOIN newsletter_clicks nc ON nq.email = nc.email AND nq.newsletter_id = nc.newsletter_id
                WHERE nq.user_id = users.id AND nq.status = 'sent'
            ) stats
        )";

        if ($operator === 'equals') {
            $params[] = $value;
            return "$engagementSql = ?";
        } elseif ($operator === 'not_equals') {
            $params[] = $value;
            return "$engagementSql != ?";
        }

        return null;
    }

    /**
     * Reusable helper for numeric subquery conditions
     */
    private static function buildNumericSubqueryCondition($subquery, $operator, $value, &$params)
    {
        switch ($operator) {
            case 'equals':
                $params[] = (float) $value;
                return "$subquery = ?";

            case 'greater_than':
                $params[] = (float) $value;
                return "$subquery > ?";

            case 'less_than':
                $params[] = (float) $value;
                return "$subquery < ?";

            case 'at_least':
                $params[] = (float) $value;
                return "$subquery >= ?";

            case 'at_most':
                $params[] = (float) $value;
                return "$subquery <= ?";

            case 'between':
                if (is_array($value) && count($value) >= 2) {
                    $params[] = (float) $value[0];
                    $params[] = (float) $value[1];
                    return "$subquery BETWEEN ? AND ?";
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Get available fields for segmentation rules
     */
    public static function getAvailableFields()
    {
        return [
            // Profile fields
            'role' => [
                'label' => 'User Role',
                'type' => 'select',
                'options' => ['user' => 'User', 'admin' => 'Admin'],
                'operators' => ['equals', 'not_equals']
            ],
            'profile_type' => [
                'label' => 'Profile Type',
                'type' => 'select',
                'options' => ['individual' => 'Individual', 'organisation' => 'Organization'],
                'operators' => ['equals', 'not_equals']
            ],
            'location' => [
                'label' => 'Location Text',
                'type' => 'text',
                'operators' => ['equals', 'contains', 'starts_with', 'is_empty', 'is_not_empty']
            ],
            'bio' => [
                'label' => 'Bio/About',
                'type' => 'text',
                'operators' => ['is_empty', 'is_not_empty', 'contains'],
                'category' => 'profile'
            ],
            'avatar' => [
                'label' => 'Profile Photo',
                'type' => 'text',
                'operators' => ['is_empty', 'is_not_empty'],
                'category' => 'profile'
            ],
            'county' => [
                'label' => 'County',
                'type' => 'county_select',
                'operators' => ['in', 'not_in']
            ],
            'town' => [
                'label' => 'Town/City',
                'type' => 'town_select',
                'operators' => ['in', 'not_in']
            ],
            'geo_radius' => [
                'label' => 'Geographic Area',
                'type' => 'geo_radius',
                'operators' => ['within']
            ],
            'group_membership' => [
                'label' => 'Group Membership',
                'type' => 'group_select',
                'operators' => ['member_of', 'not_member_of']
            ],
            'created_at' => [
                'label' => 'Member Since',
                'type' => 'date',
                'operators' => ['older_than_days', 'newer_than_days', 'before', 'after']
            ],
            'has_listings' => [
                'label' => 'Has Active Listings',
                'type' => 'boolean',
                'operators' => ['equals']
            ],
            'listing_count' => [
                'label' => 'Number of Listings',
                'type' => 'number',
                'operators' => ['equals', 'greater_than', 'less_than', 'at_least', 'at_most']
            ],

            // Engagement-based fields (Algorithm)
            'activity_score' => [
                'label' => 'Activity Score',
                'type' => 'select',
                'options' => ['high' => 'High (Active)', 'medium' => 'Medium', 'low' => 'Low (Inactive)', 'returning' => 'Returning (Came Back)'],
                'operators' => ['equals', 'not_equals'],
                'category' => 'engagement'
            ],
            'login_recency' => [
                'label' => 'Last Login',
                'type' => 'number',
                'operators' => ['newer_than_days', 'older_than_days', 'equals'],
                'placeholder' => 'Days (or "never")',
                'category' => 'engagement'
            ],
            'transaction_count' => [
                'label' => 'Transaction Count',
                'type' => 'number',
                'operators' => ['equals', 'greater_than', 'less_than', 'at_least', 'at_most'],
                'placeholder' => 'Number',
                'category' => 'engagement'
            ],

            // Algorithm score fields
            'community_rank' => [
                'label' => 'CommunityRank',
                'type' => 'select',
                'options' => [
                    'top_10' => 'Top 10%',
                    'top_25' => 'Top 25%',
                    'top_50' => 'Top 50%',
                    'bottom_25' => 'Bottom 25%'
                ],
                'operators' => ['equals'],
                'category' => 'algorithm'
            ],

            // Email engagement fields
            'email_open_rate' => [
                'label' => 'Email Open Rate (%)',
                'type' => 'number',
                'operators' => ['at_least', 'at_most', 'greater_than', 'less_than'],
                'placeholder' => '0-100',
                'category' => 'email'
            ],
            'email_click_rate' => [
                'label' => 'Email Click Rate (%)',
                'type' => 'number',
                'operators' => ['at_least', 'at_most', 'greater_than', 'less_than'],
                'placeholder' => '0-100',
                'category' => 'email'
            ],
            'newsletters_received' => [
                'label' => 'Newsletters Received',
                'type' => 'number',
                'operators' => ['at_least', 'at_most', 'equals', 'greater_than', 'less_than'],
                'placeholder' => 'Number',
                'category' => 'email'
            ],
            'email_engagement_level' => [
                'label' => 'Email Engagement',
                'type' => 'select',
                'options' => [
                    'highly_engaged' => 'Highly Engaged',
                    'engaged' => 'Engaged',
                    'passive' => 'Passive',
                    'dormant' => 'Dormant',
                    'never_opened' => 'Never Opened'
                ],
                'operators' => ['equals', 'not_equals'],
                'category' => 'email'
            ]
        ];
    }
}
