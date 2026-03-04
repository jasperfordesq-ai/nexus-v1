<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// ============================================
// API V2 - JOB VACANCIES
// ============================================
$router->add('GET', '/api/v2/jobs', 'Nexus\Controllers\Api\JobVacanciesApiController@index');
$router->add('POST', '/api/v2/jobs', 'Nexus\Controllers\Api\JobVacanciesApiController@store');
$router->add('GET', '/api/v2/jobs/saved', 'Nexus\Controllers\Api\JobVacanciesApiController@savedJobs');
$router->add('GET', '/api/v2/jobs/my-applications', 'Nexus\Controllers\Api\JobVacanciesApiController@myApplications');
$router->add('GET', '/api/v2/jobs/alerts', 'Nexus\Controllers\Api\JobVacanciesApiController@listAlerts');
$router->add('POST', '/api/v2/jobs/alerts', 'Nexus\Controllers\Api\JobVacanciesApiController@createAlert');
$router->add('DELETE', '/api/v2/jobs/alerts/{id}', 'Nexus\Controllers\Api\JobVacanciesApiController@deleteAlert');
$router->add('PUT', '/api/v2/jobs/alerts/{id}/unsubscribe', 'Nexus\Controllers\Api\JobVacanciesApiController@unsubscribeAlert');
$router->add('PUT', '/api/v2/jobs/alerts/{id}/resubscribe', 'Nexus\Controllers\Api\JobVacanciesApiController@resubscribeAlert');
$router->add('GET', '/api/v2/jobs/{id}', 'Nexus\Controllers\Api\JobVacanciesApiController@show');
$router->add('PUT', '/api/v2/jobs/{id}', 'Nexus\Controllers\Api\JobVacanciesApiController@update');
$router->add('DELETE', '/api/v2/jobs/{id}', 'Nexus\Controllers\Api\JobVacanciesApiController@destroy');
$router->add('POST', '/api/v2/jobs/{id}/apply', 'Nexus\Controllers\Api\JobVacanciesApiController@apply');
$router->add('POST', '/api/v2/jobs/{id}/save', 'Nexus\Controllers\Api\JobVacanciesApiController@saveJob');
$router->add('DELETE', '/api/v2/jobs/{id}/save', 'Nexus\Controllers\Api\JobVacanciesApiController@unsaveJob');
$router->add('GET', '/api/v2/jobs/{id}/match', 'Nexus\Controllers\Api\JobVacanciesApiController@matchPercentage');
$router->add('GET', '/api/v2/jobs/{id}/qualified', 'Nexus\Controllers\Api\JobVacanciesApiController@qualificationAssessment');
$router->add('GET', '/api/v2/jobs/{id}/applications', 'Nexus\Controllers\Api\JobVacanciesApiController@applications');
$router->add('GET', '/api/v2/jobs/{id}/analytics', 'Nexus\Controllers\Api\JobVacanciesApiController@analytics');
$router->add('POST', '/api/v2/jobs/{id}/renew', 'Nexus\Controllers\Api\JobVacanciesApiController@renewJob');
// Feature/unfeature routes are admin-only, served via /api/v2/admin/jobs/{id}/feature and /unfeature
// Legacy public routes kept but point to admin-gated controller methods
$router->add('POST', '/api/v2/jobs/{id}/feature', 'Nexus\Controllers\Api\JobVacanciesApiController@featureJob');
$router->add('DELETE', '/api/v2/jobs/{id}/feature', 'Nexus\Controllers\Api\JobVacanciesApiController@unfeatureJob');
$router->add('PUT', '/api/v2/jobs/applications/{id}', 'Nexus\Controllers\Api\JobVacanciesApiController@updateApplication');
$router->add('GET', '/api/v2/jobs/applications/{id}/history', 'Nexus\Controllers\Api\JobVacanciesApiController@applicationHistory');

