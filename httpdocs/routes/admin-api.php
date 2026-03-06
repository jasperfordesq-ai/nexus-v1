<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// ============================================
// API V2 - ADMIN (React Admin Panel)
// Dashboard, Users, Listings, Config, Cache, Jobs
// ============================================

// Admin Dashboard
$router->add('GET', '/api/v2/admin/dashboard/stats', 'Nexus\Controllers\Api\AdminDashboardApiController@stats');
$router->add('GET', '/api/v2/admin/dashboard/trends', 'Nexus\Controllers\Api\AdminDashboardApiController@trends');
$router->add('GET', '/api/v2/admin/dashboard/activity', 'Nexus\Controllers\Api\AdminDashboardApiController@activity');

// Admin Users
$router->add('GET', '/api/v2/admin/users', 'Nexus\Controllers\Api\AdminUsersApiController@index');
$router->add('POST', '/api/v2/admin/users', 'Nexus\Controllers\Api\AdminUsersApiController@store');
$router->add('POST', '/api/v2/admin/users/import', 'Nexus\Controllers\Api\AdminUsersApiController@import');
$router->add('GET', '/api/v2/admin/users/import/template', 'Nexus\Controllers\Api\AdminUsersApiController@importTemplate');
$router->add('GET', '/api/v2/admin/users/{id}', 'Nexus\Controllers\Api\AdminUsersApiController@show');
$router->add('PUT', '/api/v2/admin/users/{id}', 'Nexus\Controllers\Api\AdminUsersApiController@update');
$router->add('DELETE', '/api/v2/admin/users/{id}', 'Nexus\Controllers\Api\AdminUsersApiController@destroy');
$router->add('POST', '/api/v2/admin/users/{id}/approve', 'Nexus\Controllers\Api\AdminUsersApiController@approve');
$router->add('POST', '/api/v2/admin/users/{id}/suspend', 'Nexus\Controllers\Api\AdminUsersApiController@suspend');
$router->add('POST', '/api/v2/admin/users/{id}/ban', 'Nexus\Controllers\Api\AdminUsersApiController@ban');
$router->add('POST', '/api/v2/admin/users/{id}/reactivate', 'Nexus\Controllers\Api\AdminUsersApiController@reactivate');
$router->add('POST', '/api/v2/admin/users/{id}/reset-2fa', 'Nexus\Controllers\Api\AdminUsersApiController@reset2fa');
$router->add('POST', '/api/v2/admin/users/badges/recheck-all', 'Nexus\Controllers\Api\AdminGamificationApiController@recheckAll');
$router->add('POST', '/api/v2/admin/users/{id}/badges', 'Nexus\Controllers\Api\AdminUsersApiController@addBadge');
$router->add('DELETE', '/api/v2/admin/users/{id}/badges/{badgeId}', 'Nexus\Controllers\Api\AdminUsersApiController@removeBadge');
$router->add('POST', '/api/v2/admin/users/{id}/impersonate', 'Nexus\Controllers\Api\AdminUsersApiController@impersonate');
$router->add('PUT', '/api/v2/admin/users/{id}/super-admin', 'Nexus\Controllers\Api\AdminUsersApiController@setSuperAdmin');
$router->add('PUT', '/api/v2/admin/users/{id}/global-super-admin', 'Nexus\Controllers\Api\AdminUsersApiController@setGlobalSuperAdmin');
$router->add('POST', '/api/v2/admin/users/{id}/badges/recheck', 'Nexus\Controllers\Api\AdminUsersApiController@recheckBadges');
$router->add('GET', '/api/v2/admin/users/{id}/consents', 'Nexus\Controllers\Api\AdminUsersApiController@getConsents');
$router->add('POST', '/api/v2/admin/users/{id}/password', 'Nexus\Controllers\Api\AdminUsersApiController@setPassword');
$router->add('POST', '/api/v2/admin/users/{id}/send-password-reset', 'Nexus\Controllers\Api\AdminUsersApiController@sendPasswordReset');
$router->add('POST', '/api/v2/admin/users/{id}/send-welcome-email', 'Nexus\Controllers\Api\AdminUsersApiController@sendWelcomeEmail');

// Admin Listings/Content
$router->add('GET', '/api/v2/admin/listings', 'Nexus\Controllers\Api\AdminListingsApiController@index');
$router->add('GET', '/api/v2/admin/listings/moderation-queue', 'Nexus\Controllers\Api\AdminListingsApiController@moderationQueue');
$router->add('GET', '/api/v2/admin/listings/moderation-stats', 'Nexus\Controllers\Api\AdminListingsApiController@moderationStats');
$router->add('GET', '/api/v2/admin/listings/{id}', 'Nexus\Controllers\Api\AdminListingsApiController@show');
$router->add('POST', '/api/v2/admin/listings/{id}/approve', 'Nexus\Controllers\Api\AdminListingsApiController@approve');
$router->add('DELETE', '/api/v2/admin/listings/{id}', 'Nexus\Controllers\Api\AdminListingsApiController@destroy');

// Admin Categories
$router->add('GET', '/api/v2/admin/categories', 'Nexus\Controllers\Api\AdminCategoriesApiController@index');
$router->add('POST', '/api/v2/admin/categories', 'Nexus\Controllers\Api\AdminCategoriesApiController@store');
$router->add('PUT', '/api/v2/admin/categories/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@update');
$router->add('DELETE', '/api/v2/admin/categories/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@destroy');

// Admin Attributes
$router->add('GET', '/api/v2/admin/attributes', 'Nexus\Controllers\Api\AdminCategoriesApiController@listAttributes');
$router->add('POST', '/api/v2/admin/attributes', 'Nexus\Controllers\Api\AdminCategoriesApiController@storeAttribute');
$router->add('PUT', '/api/v2/admin/attributes/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@updateAttribute');
$router->add('DELETE', '/api/v2/admin/attributes/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@destroyAttribute');

// Admin Config (Features & Modules)
$router->add('GET', '/api/v2/admin/config', 'Nexus\Controllers\Api\AdminConfigApiController@getConfig');
$router->add('PUT', '/api/v2/admin/config/features', 'Nexus\Controllers\Api\AdminConfigApiController@updateFeature');
$router->add('PUT', '/api/v2/admin/config/modules', 'Nexus\Controllers\Api\AdminConfigApiController@updateModule');

// Admin Cache
$router->add('GET', '/api/v2/admin/cache/stats', 'Nexus\Controllers\Api\AdminConfigApiController@cacheStats');
$router->add('POST', '/api/v2/admin/cache/clear', 'Nexus\Controllers\Api\AdminConfigApiController@clearCache');

// Admin Background Jobs (uses /background-jobs to avoid collision with /admin/jobs for job vacancies)
$router->add('GET', '/api/v2/admin/background-jobs', 'Nexus\Controllers\Api\AdminConfigApiController@getJobs');
$router->add('POST', '/api/v2/admin/background-jobs/{id}/run', 'Nexus\Controllers\Api\AdminConfigApiController@runJob');

// Admin Settings (General Tenant Settings)
$router->add('GET', '/api/v2/admin/settings', 'Nexus\Controllers\Api\AdminConfigApiController@getSettings');
$router->add('PUT', '/api/v2/admin/settings', 'Nexus\Controllers\Api\AdminConfigApiController@updateSettings');

// Admin Config - AI
$router->add('GET', '/api/v2/admin/config/ai', 'Nexus\Controllers\Api\AdminConfigApiController@getAiConfig');
$router->add('PUT', '/api/v2/admin/config/ai', 'Nexus\Controllers\Api\AdminConfigApiController@updateAiConfig');

// Admin Config - Feed Algorithm (legacy per-area endpoint)
$router->add('GET', '/api/v2/admin/config/feed-algorithm', 'Nexus\Controllers\Api\AdminConfigApiController@getFeedAlgorithmConfig');
$router->add('PUT', '/api/v2/admin/config/feed-algorithm', 'Nexus\Controllers\Api\AdminConfigApiController@updateFeedAlgorithmConfig');

