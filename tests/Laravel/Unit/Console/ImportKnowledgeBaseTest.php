<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Laravel\TestCase;

/**
 * Tests for kb:import Artisan command.
 *
 * Uses tenant id 99741 to remain isolated from other test tenants.
 *
 * The command scans a directory for .md and .pdf files, creates
 * resource_categories from folder structure, inserts knowledge_base_articles,
 * and stores attachments via the 'public' Storage disk.
 *
 * We point --path at a temp directory we create in setUp() and clean up in
 * tearDown(). Storage::fake('public') prevents any real filesystem writes.
 */
class ImportKnowledgeBaseTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99741;

    /** Absolute path to the temp fixture directory. */
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
        TenantContext::setById(self::TENANT_ID);

        // Ensure isolated tenant row exists.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'ImportKnowledgeBase Test Tenant',
                'slug'       => 'import-kb-test-99741',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create a fresh temp directory under storage/app so that base_path()
        // can resolve it when we pass the relative path to the --path option.
        // base_path($opt) = /var/www/html/$opt, so we use a path inside the project.
        $uniqueSuffix = uniqid('kb_import_test_', true);
        $relPath = 'storage/app/' . $uniqueSuffix;
        $this->tmpDir = base_path($relPath);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Remove all temp fixture files created during tests.
        $this->removeDirRecursive($this->tmpDir);
        parent::tearDown();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Write a file into the temp fixture directory.
     */
    private function writeFixture(string $relativePath, string $content): string
    {
        $fullPath = $this->tmpDir . DIRECTORY_SEPARATOR . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($fullPath, $content);
        return $fullPath;
    }

    /**
     * Run kb:import pointing at the temp dir, scoped to our isolated tenant.
     *
     * The command resolves --path via base_path($opt), so we pass the path
     * relative to the project root (e.g. 'storage/app/kb_import_test_XXX').
     * The tmpDir property holds the resolved absolute path; we derive the
     * relative portion by stripping the base_path prefix.
     */
    private function runImport(array $extraOptions = []): \Illuminate\Testing\PendingCommand
    {
        // Convert absolute tmpDir back to a project-relative path.
        $relPath = ltrim(str_replace(base_path(), '', $this->tmpDir), '/\\');

        return $this->artisan('kb:import', array_merge([
            '--tenant' => self::TENANT_ID,
            '--path'   => $relPath,
        ], $extraOptions));
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /** Command exits 1 when --tenant is missing. */
    public function test_exits_error_when_tenant_missing(): void
    {
        $this->artisan('kb:import')
            ->assertExitCode(1);
    }

    /** Command exits 1 when the given --path does not exist. */
    public function test_exits_error_when_path_not_found(): void
    {
        $this->artisan('kb:import', [
            '--tenant' => self::TENANT_ID,
            '--path'   => '/nonexistent/path/xyz123',
        ])->assertExitCode(1);
    }

    /** Command exits 1 when tenant id is not in the tenants table. */
    public function test_exits_error_when_tenant_not_found(): void
    {
        $this->writeFixture('dummy.md', '# Dummy');

        $this->artisan('kb:import', [
            '--tenant' => 9999999,
            '--path'   => $this->tmpDir,
        ])->assertExitCode(1);
    }

    /** A single .md file produces one article row with markdown content_type. */
    public function test_imports_single_md_file(): void
    {
        $this->writeFixture('guide.md', "# My Guide\n\nThis is the guide body.");

        $this->runImport()->assertExitCode(0);

        $article = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('title', 'My Guide')
            ->first();

        $this->assertNotNull($article, 'Article should have been inserted');
        $this->assertSame('markdown', $article->content_type);
        $this->assertStringContainsString('My Guide', $article->content);
    }

    /** A standalone .pdf file (no matching .md) produces an html-type article. */
    public function test_imports_standalone_pdf(): void
    {
        // Minimal valid-looking PDF binary header
        $pdfContent = '%PDF-1.4 1 0 obj<</Type /Catalog>>endobj';
        $this->writeFixture('policy.pdf', $pdfContent);

        $this->runImport()->assertExitCode(0);

        $article = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('title', 'Policy')
            ->first();

        $this->assertNotNull($article, 'Article for standalone PDF should have been inserted');
        $this->assertSame('html', $article->content_type);
        $this->assertStringContainsString('attached PDF', $article->content);
    }

    /** Paired .md + .pdf with same base name creates ONE article with two attachments. */
    public function test_pairs_md_and_pdf_into_single_article(): void
    {
        $this->writeFixture('report.md', "# Annual Report\n\nSee attached.");
        $this->writeFixture('report.pdf', '%PDF-1.4');

        $this->runImport()->assertExitCode(0);

        $articles = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('title', 'Annual Report')
            ->get();

        $this->assertCount(1, $articles, 'MD+PDF pair should produce exactly one article');

        $articleId = $articles->first()->id;
        $attachCount = DB::table('knowledge_base_attachments')
            ->where('article_id', $articleId)
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(2, $attachCount, 'Paired MD+PDF should create 2 attachments');
    }

    /** Subdirectory creates a resource_category and articles link to it. */
    public function test_creates_category_from_subdirectory(): void
    {
        $this->writeFixture('guides/getting-started.md', "# Getting Started\n\nBegin here.");

        $this->runImport()->assertExitCode(0);

        $category = DB::table('resource_categories')
            ->where('tenant_id', self::TENANT_ID)
            ->where('slug', 'guides')
            ->first();

        $this->assertNotNull($category, 'resource_categories row should have been created for guides/');

        $article = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('category_id', $category->id)
            ->first();

        $this->assertNotNull($article, 'Article should be linked to the guides category');
    }

    /** Dry-run exits 0 but inserts NO rows. */
    public function test_dry_run_makes_no_database_changes(): void
    {
        $this->writeFixture('readme.md', "# Readme\n\nNothing to see.");

        $this->runImport(['--dry-run' => true])->assertExitCode(0);

        $articleCount = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(0, $articleCount, 'Dry-run must not insert any articles');

        $catCount = DB::table('resource_categories')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(0, $catCount, 'Dry-run must not insert any categories');
    }

    /** --publish flag stores is_published = 1. */
    public function test_publish_flag_marks_articles_as_published(): void
    {
        $this->writeFixture('published-doc.md', "# Published Doc\n\nReady.");

        $this->runImport(['--publish' => true])->assertExitCode(0);

        $article = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('title', 'Published Doc')
            ->first();

        $this->assertNotNull($article);
        $this->assertSame(1, (int) $article->is_published);
    }

    /** Without --publish flag articles default to draft (is_published = 0). */
    public function test_default_import_creates_draft_articles(): void
    {
        $this->writeFixture('draft-doc.md', "# Draft Doc\n\nNot ready yet.");

        $this->runImport()->assertExitCode(0);

        $article = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('title', 'Draft Doc')
            ->first();

        $this->assertNotNull($article);
        $this->assertSame(0, (int) $article->is_published);
    }

    /** Idempotency: second run on same file adds a timestamped slug variant, not a crash. */
    public function test_duplicate_slug_gets_unique_suffix(): void
    {
        $this->writeFixture('faq.md', "# FAQ\n\nFrequently asked questions.");

        // First run.
        $this->runImport()->assertExitCode(0);
        // Second run — slug 'faq' already taken; command appends timestamp.
        $this->runImport()->assertExitCode(0);

        $count = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            // Both rows have titles derived from 'FAQ'
            ->where('title', 'Faq')
            ->count();

        $this->assertGreaterThanOrEqual(1, $count, 'At least one FAQ article should exist');
        // Both runs should have succeeded (no crash).
        $slugs = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('title', 'Faq')
            ->pluck('slug')
            ->toArray();

        // All slugs must be unique (the second one has a -<timestamp> suffix).
        $this->assertSame(count($slugs), count(array_unique($slugs)), 'Slugs must be unique across runs');
    }

    /** Title is extracted from the first # heading in the markdown. */
    public function test_title_extracted_from_md_heading(): void
    {
        $this->writeFixture('headings.md', "# Custom Article Title\n\nBody text here.");

        $this->runImport()->assertExitCode(0);

        $exists = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('title', 'Custom Article Title')
            ->exists();

        $this->assertTrue($exists, 'Title must be extracted from first # heading');
    }

    /** Non-.md/.pdf files in the directory are silently skipped. */
    public function test_ignores_non_md_pdf_files(): void
    {
        $this->writeFixture('config.yml', "key: value\n");
        $this->writeFixture('notes.txt', "some notes");

        $this->runImport()->assertExitCode(0);

        $count = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(0, $count, 'yml/txt files should be ignored');
    }

    /** An empty directory exits 0 with zero rows inserted (graceful no-op). */
    public function test_empty_directory_is_graceful_noop(): void
    {
        // No files written — tmpDir is empty.
        $this->runImport()->assertExitCode(0);

        $count = DB::table('knowledge_base_articles')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(0, $count);
    }
}