// ============================================
// API V2 - IDEATION CHALLENGES
// ============================================
$router->add('GET', '/api/v2/ideation-challenges', 'Nexus\Controllers\Api\IdeationChallengesApiController@index');
$router->add('POST', '/api/v2/ideation-challenges', 'Nexus\Controllers\Api\IdeationChallengesApiController@store');
$router->add('GET', '/api/v2/ideation-ideas/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@showIdea');
$router->add('PUT', '/api/v2/ideation-ideas/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateIdea');
$router->add('PUT', '/api/v2/ideation-ideas/{id}/draft', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateDraft');
$router->add('DELETE', '/api/v2/ideation-ideas/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteIdea');
$router->add('POST', '/api/v2/ideation-ideas/{id}/vote', 'Nexus\Controllers\Api\IdeationChallengesApiController@voteIdea');
$router->add('PUT', '/api/v2/ideation-ideas/{id}/status', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateIdeaStatus');
$router->add('GET', '/api/v2/ideation-ideas/{id}/comments', 'Nexus\Controllers\Api\IdeationChallengesApiController@comments');
$router->add('POST', '/api/v2/ideation-ideas/{id}/comments', 'Nexus\Controllers\Api\IdeationChallengesApiController@addComment');
$router->add('DELETE', '/api/v2/ideation-comments/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteComment');
$router->add('GET', '/api/v2/ideation-challenges/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@show');
$router->add('PUT', '/api/v2/ideation-challenges/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@update');
$router->add('DELETE', '/api/v2/ideation-challenges/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@destroy');
$router->add('PUT', '/api/v2/ideation-challenges/{id}/status', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateStatus');
$router->add('GET', '/api/v2/ideation-challenges/{id}/ideas/drafts', 'Nexus\Controllers\Api\IdeationChallengesApiController@ideaDrafts');
$router->add('GET', '/api/v2/ideation-challenges/{id}/ideas', 'Nexus\Controllers\Api\IdeationChallengesApiController@ideas');
$router->add('POST', '/api/v2/ideation-challenges/{id}/ideas', 'Nexus\Controllers\Api\IdeationChallengesApiController@submitIdea');
$router->add('POST', '/api/v2/ideation-challenges/{id}/favorite', 'Nexus\Controllers\Api\IdeationChallengesApiController@toggleFavorite');
$router->add('POST', '/api/v2/ideation-challenges/{id}/duplicate', 'Nexus\Controllers\Api\IdeationChallengesApiController@duplicate');
$router->add('POST', '/api/v2/ideation-ideas/{id}/convert-to-group', 'Nexus\Controllers\Api\IdeationChallengesApiController@convertToGroup');

// ============================================
// API V2 - GOALS (Full CRUD + Progress Tracking)
// ============================================
$router->add('GET', '/api/v2/goals', 'Nexus\Controllers\Api\GoalsApiController@index');
$router->add('POST', '/api/v2/goals', 'Nexus\Controllers\Api\GoalsApiController@store');
$router->add('GET', '/api/v2/goals/discover', 'Nexus\Controllers\Api\GoalsApiController@discover');
$router->add('GET', '/api/v2/goals/mentoring', 'Nexus\Controllers\Api\GoalsApiController@mentoring');
$router->add('GET', '/api/v2/goals/templates', 'Nexus\Controllers\Api\GoalsApiController@templates');
$router->add('GET', '/api/v2/goals/templates/categories', 'Nexus\Controllers\Api\GoalsApiController@templateCategories');
$router->add('POST', '/api/v2/goals/templates', 'Nexus\Controllers\Api\GoalsApiController@createTemplate');
$router->add('POST', '/api/v2/goals/from-template/{templateId}', 'Nexus\Controllers\Api\GoalsApiController@createFromTemplate');
$router->add('GET', '/api/v2/goals/{id}', 'Nexus\Controllers\Api\GoalsApiController@show');
$router->add('PUT', '/api/v2/goals/{id}', 'Nexus\Controllers\Api\GoalsApiController@update');
$router->add('DELETE', '/api/v2/goals/{id}', 'Nexus\Controllers\Api\GoalsApiController@destroy');
$router->add('POST', '/api/v2/goals/{id}/progress', 'Nexus\Controllers\Api\GoalsApiController@progress');
$router->add('POST', '/api/v2/goals/{id}/buddy', 'Nexus\Controllers\Api\GoalsApiController@buddy');
$router->add('POST', '/api/v2/goals/{id}/complete', 'Nexus\Controllers\Api\GoalsApiController@complete');
$router->add('GET', '/api/v2/goals/{id}/checkins', 'Nexus\Controllers\Api\GoalsApiController@listCheckins');
$router->add('POST', '/api/v2/goals/{id}/checkins', 'Nexus\Controllers\Api\GoalsApiController@createCheckin');
$router->add('GET', '/api/v2/goals/{id}/history', 'Nexus\Controllers\Api\GoalsApiController@history');
$router->add('GET', '/api/v2/goals/{id}/history/summary', 'Nexus\Controllers\Api\GoalsApiController@historySummary');
$router->add('GET', '/api/v2/goals/{id}/reminder', 'Nexus\Controllers\Api\GoalsApiController@getReminder');
$router->add('PUT', '/api/v2/goals/{id}/reminder', 'Nexus\Controllers\Api\GoalsApiController@setReminder');
$router->add('DELETE', '/api/v2/goals/{id}/reminder', 'Nexus\Controllers\Api\GoalsApiController@deleteReminder');

// ============================================
// API V2 - GAMIFICATION (XP, Badges, Leaderboards)
// ============================================
$router->add('GET', '/api/v2/gamification/profile', 'Nexus\Controllers\Api\GamificationV2ApiController@profile');
$router->add('GET', '/api/v2/gamification/badges', 'Nexus\Controllers\Api\GamificationV2ApiController@badges');
$router->add('GET', '/api/v2/gamification/badges/{key}', 'Nexus\Controllers\Api\GamificationV2ApiController@showBadge');
$router->add('GET', '/api/v2/gamification/leaderboard', 'Nexus\Controllers\Api\GamificationV2ApiController@leaderboard');
$router->add('GET', '/api/v2/gamification/challenges', 'Nexus\Controllers\Api\GamificationV2ApiController@challenges');
$router->add('GET', '/api/v2/gamification/collections', 'Nexus\Controllers\Api\GamificationV2ApiController@collections');
$router->add('GET', '/api/v2/gamification/daily-reward', 'Nexus\Controllers\Api\GamificationV2ApiController@dailyRewardStatus');
$router->add('POST', '/api/v2/gamification/daily-reward', 'Nexus\Controllers\Api\GamificationV2ApiController@claimDailyReward');
$router->add('GET', '/api/v2/gamification/shop', 'Nexus\Controllers\Api\GamificationV2ApiController@shop');
$router->add('POST', '/api/v2/gamification/shop/purchase', 'Nexus\Controllers\Api\GamificationV2ApiController@purchase');
$router->add('PUT', '/api/v2/gamification/showcase', 'Nexus\Controllers\Api\GamificationV2ApiController@updateShowcase');
$router->add('GET', '/api/v2/gamification/seasons', 'Nexus\Controllers\Api\GamificationV2ApiController@seasons');
$router->add('GET', '/api/v2/gamification/seasons/current', 'Nexus\Controllers\Api\GamificationV2ApiController@currentSeason');
$router->add('POST', '/api/v2/gamification/challenges/{id}/claim', 'Nexus\Controllers\Api\GamificationV2ApiController@claimChallenge');

// ============================================
// API V2 - VOLUNTEERING (Full Module)
// ============================================
// Opportunities
$router->add('GET', '/api/v2/volunteering/opportunities', 'Nexus\Controllers\Api\VolunteerApiController@opportunities');
$router->add('POST', '/api/v2/volunteering/opportunities', 'Nexus\Controllers\Api\VolunteerApiController@createOpportunity');
$router->add('GET', '/api/v2/volunteering/opportunities/{id}', 'Nexus\Controllers\Api\VolunteerApiController@showOpportunity');
$router->add('PUT', '/api/v2/volunteering/opportunities/{id}', 'Nexus\Controllers\Api\VolunteerApiController@updateOpportunity');
$router->add('DELETE', '/api/v2/volunteering/opportunities/{id}', 'Nexus\Controllers\Api\VolunteerApiController@deleteOpportunity');
$router->add('GET', '/api/v2/volunteering/opportunities/{id}/shifts', 'Nexus\Controllers\Api\VolunteerApiController@shifts');
$router->add('GET', '/api/v2/volunteering/opportunities/{id}/applications', 'Nexus\Controllers\Api\VolunteerApiController@opportunityApplications');
$router->add('POST', '/api/v2/volunteering/opportunities/{id}/apply', 'Nexus\Controllers\Api\VolunteerApiController@apply');

// Applications
$router->add('GET', '/api/v2/volunteering/applications', 'Nexus\Controllers\Api\VolunteerApiController@myApplications');
$router->add('PUT', '/api/v2/volunteering/applications/{id}', 'Nexus\Controllers\Api\VolunteerApiController@handleApplication');
$router->add('DELETE', '/api/v2/volunteering/applications/{id}', 'Nexus\Controllers\Api\VolunteerApiController@withdrawApplication');

// Shifts
$router->add('GET', '/api/v2/volunteering/shifts', 'Nexus\Controllers\Api\VolunteerApiController@myShifts');
$router->add('POST', '/api/v2/volunteering/shifts/{id}/signup', 'Nexus\Controllers\Api\VolunteerApiController@signUp');
$router->add('DELETE', '/api/v2/volunteering/shifts/{id}/signup', 'Nexus\Controllers\Api\VolunteerApiController@cancelSignup');

// Hours
$router->add('GET', '/api/v2/volunteering/hours', 'Nexus\Controllers\Api\VolunteerApiController@myHours');
$router->add('POST', '/api/v2/volunteering/hours', 'Nexus\Controllers\Api\VolunteerApiController@logHours');
$router->add('GET', '/api/v2/volunteering/hours/summary', 'Nexus\Controllers\Api\VolunteerApiController@hoursSummary');
$router->add('PUT', '/api/v2/volunteering/hours/{id}/verify', 'Nexus\Controllers\Api\VolunteerApiController@verifyHours');

// Organisations
$router->add('GET', '/api/v2/volunteering/my-organisations', 'Nexus\Controllers\Api\VolunteerApiController@myOrganisations');
$router->add('GET', '/api/v2/volunteering/organisations', 'Nexus\Controllers\Api\VolunteerApiController@organisations');
$router->add('POST', '/api/v2/volunteering/organisations', 'Nexus\Controllers\Api\VolunteerApiController@createOrganisation');
$router->add('GET', '/api/v2/volunteering/organisations/{id}', 'Nexus\Controllers\Api\VolunteerApiController@showOrganisation');

// Volunteering Reviews (separate from main reviews)
$router->add('POST', '/api/v2/volunteering/reviews', 'Nexus\Controllers\Api\VolunteerApiController@createReview');
$router->add('GET', '/api/v2/volunteering/reviews/{type}/{id}', 'Nexus\Controllers\Api\VolunteerApiController@getReviews');

// ============================================
// API V2 - COMMENTS (Threaded comments for React frontend)
// ============================================
$router->add('GET', '/api/v2/comments', 'Nexus\Controllers\Api\CommentsV2ApiController@index');
$router->add('POST', '/api/v2/comments', 'Nexus\Controllers\Api\CommentsV2ApiController@store');
$router->add('PUT', '/api/v2/comments/{id}', 'Nexus\Controllers\Api\CommentsV2ApiController@update');
$router->add('DELETE', '/api/v2/comments/{id}', 'Nexus\Controllers\Api\CommentsV2ApiController@destroy');
$router->add('POST', '/api/v2/comments/{id}/reactions', 'Nexus\Controllers\Api\CommentsV2ApiController@reactions');

// ============================================
// API V2 - BLOG (Public, for React frontend)
// ============================================
$router->add('GET', '/api/v2/blog', 'Nexus\Controllers\Api\BlogPublicApiController@index');
$router->add('GET', '/api/v2/blog/categories', 'Nexus\Controllers\Api\BlogPublicApiController@categories');
$router->add('GET', '/api/v2/blog/{slug}', 'Nexus\Controllers\Api\BlogPublicApiController@show');

// ============================================
// API V2 - HELP / FAQ (Public, for React frontend)
// ============================================
$router->add('GET', '/api/v2/help/faqs', 'Nexus\Controllers\Api\HelpApiController@getFaqs');

// ============================================
// API V2 - PAGES (Public, for React frontend)
// ============================================
$router->add('GET', '/api/v2/pages/{slug}', 'Nexus\Controllers\Api\PagesPublicApiController@show');

// ============================================
// API V2 - RESOURCES (Public, for React frontend)
// ============================================
$router->add('GET', '/api/v2/resources', 'Nexus\Controllers\Api\ResourcesPublicApiController@index');
$router->add('GET', '/api/v2/resources/categories', 'Nexus\Controllers\Api\ResourcesPublicApiController@categories');
$router->add('GET', '/api/v2/resources/categories/tree', 'Nexus\Controllers\Api\ResourceCategoriesApiController@tree');
$router->add('POST', '/api/v2/resources/categories', 'Nexus\Controllers\Api\ResourceCategoriesApiController@store');
$router->add('PUT', '/api/v2/resources/categories/{id}', 'Nexus\Controllers\Api\ResourceCategoriesApiController@update');
$router->add('DELETE', '/api/v2/resources/categories/{id}', 'Nexus\Controllers\Api\ResourceCategoriesApiController@destroy');
$router->add('PUT', '/api/v2/resources/reorder', 'Nexus\Controllers\Api\ResourceCategoriesApiController@reorder');
$router->add('POST', '/api/v2/resources', 'Nexus\Controllers\Api\ResourcesPublicApiController@store');

// ============================================
// API V2 - KNOWLEDGE BASE
// ============================================
$router->add('GET', '/api/v2/kb', 'Nexus\Controllers\Api\KnowledgeBaseApiController@index');
$router->add('GET', '/api/v2/kb/search', 'Nexus\Controllers\Api\KnowledgeBaseApiController@search');
$router->add('POST', '/api/v2/kb', 'Nexus\Controllers\Api\KnowledgeBaseApiController@store');
$router->add('GET', '/api/v2/kb/slug/{slug}', 'Nexus\Controllers\Api\KnowledgeBaseApiController@showBySlug');
$router->add('GET', '/api/v2/kb/{id}', 'Nexus\Controllers\Api\KnowledgeBaseApiController@show');
$router->add('PUT', '/api/v2/kb/{id}', 'Nexus\Controllers\Api\KnowledgeBaseApiController@update');
$router->add('DELETE', '/api/v2/kb/{id}', 'Nexus\Controllers\Api\KnowledgeBaseApiController@destroy');
$router->add('POST', '/api/v2/kb/{id}/feedback', 'Nexus\Controllers\Api\KnowledgeBaseApiController@feedback');

