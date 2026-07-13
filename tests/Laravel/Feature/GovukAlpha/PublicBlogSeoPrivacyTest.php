<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Public blog SEO coverage for the accessible frontend's identity boundary.
 */
class PublicBlogSeoPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    private const AUTHOR_NAME = 'Privacy Boundary Author Sentinel';
    private const AUTHOR_EMAIL = 'privacy-boundary-author@example.test';
    private const AUTHOR_AVATAR = 'https://cdn.example.test/privacy-boundary-author-avatar.png';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();
        unset($_SESSION['user_id']);
        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        $features = json_decode((string) DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->value('features'), true) ?: [];
        $features['blog'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_anonymous_blog_surfaces_are_public_and_omit_linked_member_identity(): void
    {
        [$author, $slug] = $this->seedPublishedPost();
        $prefix = "/{$this->testTenantSlug}/accessible/blog";

        $index = $this->get($prefix);
        $index->assertOk();
        $index->assertSee('Public SEO Privacy Post');
        $this->assertNoAuthorIdentity($index->getContent(), (int) $author->id);

        $detail = $this->get("{$prefix}/{$slug}");
        $detail->assertOk();
        $detail->assertSee('Public SEO Privacy Post');
        $detail->assertSee('Search-visible community editorial content.');
        $this->assertNoAuthorIdentity($detail->getContent(), (int) $author->id);

        $this->assertSame(1, preg_match(
            '#<script type="application/ld\+json" nonce="([a-f0-9]{32})">(.+?)</script>#s',
            $detail->getContent(),
            $schemaMatch
        ));
        $detail->assertHeaderContains('Content-Security-Policy', "'nonce-{$schemaMatch[1]}'");
        $schema = json_decode($schemaMatch[2], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Article', $schema['@type'] ?? null);
        $this->assertSame('Organization', $schema['author']['@type'] ?? null);
        $this->assertSame('Organization', $schema['publisher']['@type'] ?? null);
        $this->assertSame('Hour Timebank', $schema['publisher']['name'] ?? null);
        $this->assertStringNotContainsString('"@type":"Person"', $detail->getContent());

        $feed = $this->get("{$prefix}/feed.xml");
        $feed->assertOk();
        $feed->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $feed->assertSee('Public SEO Privacy Post', false);
        $feed->assertSee("{$prefix}/{$slug}", false);
        $this->assertNoAuthorIdentity($feed->getContent(), (int) $author->id);
        $this->assertStringNotContainsString('<author>', $feed->getContent());
    }

    public function test_anonymous_blog_comments_and_reactions_remain_behind_login(): void
    {
        [, $slug] = $this->seedPublishedPost();
        $prefix = "/{$this->testTenantSlug}/accessible/blog/{$slug}";
        $login = "/{$this->testTenantSlug}/accessible/login?status=auth-required";
        $token = 'public-blog-seo-privacy-csrf-token';
        $this->withSession(['_token' => $token]);

        $this->get("{$prefix}/comments")->assertRedirect($login);
        $this->post("{$prefix}/comments", [
            '_token' => $token,
            'body' => 'Must not be stored anonymously',
        ])
            ->assertRedirect($login);
        $this->post("{$prefix}/like", ['_token' => $token])->assertRedirect($login);

        $this->assertDatabaseMissing('comments', [
            'tenant_id' => $this->testTenantId,
            'content' => 'Must not be stored anonymously',
        ]);
    }

    /** @return array{0:User,1:string} */
    private function seedPublishedPost(): array
    {
        $author = User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Privacy Boundary',
            'last_name' => 'Author Sentinel',
            'name' => self::AUTHOR_NAME,
            'email' => self::AUTHOR_EMAIL,
            'avatar_url' => self::AUTHOR_AVATAR,
            'status' => 'active',
            'is_approved' => true,
        ]);
        $slug = 'public-seo-privacy-' . strtolower(str_replace('.', '', uniqid('', true)));

        DB::table('posts')->insert([
            'tenant_id' => $this->testTenantId,
            'author_id' => $author->id,
            'title' => 'Public SEO Privacy Post',
            'slug' => $slug,
            'excerpt' => 'Search-visible community editorial content.',
            'content' => '<p>Search-visible community editorial content.</p>',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$author, $slug];
    }

    private function assertNoAuthorIdentity(string $content, int $authorId): void
    {
        $this->assertStringNotContainsString(self::AUTHOR_NAME, $content);
        $this->assertStringNotContainsString(self::AUTHOR_EMAIL, $content);
        $this->assertStringNotContainsString(self::AUTHOR_AVATAR, $content);
        $this->assertStringNotContainsString("/members/{$authorId}", $content);
        $this->assertStringNotContainsString("/profile/{$authorId}", $content);
    }
}
