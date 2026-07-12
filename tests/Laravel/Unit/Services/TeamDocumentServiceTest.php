<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\TeamDocumentService;
use Tests\Laravel\TestCase;

final class TeamDocumentServiceTest extends TestCase
{
    private TeamDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamDocumentService();
    }

    public function test_get_documents_fails_closed_without_authenticated_user(): void
    {
        self::assertSame(
            ['items' => [], 'cursor' => null, 'has_more' => false],
            $this->service->getDocuments(10),
        );
        self::assertSame('FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_upload_rejects_missing_or_unreadable_temporary_file(): void
    {
        self::assertNull($this->service->upload(1, 1, []));
        self::assertSame('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);

        self::assertNull($this->service->upload(1, 1, [
            'name' => 'missing.pdf',
            'tmp_name' => '/definitely/not/a/real/upload.pdf',
            'error' => UPLOAD_ERR_OK,
        ]));
        self::assertSame('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_get_errors_is_empty_before_an_operation(): void
    {
        self::assertSame([], $this->service->getErrors());
    }
}