// Admin Config - Algorithms (unified per-area endpoint)
$router->add('GET', '/api/v2/admin/config/algorithms', 'Nexus\Controllers\Api\AdminConfigApiController@getAlgorithmConfig');
$router->add('PUT', '/api/v2/admin/config/algorithm/{area}', 'Nexus\Controllers\Api\AdminConfigApiController@updateAlgorithmConfig');
$router->add('GET', '/api/v2/admin/config/algorithm-health', 'Nexus\Controllers\Api\AdminConfigApiController@getAlgorithmHealth');

// Admin Config - Images
$router->add('GET', '/api/v2/admin/config/images', 'Nexus\Controllers\Api\AdminConfigApiController@getImageConfig');
$router->add('PUT', '/api/v2/admin/config/images', 'Nexus\Controllers\Api\AdminConfigApiController@updateImageConfig');

// Admin Config - SEO
$router->add('GET', '/api/v2/admin/config/seo', 'Nexus\Controllers\Api\AdminConfigApiController@getSeoConfig');
$router->add('PUT', '/api/v2/admin/config/seo', 'Nexus\Controllers\Api\AdminConfigApiController@updateSeoConfig');

// Admin Config - Languages
$router->add('GET', '/api/v2/admin/config/languages', 'Nexus\Controllers\Api\AdminConfigApiController@getLanguageConfig');
$router->add('PUT', '/api/v2/admin/config/languages', 'Nexus\Controllers\Api\AdminConfigApiController@updateLanguageConfig');

// Admin Config - Native App / PWA
$router->add('GET', '/api/v2/admin/config/native-app', 'Nexus\Controllers\Api\AdminConfigApiController@getNativeAppConfig');
$router->add('PUT', '/api/v2/admin/config/native-app', 'Nexus\Controllers\Api\AdminConfigApiController@updateNativeAppConfig');

// Admin System - Cron Jobs
$router->add('GET', '/api/v2/admin/system/cron-jobs', 'Nexus\Controllers\Api\AdminConfigApiController@getCronJobs');
$router->add('POST', '/api/v2/admin/system/cron-jobs/{id}/run', 'Nexus\Controllers\Api\AdminConfigApiController@runCronJob');

// Admin System - Cron Jobs - Logs
$router->add('GET', '/api/v2/admin/system/cron-jobs/logs', 'Nexus\Controllers\Api\AdminCronApiController@getLogs');
$router->add('GET', '/api/v2/admin/system/cron-jobs/logs/{id}', 'Nexus\Controllers\Api\AdminCronApiController@getLogDetail');
$router->add('DELETE', '/api/v2/admin/system/cron-jobs/logs', 'Nexus\Controllers\Api\AdminCronApiController@clearLogs');

// Admin System - Cron Jobs - Global Settings & Health
// IMPORTANT: Literal routes must come before {jobId} wildcard to avoid being shadowed
$router->add('GET', '/api/v2/admin/system/cron-jobs/settings', 'Nexus\Controllers\Api\AdminCronApiController@getGlobalSettings');
$router->add('PUT', '/api/v2/admin/system/cron-jobs/settings', 'Nexus\Controllers\Api\AdminCronApiController@updateGlobalSettings');
$router->add('GET', '/api/v2/admin/system/cron-jobs/health', 'Nexus\Controllers\Api\AdminCronApiController@getHealthMetrics');

// Admin System - Cron Jobs - Per-Job Settings
$router->add('GET', '/api/v2/admin/system/cron-jobs/{jobId}/settings', 'Nexus\Controllers\Api\AdminCronApiController@getJobSettings');
$router->add('PUT', '/api/v2/admin/system/cron-jobs/{jobId}/settings', 'Nexus\Controllers\Api\AdminCronApiController@updateJobSettings');

// Admin System - Activity Log
$router->add('GET', '/api/v2/admin/system/activity-log', 'Nexus\Controllers\Api\AdminDashboardApiController@activity');

// Admin System - Email
$router->add('GET', '/api/v2/admin/email/status', 'Nexus\Controllers\Api\EmailAdminApiController@status');
$router->add('POST', '/api/v2/admin/email/test', 'Nexus\Controllers\Api\EmailAdminApiController@test');
$router->add('POST', '/api/v2/admin/email/test-gmail', 'Nexus\Controllers\Api\EmailAdminApiController@testGmail');
$router->add('GET', '/api/v2/admin/email/config', 'Nexus\Controllers\Api\EmailAdminApiController@getConfig');
$router->add('PUT', '/api/v2/admin/email/config', 'Nexus\Controllers\Api\EmailAdminApiController@updateConfig');
$router->add('POST', '/api/v2/admin/email/test-provider', 'Nexus\Controllers\Api\EmailAdminApiController@testProvider');

// Admin Matching - Config, Stats, Cache
$router->add('GET', '/api/v2/admin/matching/config', 'Nexus\Controllers\Api\AdminMatchingApiController@getConfig');
$router->add('PUT', '/api/v2/admin/matching/config', 'Nexus\Controllers\Api\AdminMatchingApiController@updateConfig');
$router->add('POST', '/api/v2/admin/matching/cache/clear', 'Nexus\Controllers\Api\AdminMatchingApiController@clearCache');
$router->add('GET', '/api/v2/admin/matching/stats', 'Nexus\Controllers\Api\AdminMatchingApiController@getStats');

// Admin Matching - Approvals
$router->add('GET', '/api/v2/admin/matching/approvals', 'Nexus\Controllers\Api\AdminMatchingApiController@index');
$router->add('GET', '/api/v2/admin/matching/approvals/stats', 'Nexus\Controllers\Api\AdminMatchingApiController@approvalStats');
$router->add('GET', '/api/v2/admin/matching/approvals/{id}', 'Nexus\Controllers\Api\AdminMatchingApiController@show');
$router->add('POST', '/api/v2/admin/matching/approvals/{id}/approve', 'Nexus\Controllers\Api\AdminMatchingApiController@approve');
$router->add('POST', '/api/v2/admin/matching/approvals/{id}/reject', 'Nexus\Controllers\Api\AdminMatchingApiController@reject');

// Admin Help / FAQ
$router->add('GET', '/api/v2/admin/help/faqs', 'Nexus\Controllers\Api\HelpApiController@adminGetFaqs');
$router->add('POST', '/api/v2/admin/help/faqs', 'Nexus\Controllers\Api\HelpApiController@adminCreateFaq');
$router->add('PUT', '/api/v2/admin/help/faqs/{id}', 'Nexus\Controllers\Api\HelpApiController@adminUpdateFaq');
$router->add('DELETE', '/api/v2/admin/help/faqs/{id}', 'Nexus\Controllers\Api\HelpApiController@adminDeleteFaq');

// Admin Blog
$router->add('GET', '/api/v2/admin/blog', 'Nexus\Controllers\Api\AdminBlogApiController@index');
$router->add('POST', '/api/v2/admin/blog', 'Nexus\Controllers\Api\AdminBlogApiController@store');
$router->add('GET', '/api/v2/admin/blog/{id}', 'Nexus\Controllers\Api\AdminBlogApiController@show');
$router->add('PUT', '/api/v2/admin/blog/{id}', 'Nexus\Controllers\Api\AdminBlogApiController@update');
$router->add('DELETE', '/api/v2/admin/blog/{id}', 'Nexus\Controllers\Api\AdminBlogApiController@destroy');
$router->add('POST', '/api/v2/admin/blog/{id}/toggle-status', 'Nexus\Controllers\Api\AdminBlogApiController@toggleStatus');

// Admin Content Moderation - Feed Posts
$router->add('GET', '/api/v2/admin/feed/posts', 'Nexus\Controllers\Api\AdminFeedApiController@index');
$router->add('GET', '/api/v2/admin/feed/posts/{id}', 'Nexus\Controllers\Api\AdminFeedApiController@show');
$router->add('POST', '/api/v2/admin/feed/posts/{id}/hide', 'Nexus\Controllers\Api\AdminFeedApiController@hide');
$router->add('DELETE', '/api/v2/admin/feed/posts/{id}', 'Nexus\Controllers\Api\AdminFeedApiController@destroy');
$router->add('GET', '/api/v2/admin/feed/stats', 'Nexus\Controllers\Api\AdminFeedApiController@stats');

