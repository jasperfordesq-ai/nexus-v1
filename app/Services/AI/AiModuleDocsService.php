<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable, per-tenant "how each module works" documentation.
 *
 * Each row describes one platform area (listings, wallet, events, …) in
 * plain language and lists trigger keywords. When the user's message
 * contains any of a doc's keywords (or its slug), that doc's body is
 * appended to the AI chat system prompt as grounding.
 *
 * This supersedes the hardcoded SUPPORT_AREAS map in AiSupportContextService
 * — once an admin writes a custom doc for a slug, that text takes precedence
 * over any built-in summary.
 */
class AiModuleDocsService
{
    private const MAX_INJECTED = 3;
    private const MAX_BODY_INJECT_CHARS = 1200;

    /**
     * Return the docs that should be injected into the prompt for this
     * message. Match is case-insensitive substring against the doc's
     * keywords list (and the module slug as a fallback keyword).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findRelevant(int $tenantId, string $message): array
    {
        $haystack = mb_strtolower($message);

        try {
            $docs = DB::table('ai_module_docs')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('module_slug')
                ->get(['id', 'module_slug', 'title', 'body', 'keywords']);
        } catch (\Throwable $e) {
            // Table may not exist yet (migration not run). Fail soft — the
            // chat still works without injected module docs.
            \Illuminate\Support\Facades\Log::info('AiModuleDocsService: table query failed (migration pending?): ' . $e->getMessage());
            return [];
        }

        $matched = [];
        foreach ($docs as $doc) {
            $keywords = $this->decodeKeywords($doc->keywords);
            if ($keywords === []) {
                $keywords = [$doc->module_slug];
            }
            foreach ($keywords as $keyword) {
                $keyword = trim(mb_strtolower((string) $keyword));
                if ($keyword === '') {
                    continue;
                }
                if (mb_stripos($haystack, $keyword) !== false) {
                    $matched[] = [
                        'slug' => (string) $doc->module_slug,
                        'title' => (string) $doc->title,
                        'body' => mb_substr((string) $doc->body, 0, self::MAX_BODY_INJECT_CHARS),
                        'matched_keyword' => $keyword,
                    ];
                    break;
                }
            }
            if (count($matched) >= self::MAX_INJECTED) {
                break;
            }
        }

        return $matched;
    }

    /**
     * Render matched docs as a Markdown section to append to the system
     * prompt. Empty string if no matches.
     */
    public function renderForPrompt(int $tenantId, string $message): string
    {
        $matched = $this->findRelevant($tenantId, $message);
        if ($matched === []) {
            return '';
        }
        $lines = ['', '## Admin Module Docs (canonical, prefer these over your training data)'];
        foreach ($matched as $doc) {
            $lines[] = '';
            $lines[] = '### ' . $doc['title'] . ' (' . $doc['slug'] . ')';
            $lines[] = $doc['body'];
        }
        return implode("\n", $lines);
    }

    /**
     * Convenience for the admin API list endpoint.
     * @return array<int, object>
     */
    public function listForTenant(int $tenantId): array
    {
        return DB::table('ai_module_docs')
            ->where('tenant_id', $tenantId)
            ->orderBy('module_slug')
            ->get(['id', 'module_slug', 'title', 'body', 'keywords', 'is_active', 'updated_at'])
            ->map(function ($row) {
                $row->keywords = $this->decodeKeywords($row->keywords);
                $row->is_active = (bool) $row->is_active;
                return $row;
            })
            ->all();
    }

