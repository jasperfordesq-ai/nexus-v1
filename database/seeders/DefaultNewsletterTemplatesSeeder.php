<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Seeders;

use App\Models\NewsletterTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DefaultNewsletterTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::query()->pluck('name', 'id');

        foreach ($tenants as $tenantId => $tenantName) {
            $community = $tenantName ?: 'Project NEXUS';

            $name = __('emails.announcement.template_name');

            NewsletterTemplate::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                [
                    'description'  => __('emails.announcement.template_description'),
                    'category'     => 'announcement',
                    'subject'      => __('emails.announcement.subject', ['community' => $community]),
                    'preview_text' => __('emails.announcement.preview_text'),
                    'content'      => $this->buildContent($community),
                    'thumbnail'    => null,
                    'is_active'    => true,
                ]
            );
        }
    }

    private function buildContent(string $community): string
    {
        $sections = [
            'marketplace', 'donations', 'identity', 'volunteering',
            'federation', 'languages', 'seo', 'security',
        ];

        $html  = '<h1>' . e(__('emails.announcement.heading', ['community' => $community])) . '</h1>';
        $html .= '<p>' . e(__('emails.announcement.intro', ['name' => '{{name}}'])) . '</p>';

        foreach ($sections as $key) {
            $html .= '<h2>' . e(__("emails.announcement.section_{$key}_title")) . '</h2>';
            $html .= '<p>' . e(__("emails.announcement.section_{$key}_body")) . '</p>';
        }

        $html .= '<p><a href="{{app_url}}">' . e(__('emails.announcement.cta_primary')) . '</a> · ';
        $html .= '<a href="{{app_url}}/dev-status">' . e(__('emails.announcement.cta_secondary')) . '</a></p>';
        $html .= '<p>' . e(__('emails.announcement.thanks')) . '</p>';
        $html .= '<p>' . e(__('emails.announcement.signoff', ['community' => $community])) . '</p>';

        return $html;
    }
}