// Admin Content Moderation - Comments
$router->add('GET', '/api/v2/admin/comments', 'Nexus\Controllers\Api\AdminCommentsApiController@index');
$router->add('GET', '/api/v2/admin/comments/{id}', 'Nexus\Controllers\Api\AdminCommentsApiController@show');
$router->add('POST', '/api/v2/admin/comments/{id}/hide', 'Nexus\Controllers\Api\AdminCommentsApiController@hide');
$router->add('DELETE', '/api/v2/admin/comments/{id}', 'Nexus\Controllers\Api\AdminCommentsApiController@destroy');

// Admin Content Moderation - Reviews
$router->add('GET', '/api/v2/admin/reviews', 'Nexus\Controllers\Api\AdminReviewsApiController@index');
$router->add('GET', '/api/v2/admin/reviews/{id}', 'Nexus\Controllers\Api\AdminReviewsApiController@show');
$router->add('POST', '/api/v2/admin/reviews/{id}/flag', 'Nexus\Controllers\Api\AdminReviewsApiController@flag');
$router->add('POST', '/api/v2/admin/reviews/{id}/hide', 'Nexus\Controllers\Api\AdminReviewsApiController@hide');
$router->add('DELETE', '/api/v2/admin/reviews/{id}', 'Nexus\Controllers\Api\AdminReviewsApiController@destroy');