    public function upsert(int $tenantId, int $userId, array $input): array
    {
        $slug = trim((string) ($input['module_slug'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        $keywords = is_array($input['keywords'] ?? null)
            ? array_values(array_filter(array_map('trim', $input['keywords'])))
            : [];
        $isActive = (bool) ($input['is_active'] ?? true);

        if ($slug === '' || $title === '' || $body === '') {
            throw new \InvalidArgumentException('module_slug, title, and body are required');
        }
        if (!preg_match('/^[a-z0-9_\-]{1,64}$/', $slug)) {
            throw new \InvalidArgumentException('module_slug must be 1-64 chars, lowercase letters, numbers, underscores or dashes');
        }

        $existing = DB::table('ai_module_docs')
            ->where('tenant_id', $tenantId)
            ->where('module_slug', $slug)
            ->first('id');

        $payload = [
            'tenant_id' => $tenantId,
            'module_slug' => $slug,
            'title' => $title,
            'body' => $body,
            'keywords' => json_encode($keywords),
            'is_active' => $isActive ? 1 : 0,
        ];

        if ($existing) {
            DB::table('ai_module_docs')->where('id', $existing->id)->update($payload);
            $id = (int) $existing->id;
        } else {
            $payload['created_by'] = $userId;
            $id = (int) DB::table('ai_module_docs')->insertGetId($payload);
        }

        return $this->getById($tenantId, $id);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return DB::table('ai_module_docs')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->delete() > 0;
    }

    public function getById(int $tenantId, int $id): array
    {
        $row = DB::table('ai_module_docs')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();
        if (!$row) {
            throw new \RuntimeException('Doc not found');
        }
        $row->keywords = $this->decodeKeywords($row->keywords);
        $row->is_active = (bool) $row->is_active;
        return (array) $row;
    }

    /**
     * Seed default docs for a tenant from the built-in SUPPORT_AREAS map.
     * Idempotent — won't overwrite an admin's custom edits.
     */
    public function seedDefaultsForTenant(int $tenantId, ?int $userId = null): int
    {
        $defaults = self::defaultSeed();
        $inserted = 0;
        foreach ($defaults as $slug => $data) {
            $exists = DB::table('ai_module_docs')
                ->where('tenant_id', $tenantId)
                ->where('module_slug', $slug)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('ai_module_docs')->insert([
                'tenant_id' => $tenantId,
                'module_slug' => $slug,
                'title' => $data['title'],
                'body' => $data['body'],
                'keywords' => json_encode($data['keywords']),
                'is_active' => 1,
                'created_by' => $userId,
            ]);
            $inserted++;
        }
        return $inserted;
    }

    /** @return array<int, string> */
    private function decodeKeywords($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    /**
     * Built-in defaults — mirrors AiSupportContextService::SUPPORT_AREAS
     * but in slightly richer prose and with more keywords. Loaded once per
     * tenant via seedDefaultsForTenant().
     *
     * @return array<string, array{title:string, body:string, keywords:array<int,string>}>
     */
    public static function defaultSeed(): array
    {
        return [
            'timebanking' => [
                'title' => 'Timebanking basics',
                'body' => "Timebanking is based on exchanging help using time credits. One hour of help earned is one time credit, regardless of the type of service. Members offer skills (offers) and request help (requests) in the Listings area. When a member helps someone, they receive time credits in their wallet which can be spent on any other listing.\n\nThere are no money payments — every exchange is one-for-one in hours. Members are encouraged to record exchanges in the wallet so the community can see activity, but the platform never holds funds.",
                'keywords' => ['timebank', 'timebanking', 'credit', 'credits', 'hour', 'hours', 'exchange', 'how it works'],
            ],
            'listings' => [
                'title' => 'Listings — offers and requests',
                'body' => "Listings are the heart of the platform. Members create two kinds: an offer (\"I can mow your lawn\") or a request (\"I need a lift to the hospital on Tuesday\"). Both are paid in time credits at one credit per hour.\n\nTo create a listing: signed-in members go to /listings and tap \"Create listing\". They pick a category, write a title and description, optionally add a location, hours estimate, and image, then publish. Listings can be edited or removed by their owner at any time. The platform may require admin moderation depending on tenant settings.",
                'keywords' => ['listing', 'listings', 'offer', 'request', 'service', 'skill', 'skills'],
            ],
            'wallet' => [
                'title' => 'Wallet and time credits',
                'body' => "The wallet shows a member's time credit balance and full transaction history. Transfers happen when one member records helping another. Either party can initiate the transfer; the other party confirms before it counts.\n\nA member with a negative balance is normal and encouraged early on — it means they have received help from the community and can pay it forward. The platform does not charge interest, fees, or expire credits.",
                'keywords' => ['wallet', 'balance', 'transfer', 'pay', 'payment', 'transaction', 'hours balance'],
            ],
            'messages' => [
                'title' => 'Messages',
                'body' => "Members can message each other privately about a listing, event, or group. The Messages area shows all conversations. If direct messaging is disabled by the community administrator, members may need to coordinate via comments on a listing or contact an admin.",
                'keywords' => ['message', 'messages', 'chat', 'conversation', 'inbox', 'contact'],
            ],
            'events' => [
                'title' => 'Events',
                'body' => "Events are community gatherings — workshops, socials, work parties — that members can RSVP to. Events appear in /events and on the calendar. Free events are common; some may be paid in time credits. Members RSVP from the event page and receive reminders. The events feature must be enabled by an admin.",
                'keywords' => ['event', 'events', 'rsvp', 'attend', 'workshop', 'gathering'],
            ],
            'groups' => [
                'title' => 'Groups',
                'body' => "Groups let members organise around a shared interest or local area. A group has its own page, members, posts, and (optionally) chatroom. Members can join open groups instantly; private groups require approval from a group admin. Groups are useful for sub-communities — \"East Galway gardeners\", \"Polish-speaking parents\", etc.",
                'keywords' => ['group', 'groups', 'join', 'community', 'club'],
            ],
            'volunteering' => [
                'title' => 'Volunteering',
                'body' => "If the volunteering feature is enabled, organisations can post shifts and members can sign up to volunteer. Hours volunteered are logged automatically. Some organisations also pay in time credits, which appear in the member's wallet after the shift is approved.",
                'keywords' => ['volunteer', 'volunteering', 'shift', 'shifts', 'opportunity'],
            ],
            'jobs' => [
                'title' => 'Job vacancies',
                'body' => "The job board lists paid roles, internships, and skilled volunteering posted by employers in the community. Members can browse, filter by remote/location/type, and apply directly. The jobs feature must be enabled by an admin.",
                'keywords' => ['job', 'jobs', 'vacancy', 'vacancies', 'employment', 'work', 'hire'],
            ],
            'marketplace' => [
                'title' => 'Marketplace',
                'body' => "The marketplace is for physical or digital items being sold or given away — distinct from Listings (which are services). Items can be priced in cash, time credits, or be free. Pickup and shipping options vary by seller.",
                'keywords' => ['marketplace', 'sell', 'buy', 'item', 'items', 'goods', 'second hand', 'free'],
            ],
            'profile' => [
                'title' => 'Profile and settings',
                'body' => "Members manage their profile, skills, photo, bio, language, security, and notification preferences under Settings. Skills listed on a member's profile help the AI assistant and other members find them when help is needed.",
                'keywords' => ['profile', 'settings', 'account', 'password', 'email', 'language', 'notification', 'skill'],
            ],
        ];
    }
}
