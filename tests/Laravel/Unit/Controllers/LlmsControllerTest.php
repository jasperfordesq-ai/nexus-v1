<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Controllers;

use App\Http\Controllers\LlmsController;
use ReflectionMethod;
use Tests\Laravel\TestCase;

class LlmsControllerTest extends TestCase
{
    public function test_short_context_lists_organisations_when_volunteering_is_enabled(): void
    {
        $body = $this->renderShort([
            'events' => false,
            'groups' => false,
            'job_vacancies' => false,
            'organisations' => false,
            'volunteering' => true,
        ]);

        $this->assertStringContainsString('[Organisations](https://example.test/organisations)', $body);
    }

    public function test_short_context_omits_organisations_when_volunteering_is_disabled(): void
    {
        $body = $this->renderShort([
            'events' => false,
            'groups' => false,
            'job_vacancies' => false,
            'organisations' => true,
            'volunteering' => false,
        ]);

        $this->assertStringNotContainsString('[Organisations](https://example.test/organisations)', $body);
    }

    /**
     * @param array<string, bool> $features
     */
    private function renderShort(array $features): string
    {
        $tenant = (object) [
            'description' => '',
            'features' => json_encode($features),
            'meta_description' => '',
            'name' => 'Test Timebank',
            'tagline' => '',
        ];

        $controller = new LlmsController();
        $method = new ReflectionMethod($controller, 'renderShort');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $tenant, 'example.test');
    }
}