// Admin Content Moderation - Reports
$router->add('GET', '/api/v2/admin/reports', 'Nexus\Controllers\Api\AdminReportsApiController@index');
$router->add('GET', '/api/v2/admin/reports/stats', 'Nexus\Controllers\Api\AdminReportsApiController@stats');
// Admin Analytics & Reports — named paths MUST come before {id} catch-all
$router->add('GET', '/api/v2/admin/reports/social-value', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@socialValue');
$router->add('PUT', '/api/v2/admin/reports/social-value/config', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@updateSocialValueConfig');
$router->add('GET', '/api/v2/admin/reports/members', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@memberReports');
$router->add('GET', '/api/v2/admin/reports/hours', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@hoursReports');
$router->add('GET', '/api/v2/admin/reports/export-types', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@exportTypes');
$router->add('GET', '/api/v2/admin/reports/{type}/export', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@exportReport');
$router->add('GET', '/api/v2/admin/reports/{id}', 'Nexus\Controllers\Api\AdminReportsApiController@show');
$router->add('POST', '/api/v2/admin/reports/{id}/resolve', 'Nexus\Controllers\Api\AdminReportsApiController@resolve');
$router->add('POST', '/api/v2/admin/reports/{id}/dismiss', 'Nexus\Controllers\Api\AdminReportsApiController@dismiss');

// Admin Gamification
$router->add('GET', '/api/v2/admin/gamification/stats', 'Nexus\Controllers\Api\AdminGamificationApiController@stats');
$router->add('GET', '/api/v2/admin/gamification/badges', 'Nexus\Controllers\Api\AdminGamificationApiController@badges');
$router->add('POST', '/api/v2/admin/gamification/badges', 'Nexus\Controllers\Api\AdminGamificationApiController@createBadge');
$router->add('DELETE', '/api/v2/admin/gamification/badges/{id}', 'Nexus\Controllers\Api\AdminGamificationApiController@deleteBadge');
$router->add('GET', '/api/v2/admin/gamification/campaigns', 'Nexus\Controllers\Api\AdminGamificationApiController@campaigns');
$router->add('POST', '/api/v2/admin/gamification/campaigns', 'Nexus\Controllers\Api\AdminGamificationApiController@createCampaign');
$router->add('PUT', '/api/v2/admin/gamification/campaigns/{id}', 'Nexus\Controllers\Api\AdminGamificationApiController@updateCampaign');
$router->add('DELETE', '/api/v2/admin/gamification/campaigns/{id}', 'Nexus\Controllers\Api\AdminGamificationApiController@deleteCampaign');
$router->add('POST', '/api/v2/admin/gamification/recheck-all', 'Nexus\Controllers\Api\AdminGamificationApiController@recheckAll');
$router->add('POST', '/api/v2/admin/gamification/bulk-award', 'Nexus\Controllers\Api\AdminGamificationApiController@bulkAward');

// Admin Groups
$router->add('GET', '/api/v2/admin/groups', 'Nexus\Controllers\Api\AdminGroupsApiController@index');
$router->add('GET', '/api/v2/admin/groups/analytics', 'Nexus\Controllers\Api\AdminGroupsApiController@analytics');
$router->add('GET', '/api/v2/admin/groups/approvals', 'Nexus\Controllers\Api\AdminGroupsApiController@approvals');
$router->add('POST', '/api/v2/admin/groups/approvals/{id}/approve', 'Nexus\Controllers\Api\AdminGroupsApiController@approveMember');
$router->add('POST', '/api/v2/admin/groups/approvals/{id}/reject', 'Nexus\Controllers\Api\AdminGroupsApiController@rejectMember');
$router->add('GET', '/api/v2/admin/groups/moderation', 'Nexus\Controllers\Api\AdminGroupsApiController@moderation');

// Admin Groups - Types & Policies (Phase 3) — static paths BEFORE {id} catch-all
$router->add('GET', '/api/v2/admin/groups/types', 'Nexus\Controllers\Api\AdminGroupsApiController@getGroupTypes');
$router->add('POST', '/api/v2/admin/groups/types', 'Nexus\Controllers\Api\AdminGroupsApiController@createGroupType');
$router->add('PUT', '/api/v2/admin/groups/types/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@updateGroupType');
$router->add('DELETE', '/api/v2/admin/groups/types/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@deleteGroupType');
$router->add('GET', '/api/v2/admin/groups/types/{id}/policies', 'Nexus\Controllers\Api\AdminGroupsApiController@getPolicies');
$router->add('PUT', '/api/v2/admin/groups/types/{id}/policies', 'Nexus\Controllers\Api\AdminGroupsApiController@setPolicy');

// Admin Groups - Geocoding (Phase 3)
$router->add('POST', '/api/v2/admin/groups/batch-geocode', 'Nexus\Controllers\Api\AdminGroupsApiController@batchGeocode');

// Admin Groups - Recommendations & Ranking (Phase 3)
$router->add('GET', '/api/v2/admin/groups/recommendations', 'Nexus\Controllers\Api\AdminGroupsApiController@getRecommendationData');
$router->add('GET', '/api/v2/admin/groups/featured', 'Nexus\Controllers\Api\AdminGroupsApiController@getFeaturedGroups');
$router->add('POST', '/api/v2/admin/groups/featured/update', 'Nexus\Controllers\Api\AdminGroupsApiController@updateFeaturedGroups');

// Admin Groups - Detail & Members (Phase 3) — {id} catch-all MUST be last
$router->add('PUT', '/api/v2/admin/groups/{id}/status', 'Nexus\Controllers\Api\AdminGroupsApiController@updateStatus');
$router->add('DELETE', '/api/v2/admin/groups/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@deleteGroup');
$router->add('GET', '/api/v2/admin/groups/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@getGroup');
$router->add('PUT', '/api/v2/admin/groups/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@updateGroup');
$router->add('PUT', '/api/v2/admin/groups/{id}/toggle-featured', 'Nexus\Controllers\Api\AdminGroupsApiController@toggleFeatured');
$router->add('POST', '/api/v2/admin/groups/{id}/geocode', 'Nexus\Controllers\Api\AdminGroupsApiController@geocodeGroup');
$router->add('GET', '/api/v2/admin/groups/{groupId}/members', 'Nexus\Controllers\Api\AdminGroupsApiController@getMembers');
$router->add('POST', '/api/v2/admin/groups/{groupId}/members/{userId}/promote', 'Nexus\Controllers\Api\AdminGroupsApiController@promoteMember');
$router->add('POST', '/api/v2/admin/groups/{groupId}/members/{userId}/demote', 'Nexus\Controllers\Api\AdminGroupsApiController@demoteMember');
$router->add('DELETE', '/api/v2/admin/groups/{groupId}/members/{userId}', 'Nexus\Controllers\Api\AdminGroupsApiController@kickMember');

// Admin Timebanking
$router->add('GET', '/api/v2/admin/timebanking/stats', 'Nexus\Controllers\Api\AdminTimebankingApiController@stats');
$router->add('GET', '/api/v2/admin/timebanking/alerts', 'Nexus\Controllers\Api\AdminTimebankingApiController@alerts');
$router->add('PUT', '/api/v2/admin/timebanking/alerts/{id}', 'Nexus\Controllers\Api\AdminTimebankingApiController@updateAlert');
$router->add('POST', '/api/v2/admin/timebanking/adjust-balance', 'Nexus\Controllers\Api\AdminTimebankingApiController@adjustBalance');
$router->add('GET', '/api/v2/admin/timebanking/org-wallets', 'Nexus\Controllers\Api\AdminTimebankingApiController@orgWallets');
$router->add('GET', '/api/v2/admin/timebanking/user-report', 'Nexus\Controllers\Api\AdminTimebankingApiController@userReport');
$router->add('GET', '/api/v2/admin/timebanking/user-statement', 'Nexus\Controllers\Api\AdminTimebankingApiController@userStatement');

// Admin Enterprise
$router->add('GET', '/api/v2/admin/enterprise/dashboard', 'Nexus\Controllers\Api\AdminEnterpriseApiController@dashboard');
$router->add('GET', '/api/v2/admin/enterprise/roles', 'Nexus\Controllers\Api\AdminEnterpriseApiController@roles');
$router->add('POST', '/api/v2/admin/enterprise/roles', 'Nexus\Controllers\Api\AdminEnterpriseApiController@createRole');
$router->add('GET', '/api/v2/admin/enterprise/roles/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@showRole');
$router->add('PUT', '/api/v2/admin/enterprise/roles/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateRole');
$router->add('DELETE', '/api/v2/admin/enterprise/roles/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@deleteRole');
$router->add('GET', '/api/v2/admin/enterprise/permissions', 'Nexus\Controllers\Api\AdminEnterpriseApiController@permissions');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/dashboard', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprDashboard');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/requests', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprRequests');
$router->add('PUT', '/api/v2/admin/enterprise/gdpr/requests/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateGdprRequest');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/consents', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprConsents');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/breaches', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprBreaches');
$router->add('POST', '/api/v2/admin/enterprise/gdpr/breaches', 'Nexus\Controllers\Api\AdminEnterpriseApiController@createBreach');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/audit', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprAudit');
$router->add('GET', '/api/v2/admin/enterprise/monitoring', 'Nexus\Controllers\Api\AdminEnterpriseApiController@monitoring');
$router->add('GET', '/api/v2/admin/enterprise/monitoring/health', 'Nexus\Controllers\Api\AdminEnterpriseApiController@healthCheck');
$router->add('GET', '/api/v2/admin/enterprise/monitoring/logs', 'Nexus\Controllers\Api\AdminEnterpriseApiController@logs');
$router->add('GET', '/api/v2/admin/enterprise/config', 'Nexus\Controllers\Api\AdminEnterpriseApiController@config');
$router->add('PUT', '/api/v2/admin/enterprise/config', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateConfig');
$router->add('GET', '/api/v2/admin/enterprise/config/secrets', 'Nexus\Controllers\Api\AdminEnterpriseApiController@secrets');
$router->add('GET', '/api/v2/admin/legal-documents', 'Nexus\Controllers\Api\AdminEnterpriseApiController@legalDocs');
$router->add('POST', '/api/v2/admin/legal-documents', 'Nexus\Controllers\Api\AdminEnterpriseApiController@createLegalDoc');
// Static paths MUST come before {id} wildcard to avoid "compliance" matching {id}
$router->add('GET', '/api/v2/admin/legal-documents/compliance', 'Nexus\Controllers\Api\AdminLegalDocController@getComplianceStats');
$router->add('GET', '/api/v2/admin/legal-documents/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@showLegalDoc');
$router->add('PUT', '/api/v2/admin/legal-documents/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateLegalDoc');
$router->add('DELETE', '/api/v2/admin/legal-documents/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@deleteLegalDoc');

// Legal Document Version Management
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/versions', 'Nexus\Controllers\Api\AdminLegalDocController@getVersions');
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/versions/compare', 'Nexus\Controllers\Api\AdminLegalDocController@compareVersions');
$router->add('POST', '/api/v2/admin/legal-documents/{docId}/versions', 'Nexus\Controllers\Api\AdminLegalDocController@createVersion');
$router->add('PUT', '/api/v2/admin/legal-documents/{docId}/versions/{versionId}', 'Nexus\Controllers\Api\AdminLegalDocController@updateVersion');
$router->add('DELETE', '/api/v2/admin/legal-documents/{docId}/versions/{versionId}', 'Nexus\Controllers\Api\AdminLegalDocController@deleteVersion');
$router->add('POST', '/api/v2/admin/legal-documents/versions/{versionId}/publish', 'Nexus\Controllers\Api\AdminLegalDocController@publishVersion');
$router->add('GET', '/api/v2/admin/legal-documents/versions/{versionId}/acceptances', 'Nexus\Controllers\Api\AdminLegalDocController@getAcceptances');
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/acceptances/export', 'Nexus\Controllers\Api\AdminLegalDocController@exportAcceptances');
$router->add('POST', '/api/v2/admin/legal-documents/{docId}/versions/{versionId}/notify', 'Nexus\Controllers\Api\AdminLegalDocController@notifyUsers');
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/versions/{versionId}/pending-count', 'Nexus\Controllers\Api\AdminLegalDocController@getUsersPendingCount');

// Admin Broker Controls
$router->add('GET', '/api/v2/admin/broker/dashboard', 'Nexus\Controllers\Api\AdminBrokerApiController@dashboard');
$router->add('GET', '/api/v2/admin/broker/exchanges', 'Nexus\Controllers\Api\AdminBrokerApiController@exchanges');
$router->add('POST', '/api/v2/admin/broker/exchanges/{id}/approve', 'Nexus\Controllers\Api\AdminBrokerApiController@approveExchange');
$router->add('POST', '/api/v2/admin/broker/exchanges/{id}/reject', 'Nexus\Controllers\Api\AdminBrokerApiController@rejectExchange');
$router->add('GET', '/api/v2/admin/broker/risk-tags', 'Nexus\Controllers\Api\AdminBrokerApiController@riskTags');
$router->add('GET', '/api/v2/admin/broker/messages', 'Nexus\Controllers\Api\AdminBrokerApiController@messages');
$router->add('GET', '/api/v2/admin/broker/messages/unreviewed-count', 'Nexus\Controllers\Api\AdminBrokerApiController@unreviewedCount');
$router->add('POST', '/api/v2/admin/broker/messages/{id}/review', 'Nexus\Controllers\Api\AdminBrokerApiController@reviewMessage');
$router->add('GET', '/api/v2/admin/broker/monitoring', 'Nexus\Controllers\Api\AdminBrokerApiController@monitoring');
$router->add('POST', '/api/v2/admin/broker/messages/{id}/flag', 'Nexus\Controllers\Api\AdminBrokerApiController@flagMessage');
$router->add('POST', '/api/v2/admin/broker/monitoring/{userId}', 'Nexus\Controllers\Api\AdminBrokerApiController@setMonitoring');
$router->add('POST', '/api/v2/admin/broker/risk-tags/{listingId}', 'Nexus\Controllers\Api\AdminBrokerApiController@saveRiskTag');
$router->add('DELETE', '/api/v2/admin/broker/risk-tags/{listingId}', 'Nexus\Controllers\Api\AdminBrokerApiController@removeRiskTag');
$router->add('GET', '/api/v2/admin/broker/configuration', 'Nexus\Controllers\Api\AdminBrokerApiController@getConfiguration');
$router->add('POST', '/api/v2/admin/broker/configuration', 'Nexus\Controllers\Api\AdminBrokerApiController@saveConfiguration');
$router->add('GET', '/api/v2/admin/broker/exchanges/{id}', 'Nexus\Controllers\Api\AdminBrokerApiController@showExchange');
$router->add('GET', '/api/v2/admin/broker/messages/{id}', 'Nexus\Controllers\Api\AdminBrokerApiController@showMessage');
$router->add('POST', '/api/v2/admin/broker/messages/{id}/approve', 'Nexus\Controllers\Api\AdminBrokerApiController@approveMessage');
$router->add('GET', '/api/v2/admin/broker/archives', 'Nexus\Controllers\Api\AdminBrokerApiController@archives');
$router->add('GET', '/api/v2/admin/broker/archives/{id}', 'Nexus\Controllers\Api\AdminBrokerApiController@showArchive');

// Admin Vetting Records (TOL2 compliance — DBS/Garda vetting)
$router->add('GET', '/api/v2/admin/vetting/stats', 'Nexus\Controllers\Api\AdminVettingApiController@stats');
$router->add('GET', '/api/v2/admin/vetting/user/{userId}', 'Nexus\Controllers\Api\AdminVettingApiController@getUserRecords');
$router->add('GET', '/api/v2/admin/vetting', 'Nexus\Controllers\Api\AdminVettingApiController@list');
$router->add('GET', '/api/v2/admin/vetting/{id}', 'Nexus\Controllers\Api\AdminVettingApiController@show');
$router->add('POST', '/api/v2/admin/vetting/bulk', 'Nexus\Controllers\Api\AdminVettingApiController@bulk');
$router->add('POST', '/api/v2/admin/vetting', 'Nexus\Controllers\Api\AdminVettingApiController@store');
$router->add('PUT', '/api/v2/admin/vetting/{id}', 'Nexus\Controllers\Api\AdminVettingApiController@update');
$router->add('POST', '/api/v2/admin/vetting/{id}/verify', 'Nexus\Controllers\Api\AdminVettingApiController@verify');
$router->add('POST', '/api/v2/admin/vetting/{id}/reject', 'Nexus\Controllers\Api\AdminVettingApiController@reject');
$router->add('DELETE', '/api/v2/admin/vetting/{id}', 'Nexus\Controllers\Api\AdminVettingApiController@destroy');
$router->add('POST', '/api/v2/admin/vetting/{id}/upload', 'Nexus\Controllers\Api\AdminVettingApiController@uploadDocument');

// Admin Insurance Certificates (compliance)
$router->add('GET', '/api/v2/admin/insurance/stats', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@stats');
$router->add('GET', '/api/v2/admin/insurance/user/{userId}', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@getUserCertificates');
$router->add('GET', '/api/v2/admin/insurance', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@list');
$router->add('GET', '/api/v2/admin/insurance/{id}', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@show');
$router->add('POST', '/api/v2/admin/insurance', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@store');
$router->add('PUT', '/api/v2/admin/insurance/{id}', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@update');
$router->add('POST', '/api/v2/admin/insurance/{id}/verify', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@verify');
$router->add('POST', '/api/v2/admin/insurance/{id}/reject', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@reject');
$router->add('DELETE', '/api/v2/admin/insurance/{id}', 'Nexus\Controllers\Api\AdminInsuranceCertificateApiController@destroy');

// Admin Newsletters
$router->add('GET', '/api/v2/admin/newsletters', 'Nexus\Controllers\Api\AdminNewsletterApiController@index');
$router->add('POST', '/api/v2/admin/newsletters', 'Nexus\Controllers\Api\AdminNewsletterApiController@store');
$router->add('GET', '/api/v2/admin/newsletters/subscribers', 'Nexus\Controllers\Api\AdminNewsletterApiController@subscribers');
$router->add('POST', '/api/v2/admin/newsletters/subscribers', 'Nexus\Controllers\Api\AdminNewsletterApiController@addSubscriber');
$router->add('POST', '/api/v2/admin/newsletters/subscribers/import', 'Nexus\Controllers\Api\AdminNewsletterApiController@importSubscribers');
$router->add('GET', '/api/v2/admin/newsletters/subscribers/export', 'Nexus\Controllers\Api\AdminNewsletterApiController@exportSubscribers');
$router->add('POST', '/api/v2/admin/newsletters/subscribers/sync', 'Nexus\Controllers\Api\AdminNewsletterApiController@syncPlatformMembers');
$router->add('DELETE', '/api/v2/admin/newsletters/subscribers/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@removeSubscriber');
$router->add('GET', '/api/v2/admin/newsletters/segments', 'Nexus\Controllers\Api\AdminNewsletterApiController@segments');
$router->add('POST', '/api/v2/admin/newsletters/segments', 'Nexus\Controllers\Api\AdminNewsletterApiController@storeSegment');
$router->add('POST', '/api/v2/admin/newsletters/segments/preview', 'Nexus\Controllers\Api\AdminNewsletterApiController@previewSegment');
$router->add('GET', '/api/v2/admin/newsletters/segments/suggestions', 'Nexus\Controllers\Api\AdminNewsletterApiController@getSegmentSuggestions');
$router->add('GET', '/api/v2/admin/newsletters/segments/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@showSegment');
$router->add('PUT', '/api/v2/admin/newsletters/segments/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@updateSegment');
$router->add('DELETE', '/api/v2/admin/newsletters/segments/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@destroySegment');
$router->add('GET', '/api/v2/admin/newsletters/templates', 'Nexus\Controllers\Api\AdminNewsletterApiController@templates');
$router->add('POST', '/api/v2/admin/newsletters/templates', 'Nexus\Controllers\Api\AdminNewsletterApiController@storeTemplate');
$router->add('GET', '/api/v2/admin/newsletters/templates/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@showTemplate');
$router->add('PUT', '/api/v2/admin/newsletters/templates/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@updateTemplate');
$router->add('DELETE', '/api/v2/admin/newsletters/templates/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@destroyTemplate');
$router->add('POST', '/api/v2/admin/newsletters/templates/{id}/duplicate', 'Nexus\Controllers\Api\AdminNewsletterApiController@duplicateTemplate');
$router->add('GET', '/api/v2/admin/newsletters/templates/{id}/preview', 'Nexus\Controllers\Api\AdminNewsletterApiController@previewTemplate');
$router->add('GET', '/api/v2/admin/newsletters/analytics', 'Nexus\Controllers\Api\AdminNewsletterApiController@analytics');
$router->add('GET', '/api/v2/admin/newsletters/bounces', 'Nexus\Controllers\Api\AdminNewsletterApiController@getBounces');
$router->add('GET', '/api/v2/admin/newsletters/suppression-list', 'Nexus\Controllers\Api\AdminNewsletterApiController@getSuppressionList');
$router->add('POST', '/api/v2/admin/newsletters/suppression-list/{email}/unsuppress', 'Nexus\Controllers\Api\AdminNewsletterApiController@unsuppress');
$router->add('POST', '/api/v2/admin/newsletters/suppression-list/{email}/suppress', 'Nexus\Controllers\Api\AdminNewsletterApiController@suppress');
$router->add('GET', '/api/v2/admin/newsletters/send-time-optimizer', 'Nexus\Controllers\Api\AdminNewsletterApiController@getSendTimeData');
$router->add('GET', '/api/v2/admin/newsletters/diagnostics', 'Nexus\Controllers\Api\AdminNewsletterApiController@getDiagnostics');
$router->add('GET', '/api/v2/admin/newsletters/bounce-trends', 'Nexus\Controllers\Api\AdminNewsletterApiController@getBounceTrends');
$router->add('POST', '/api/v2/admin/newsletters/recipient-count', 'Nexus\Controllers\Api\AdminNewsletterApiController@recipientCount');
$router->add('GET', '/api/v2/admin/newsletters/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@show');
$router->add('GET', '/api/v2/admin/newsletters/{id}/resend-info', 'Nexus\Controllers\Api\AdminNewsletterApiController@getResendInfo');
$router->add('POST', '/api/v2/admin/newsletters/{id}/resend', 'Nexus\Controllers\Api\AdminNewsletterApiController@resend');
$router->add('POST', '/api/v2/admin/newsletters/{id}/send', 'Nexus\Controllers\Api\AdminNewsletterApiController@sendNewsletter');
$router->add('POST', '/api/v2/admin/newsletters/{id}/send-test', 'Nexus\Controllers\Api\AdminNewsletterApiController@sendTest');
$router->add('POST', '/api/v2/admin/newsletters/{id}/duplicate', 'Nexus\Controllers\Api\AdminNewsletterApiController@duplicateNewsletter');
$router->add('GET', '/api/v2/admin/newsletters/{id}/activity', 'Nexus\Controllers\Api\AdminNewsletterApiController@activity');
$router->add('GET', '/api/v2/admin/newsletters/{id}/openers', 'Nexus\Controllers\Api\AdminNewsletterApiController@openers');
$router->add('GET', '/api/v2/admin/newsletters/{id}/clickers', 'Nexus\Controllers\Api\AdminNewsletterApiController@clickers');
$router->add('GET', '/api/v2/admin/newsletters/{id}/non-openers', 'Nexus\Controllers\Api\AdminNewsletterApiController@nonOpeners');
$router->add('GET', '/api/v2/admin/newsletters/{id}/openers-no-click', 'Nexus\Controllers\Api\AdminNewsletterApiController@openersNoClick');
$router->add('GET', '/api/v2/admin/newsletters/{id}/email-clients', 'Nexus\Controllers\Api\AdminNewsletterApiController@emailClients');
$router->add('GET', '/api/v2/admin/newsletters/{id}/stats', 'Nexus\Controllers\Api\AdminNewsletterApiController@stats');
$router->add('POST', '/api/v2/admin/newsletters/{id}/ab-winner', 'Nexus\Controllers\Api\AdminNewsletterApiController@selectAbWinner');
$router->add('PUT', '/api/v2/admin/newsletters/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@update');
$router->add('DELETE', '/api/v2/admin/newsletters/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@destroy');

// Admin Volunteering
$router->add('GET', '/api/v2/admin/volunteering', 'Nexus\Controllers\Api\AdminVolunteeringApiController@index');
$router->add('GET', '/api/v2/admin/volunteering/approvals', 'Nexus\Controllers\Api\AdminVolunteeringApiController@approvals');
$router->add('GET', '/api/v2/admin/volunteering/organizations', 'Nexus\Controllers\Api\AdminVolunteeringApiController@organizations');
$router->add('POST', '/api/v2/admin/volunteering/approvals/{id}/approve', 'Nexus\Controllers\Api\AdminVolunteeringApiController@approveApplication');
$router->add('POST', '/api/v2/admin/volunteering/approvals/{id}/decline', 'Nexus\Controllers\Api\AdminVolunteeringApiController@declineApplication');

// Admin Events
$router->add('GET', '/api/v2/admin/events', 'Nexus\Controllers\Api\AdminEventsApiController@index');
$router->add('GET', '/api/v2/admin/events/{id}', 'Nexus\Controllers\Api\AdminEventsApiController@show');
$router->add('DELETE', '/api/v2/admin/events/{id}', 'Nexus\Controllers\Api\AdminEventsApiController@destroy');
$router->add('POST', '/api/v2/admin/events/{id}/cancel', 'Nexus\Controllers\Api\AdminEventsApiController@cancel');

// Admin Polls
$router->add('GET', '/api/v2/admin/polls', 'Nexus\Controllers\Api\AdminPollsApiController@index');
$router->add('GET', '/api/v2/admin/polls/{id}', 'Nexus\Controllers\Api\AdminPollsApiController@show');
$router->add('DELETE', '/api/v2/admin/polls/{id}', 'Nexus\Controllers\Api\AdminPollsApiController@destroy');

// Admin Goals
$router->add('GET', '/api/v2/admin/goals', 'Nexus\Controllers\Api\AdminGoalsApiController@index');
$router->add('GET', '/api/v2/admin/goals/{id}', 'Nexus\Controllers\Api\AdminGoalsApiController@show');
$router->add('DELETE', '/api/v2/admin/goals/{id}', 'Nexus\Controllers\Api\AdminGoalsApiController@destroy');

// Admin Resources / Knowledge Base
$router->add('GET', '/api/v2/admin/resources', 'Nexus\Controllers\Api\AdminResourcesApiController@index');
$router->add('GET', '/api/v2/admin/resources/{id}', 'Nexus\Controllers\Api\AdminResourcesApiController@show');
$router->add('DELETE', '/api/v2/admin/resources/{id}', 'Nexus\Controllers\Api\AdminResourcesApiController@destroy');

// Admin Jobs
$router->add('GET', '/api/v2/admin/jobs', 'Nexus\Controllers\Api\AdminJobsApiController@index');
$router->add('GET', '/api/v2/admin/jobs/{id}', 'Nexus\Controllers\Api\AdminJobsApiController@show');
$router->add('DELETE', '/api/v2/admin/jobs/{id}', 'Nexus\Controllers\Api\AdminJobsApiController@destroy');
$router->add('POST', '/api/v2/admin/jobs/{id}/feature', 'Nexus\Controllers\Api\AdminJobsApiController@feature');
$router->add('POST', '/api/v2/admin/jobs/{id}/unfeature', 'Nexus\Controllers\Api\AdminJobsApiController@unfeature');

// Admin Ideation / Challenges
$router->add('GET', '/api/v2/admin/ideation', 'Nexus\Controllers\Api\AdminIdeationApiController@index');
$router->add('GET', '/api/v2/admin/ideation/{id}', 'Nexus\Controllers\Api\AdminIdeationApiController@show');
$router->add('DELETE', '/api/v2/admin/ideation/{id}', 'Nexus\Controllers\Api\AdminIdeationApiController@destroy');
$router->add('POST', '/api/v2/admin/ideation/{id}/status', 'Nexus\Controllers\Api\AdminIdeationApiController@updateStatus');

// Admin Federation
$router->add('GET', '/api/v2/admin/federation/settings', 'Nexus\Controllers\Api\AdminFederationApiController@settings');
$router->add('PUT', '/api/v2/admin/federation/settings', 'Nexus\Controllers\Api\AdminFederationApiController@updateSettings');
$router->add('GET', '/api/v2/admin/federation/partnerships', 'Nexus\Controllers\Api\AdminFederationApiController@partnerships');
$router->add('POST', '/api/v2/admin/federation/partnerships/{id}/approve', 'Nexus\Controllers\Api\AdminFederationApiController@approvePartnership');
$router->add('POST', '/api/v2/admin/federation/partnerships/{id}/reject', 'Nexus\Controllers\Api\AdminFederationApiController@rejectPartnership');
$router->add('POST', '/api/v2/admin/federation/partnerships/{id}/terminate', 'Nexus\Controllers\Api\AdminFederationApiController@terminatePartnership');
$router->add('POST', '/api/v2/admin/federation/partnerships/request', 'Nexus\Controllers\Api\AdminFederationApiController@requestPartnership');
$router->add('GET', '/api/v2/admin/federation/directory', 'Nexus\Controllers\Api\AdminFederationApiController@directory');
$router->add('GET', '/api/v2/admin/federation/directory/profile', 'Nexus\Controllers\Api\AdminFederationApiController@profile');
$router->add('PUT', '/api/v2/admin/federation/directory/profile', 'Nexus\Controllers\Api\AdminFederationApiController@updateProfile');
$router->add('GET', '/api/v2/admin/federation/analytics', 'Nexus\Controllers\Api\AdminFederationApiController@analytics');
$router->add('GET', '/api/v2/admin/federation/api-keys', 'Nexus\Controllers\Api\AdminFederationApiController@apiKeys');
$router->add('POST', '/api/v2/admin/federation/api-keys', 'Nexus\Controllers\Api\AdminFederationApiController@createApiKey');
$router->add('GET', '/api/v2/admin/federation/data', 'Nexus\Controllers\Api\AdminFederationApiController@dataManagement');
$router->add('GET', '/api/v2/admin/federation/export/{type}', 'Nexus\Controllers\Api\AdminFederationApiController@exportData');

// Federation V2 (user-facing, for React frontend)
$router->add('GET', '/api/v2/federation/status', 'Nexus\Controllers\Api\FederationV2ApiController@status');
$router->add('POST', '/api/v2/federation/opt-in', 'Nexus\Controllers\Api\FederationV2ApiController@optIn');
$router->add('POST', '/api/v2/federation/setup', 'Nexus\Controllers\Api\FederationV2ApiController@setup');
$router->add('POST', '/api/v2/federation/opt-out', 'Nexus\Controllers\Api\FederationV2ApiController@optOut');
$router->add('GET', '/api/v2/federation/partners', 'Nexus\Controllers\Api\FederationV2ApiController@partners');
$router->add('GET', '/api/v2/federation/activity', 'Nexus\Controllers\Api\FederationV2ApiController@activity');
$router->add('GET', '/api/v2/federation/events', 'Nexus\Controllers\Api\FederationV2ApiController@events');
$router->add('GET', '/api/v2/federation/listings', 'Nexus\Controllers\Api\FederationV2ApiController@listings');
$router->add('GET', '/api/v2/federation/members', 'Nexus\Controllers\Api\FederationV2ApiController@members');
$router->add('GET', '/api/v2/federation/members/{id}', 'Nexus\Controllers\Api\FederationV2ApiController@member');
$router->add('GET', '/api/v2/federation/messages', 'Nexus\Controllers\Api\FederationV2ApiController@messages');
$router->add('POST', '/api/v2/federation/messages', 'Nexus\Controllers\Api\FederationV2ApiController@sendMessage');
$router->add('POST', '/api/v2/federation/messages/{id}/mark-read', 'Nexus\Controllers\Api\FederationV2ApiController@markMessageRead');
$router->add('GET', '/api/v2/federation/settings', 'Nexus\Controllers\Api\FederationV2ApiController@getSettings');
$router->add('PUT', '/api/v2/federation/settings', 'Nexus\Controllers\Api\FederationV2ApiController@updateSettings');

// Admin Pages
$router->add('GET', '/api/v2/admin/pages', 'Nexus\Controllers\Api\AdminContentApiController@getPages');
$router->add('POST', '/api/v2/admin/pages', 'Nexus\Controllers\Api\AdminContentApiController@createPage');
$router->add('GET', '/api/v2/admin/pages/{id}', 'Nexus\Controllers\Api\AdminContentApiController@getPage');
$router->add('PUT', '/api/v2/admin/pages/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updatePage');
$router->add('DELETE', '/api/v2/admin/pages/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deletePage');

// Admin Menus
$router->add('GET', '/api/v2/admin/menus', 'Nexus\Controllers\Api\AdminContentApiController@getMenus');
$router->add('POST', '/api/v2/admin/menus', 'Nexus\Controllers\Api\AdminContentApiController@createMenu');
$router->add('GET', '/api/v2/admin/menus/{id}', 'Nexus\Controllers\Api\AdminContentApiController@getMenu');
$router->add('PUT', '/api/v2/admin/menus/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updateMenu');
$router->add('DELETE', '/api/v2/admin/menus/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deleteMenu');
$router->add('GET', '/api/v2/admin/menus/{id}/items', 'Nexus\Controllers\Api\AdminContentApiController@getMenuItems');
$router->add('POST', '/api/v2/admin/menus/{id}/items', 'Nexus\Controllers\Api\AdminContentApiController@createMenuItem');
$router->add('POST', '/api/v2/admin/menus/{id}/items/reorder', 'Nexus\Controllers\Api\AdminContentApiController@reorderMenuItems');

// Admin Menu Items (direct item operations)
$router->add('PUT', '/api/v2/admin/menu-items/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updateMenuItem');
$router->add('DELETE', '/api/v2/admin/menu-items/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deleteMenuItem');

// Admin Plans & Subscriptions
$router->add('GET', '/api/v2/admin/plans', 'Nexus\Controllers\Api\AdminContentApiController@getPlans');
$router->add('POST', '/api/v2/admin/plans', 'Nexus\Controllers\Api\AdminContentApiController@createPlan');
$router->add('GET', '/api/v2/admin/plans/{id}', 'Nexus\Controllers\Api\AdminContentApiController@getPlan');
$router->add('PUT', '/api/v2/admin/plans/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updatePlan');
$router->add('DELETE', '/api/v2/admin/plans/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deletePlan');
$router->add('GET', '/api/v2/admin/subscriptions', 'Nexus\Controllers\Api\AdminContentApiController@getSubscriptions');

// Admin Tools - SEO & Redirects
$router->add('GET', '/api/v2/admin/tools/redirects', 'Nexus\Controllers\Api\AdminToolsApiController@getRedirects');
$router->add('POST', '/api/v2/admin/tools/redirects', 'Nexus\Controllers\Api\AdminToolsApiController@createRedirect');
$router->add('DELETE', '/api/v2/admin/tools/redirects/{id}', 'Nexus\Controllers\Api\AdminToolsApiController@deleteRedirect');

// Admin Tools - 404 Error Tracking
$router->add('GET', '/api/v2/admin/tools/404-errors', 'Nexus\Controllers\Api\AdminToolsApiController@get404Errors');
$router->add('DELETE', '/api/v2/admin/tools/404-errors/{id}', 'Nexus\Controllers\Api\AdminToolsApiController@delete404Error');

// Admin Tools - Health Check, WebP, Seed, Blog Backups, IP Debug
$router->add('POST', '/api/v2/admin/tools/health-check', 'Nexus\Controllers\Api\AdminToolsApiController@runHealthCheck');
$router->add('GET', '/api/v2/admin/tools/ip-debug', 'Nexus\Controllers\Api\AdminToolsApiController@ipDebug');
$router->add('GET', '/api/v2/admin/tools/webp-stats', 'Nexus\Controllers\Api\AdminToolsApiController@getWebpStats');
$router->add('POST', '/api/v2/admin/tools/webp-convert', 'Nexus\Controllers\Api\AdminToolsApiController@runWebpConversion');
$router->add('POST', '/api/v2/admin/tools/seed', 'Nexus\Controllers\Api\AdminToolsApiController@runSeedGenerator');
$router->add('GET', '/api/v2/admin/tools/blog-backups', 'Nexus\Controllers\Api\AdminToolsApiController@getBlogBackups');
$router->add('POST', '/api/v2/admin/tools/blog-backups/{id}/restore', 'Nexus\Controllers\Api\AdminToolsApiController@restoreBlogBackup');

// Admin Tools - SEO Audit
$router->add('GET', '/api/v2/admin/tools/seo-audit', 'Nexus\Controllers\Api\AdminToolsApiController@getSeoAudit');
$router->add('POST', '/api/v2/admin/tools/seo-audit', 'Nexus\Controllers\Api\AdminToolsApiController@runSeoAudit');

// Admin Deliverability
$router->add('GET', '/api/v2/admin/deliverability/dashboard', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getDashboard');
$router->add('GET', '/api/v2/admin/deliverability/analytics', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getAnalytics');
$router->add('GET', '/api/v2/admin/deliverability', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getDeliverables');
$router->add('POST', '/api/v2/admin/deliverability', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@createDeliverable');
$router->add('GET', '/api/v2/admin/deliverability/{id}', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getDeliverable');
$router->add('PUT', '/api/v2/admin/deliverability/{id}', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@updateDeliverable');
$router->add('DELETE', '/api/v2/admin/deliverability/{id}', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@deleteDeliverable');
$router->add('POST', '/api/v2/admin/deliverability/{id}/comments', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@addComment');

// Super Admin Panel (requires super_admin or god role)
// Dashboard
$router->add('GET', '/api/v2/admin/super/dashboard', 'Nexus\Controllers\Api\AdminSuperApiController@dashboard');

// Tenants
$router->add('GET', '/api/v2/admin/super/tenants', 'Nexus\Controllers\Api\AdminSuperApiController@tenantList');
$router->add('GET', '/api/v2/admin/super/tenants/hierarchy', 'Nexus\Controllers\Api\AdminSuperApiController@tenantHierarchy');
$router->add('POST', '/api/v2/admin/super/tenants', 'Nexus\Controllers\Api\AdminSuperApiController@tenantCreate');
$router->add('GET', '/api/v2/admin/super/tenants/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@tenantShow');
$router->add('PUT', '/api/v2/admin/super/tenants/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@tenantUpdate');
$router->add('DELETE', '/api/v2/admin/super/tenants/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@tenantDelete');
$router->add('POST', '/api/v2/admin/super/tenants/{id}/reactivate', 'Nexus\Controllers\Api\AdminSuperApiController@tenantReactivate');
$router->add('POST', '/api/v2/admin/super/tenants/{id}/toggle-hub', 'Nexus\Controllers\Api\AdminSuperApiController@tenantToggleHub');
$router->add('POST', '/api/v2/admin/super/tenants/{id}/move', 'Nexus\Controllers\Api\AdminSuperApiController@tenantMove');

// Users (Cross-Tenant)
$router->add('GET', '/api/v2/admin/super/users', 'Nexus\Controllers\Api\AdminSuperApiController@userList');
$router->add('POST', '/api/v2/admin/super/users', 'Nexus\Controllers\Api\AdminSuperApiController@userCreate');
$router->add('GET', '/api/v2/admin/super/users/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@userShow');
$router->add('PUT', '/api/v2/admin/super/users/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@userUpdate');
$router->add('POST', '/api/v2/admin/super/users/{id}/grant-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userGrantSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/revoke-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userRevokeSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/grant-global-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userGrantGlobalSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/revoke-global-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userRevokeGlobalSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/move-tenant', 'Nexus\Controllers\Api\AdminSuperApiController@userMoveTenant');
$router->add('POST', '/api/v2/admin/super/users/{id}/move-and-promote', 'Nexus\Controllers\Api\AdminSuperApiController@userMoveAndPromote');

// Bulk Operations
$router->add('POST', '/api/v2/admin/super/bulk/move-users', 'Nexus\Controllers\Api\AdminSuperApiController@bulkMoveUsers');
$router->add('POST', '/api/v2/admin/super/bulk/update-tenants', 'Nexus\Controllers\Api\AdminSuperApiController@bulkUpdateTenants');

// Audit
$router->add('GET', '/api/v2/admin/super/audit', 'Nexus\Controllers\Api\AdminSuperApiController@audit');

// Federation Controls
$router->add('GET', '/api/v2/admin/super/federation', 'Nexus\Controllers\Api\AdminSuperApiController@federationOverview');
$router->add('GET', '/api/v2/admin/super/federation/system-controls', 'Nexus\Controllers\Api\AdminSuperApiController@federationGetSystemControls');
$router->add('PUT', '/api/v2/admin/super/federation/system-controls', 'Nexus\Controllers\Api\AdminSuperApiController@federationUpdateSystemControls');
$router->add('POST', '/api/v2/admin/super/federation/emergency-lockdown', 'Nexus\Controllers\Api\AdminSuperApiController@federationEmergencyLockdown');
$router->add('POST', '/api/v2/admin/super/federation/lift-lockdown', 'Nexus\Controllers\Api\AdminSuperApiController@federationLiftLockdown');
$router->add('GET', '/api/v2/admin/super/federation/whitelist', 'Nexus\Controllers\Api\AdminSuperApiController@federationGetWhitelist');
$router->add('POST', '/api/v2/admin/super/federation/whitelist', 'Nexus\Controllers\Api\AdminSuperApiController@federationAddToWhitelist');
$router->add('DELETE', '/api/v2/admin/super/federation/whitelist/{tenantId}', 'Nexus\Controllers\Api\AdminSuperApiController@federationRemoveFromWhitelist');
$router->add('GET', '/api/v2/admin/super/federation/partnerships', 'Nexus\Controllers\Api\AdminSuperApiController@federationPartnerships');
$router->add('POST', '/api/v2/admin/super/federation/partnerships/{id}/suspend', 'Nexus\Controllers\Api\AdminSuperApiController@federationSuspendPartnership');
$router->add('POST', '/api/v2/admin/super/federation/partnerships/{id}/terminate', 'Nexus\Controllers\Api\AdminSuperApiController@federationTerminatePartnership');
$router->add('GET', '/api/v2/admin/super/federation/tenant/{id}/features', 'Nexus\Controllers\Api\AdminSuperApiController@federationGetTenantFeatures');
$router->add('PUT', '/api/v2/admin/super/federation/tenant/{id}/features', 'Nexus\Controllers\Api\AdminSuperApiController@federationUpdateTenantFeature');

// Admin CRM (Member Notes, Coordinator Tasks, Tags, Funnel)
$router->add('GET', '/api/v2/admin/crm/dashboard', 'Nexus\Controllers\Api\AdminCrmApiController@dashboard');
$router->add('GET', '/api/v2/admin/crm/funnel', 'Nexus\Controllers\Api\AdminCrmApiController@funnel');
$router->add('GET', '/api/v2/admin/crm/admins', 'Nexus\Controllers\Api\AdminCrmApiController@listAdmins');
$router->add('GET', '/api/v2/admin/crm/notes', 'Nexus\Controllers\Api\AdminCrmApiController@listNotes');
$router->add('POST', '/api/v2/admin/crm/notes', 'Nexus\Controllers\Api\AdminCrmApiController@createNote');
$router->add('PUT', '/api/v2/admin/crm/notes/{id}', 'Nexus\Controllers\Api\AdminCrmApiController@updateNote');
$router->add('DELETE', '/api/v2/admin/crm/notes/{id}', 'Nexus\Controllers\Api\AdminCrmApiController@deleteNote');
$router->add('GET', '/api/v2/admin/crm/tasks', 'Nexus\Controllers\Api\AdminCrmApiController@listTasks');
$router->add('POST', '/api/v2/admin/crm/tasks', 'Nexus\Controllers\Api\AdminCrmApiController@createTask');
$router->add('PUT', '/api/v2/admin/crm/tasks/{id}', 'Nexus\Controllers\Api\AdminCrmApiController@updateTask');
$router->add('DELETE', '/api/v2/admin/crm/tasks/{id}', 'Nexus\Controllers\Api\AdminCrmApiController@deleteTask');
$router->add('GET', '/api/v2/admin/crm/tags', 'Nexus\Controllers\Api\AdminCrmApiController@listTags');
$router->add('POST', '/api/v2/admin/crm/tags', 'Nexus\Controllers\Api\AdminCrmApiController@addTag');
$router->add('DELETE', '/api/v2/admin/crm/tags/bulk', 'Nexus\Controllers\Api\AdminCrmApiController@bulkRemoveTag');
$router->add('DELETE', '/api/v2/admin/crm/tags/{id}', 'Nexus\Controllers\Api\AdminCrmApiController@removeTag');
$router->add('GET', '/api/v2/admin/crm/timeline', 'Nexus\Controllers\Api\AdminCrmApiController@timeline');
$router->add('GET', '/api/v2/admin/crm/export/notes', 'Nexus\Controllers\Api\AdminCrmApiController@exportNotes');
$router->add('GET', '/api/v2/admin/crm/export/tasks', 'Nexus\Controllers\Api\AdminCrmApiController@exportTasks');
$router->add('GET', '/api/v2/admin/crm/export/dashboard', 'Nexus\Controllers\Api\AdminCrmApiController@exportDashboard');

