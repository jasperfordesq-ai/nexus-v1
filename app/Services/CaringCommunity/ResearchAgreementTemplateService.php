<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use InvalidArgumentException;

/**
 * ResearchAgreementTemplateService — AG65 follow-up
 *
 * Provides a curated library of research-collaboration agreement templates
 * aligned with FADP/nDSG (Swiss data-protection law) and the AG42 disclosure
 * pack. Templates are static Markdown documents with token placeholders that
 * coordinators or admins can fill in when establishing a new research
 * partnership. The output is a draft for legal review — not a substitute for
 * one — and never expands placeholders the caller did not supply.
 *
 * Each template is identified by a stable string key so that
 * `ResearchPartnershipService::createPartner()` can store the chosen
 * template's key in `agreement_reference` for later auditability.
 */
class ResearchAgreementTemplateService
{
    /**
     * @var array<string,array<string,mixed>>|null
     */
    private static ?array $cache = null;

    /**
     * Catalog of available templates with display metadata only.
     *
     * @return array<int,array{key:string,title:string,summary:string,suitable_for:array<int,string>,placeholders:array<int,string>}>
     */
    public function listTemplates(): array
    {
        $catalog = [];
        foreach ($this->templates() as $key => $tpl) {
            $catalog[] = [
                'key'          => $key,
                'title'        => $tpl['title'],
                'summary'      => $tpl['summary'],
                'suitable_for' => $tpl['suitable_for'],
                'placeholders' => array_values($tpl['placeholders']),
            ];
        }
        return $catalog;
    }

    /**
     * Render a template's Markdown body with the supplied placeholders.
     *
     * Unknown placeholders in $values are ignored. Missing placeholders are
     * left as `{{name}}` so the legal reviewer can see what still needs to be
     * filled in.
     *
     * @param array<string,string> $values
     * @return array{key:string,title:string,markdown:string,placeholders_used:array<int,string>,placeholders_missing:array<int,string>}
     */
    public function render(string $key, array $values = []): array
    {
        $tpl = $this->templates()[$key] ?? null;
        if ($tpl === null) {
            throw new InvalidArgumentException('Unknown research agreement template: ' . $key);
        }

        $markdown = $tpl['body'];
        $used = [];
        $missing = [];

        foreach ($tpl['placeholders'] as $placeholder) {
            $value = $values[$placeholder] ?? '';
            $value = trim((string) $value);
            if ($value === '') {
                $missing[] = $placeholder;
                continue;
            }
            $markdown = str_replace('{{' . $placeholder . '}}', $value, $markdown);
            $used[] = $placeholder;
        }

        return [
            'key'                  => $key,
            'title'                => $tpl['title'],
            'markdown'             => $markdown,
            'placeholders_used'    => $used,
            'placeholders_missing' => $missing,
        ];
    }

    /**
     * @return array<string,array{title:string,summary:string,suitable_for:array<int,string>,placeholders:array<int,string>,body:string}>
     */
    private function templates(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [
            'aggregate_dataset_v1' => [
                'title'   => 'Anonymised Aggregate Dataset Agreement (FADP/nDSG)',
                'summary' => 'Tenant-scoped aggregate metrics only — no row-level member data, suppression threshold N≥5. Suitable for descriptive studies, monitoring, and Pro Senectute / Age-Stiftung style evaluations.',
                'suitable_for' => [
                    'descriptive cohort statistics',
                    'before/after pilot evaluations',
                    'cantonal social-policy reporting',
                ],
                'placeholders' => [
                    'partner_name', 'partner_institution', 'tenant_name',
                    'dpo_name', 'dpo_email', 'period_start', 'period_end',
                    'jurisdiction',
                ],
                'body' => <<<'MD'
# Research Collaboration Agreement — Anonymised Aggregate Dataset

**Cooperative / Tenant:** {{tenant_name}}
**Research Partner:** {{partner_name}} ({{partner_institution}})
**Reporting Period:** {{period_start}} — {{period_end}}
**Governing Law:** {{jurisdiction}} (Swiss FADP / nDSG)

## 1. Purpose
This Agreement governs the sharing of anonymised aggregate community-care
data between the Cooperative and the Research Partner for purposes of
academic, scientific, or public-policy research. No personal data within
the meaning of FADP Art. 5 lit. a will be transferred under this Agreement.

## 2. Data Scope
- Aggregate metrics produced by the platform's `caring_community_aggregate_v1`
  dataset only.
- Suppression threshold: any cell representing fewer than five (5)
  participants is suppressed and reported as null.
- No direct identifiers, no row-level member records, no free-text member
  contributions.

## 3. Lawful Basis
The Cooperative's processing rests on member opt-in consent
(`research-v1`) and on the legitimate interest of the Cooperative in
evaluating its caring-community programmes. Members may withdraw consent
at any time; withdrawal halts further data inclusion in datasets
generated after the withdrawal.

## 4. Data Use Restrictions
The Research Partner shall:
- use the data only for the stated research purpose,
- not attempt re-identification of any individual,
- not combine the data with other datasets in a manner that could
  enable re-identification,
- store the data within {{jurisdiction}} or another country offering
  adequate FADP/nDSG protection,
- destroy or return the data on conclusion of the project.

## 5. Publication
Aggregate findings may be published. Any cell with fewer than five (5)
participants must remain suppressed in publications. Pre-publication
review by the Cooperative is courtesy, not a precondition.

## 6. Data-Protection Contacts
- Cooperative DPO: {{dpo_name}} <{{dpo_email}}>
- Research Partner Contact: {{partner_name}} ({{partner_institution}})

## 7. Term and Termination
This Agreement is in force for the Reporting Period above and may be
terminated by either party with 30 days' written notice. Termination
does not relieve the Research Partner of obligations under sections 4
and 8.

## 8. Audit and Revocation
The Cooperative may revoke any dataset export under the platform's
audit log at any time, in which case the Research Partner shall destroy
the corresponding files within 14 days. Revocation does not retract
already-published aggregate findings.

## 9. Liability
This Agreement does not create a financial obligation. Neither party
shall be liable for indirect or consequential damages.

## 10. Signatures
Cooperative: ____________________  Date: __________
Research Partner: ____________________  Date: __________
MD,
            ],

            'longitudinal_cohort_v1' => [
                'title'   => 'Longitudinal Cohort Study Agreement (FADP/nDSG)',
                'summary' => 'For multi-year cohort follow-up over the same Cooperative population. Aggregate-only, period-stratified, with explicit re-consent at each annual extension.',
                'suitable_for' => [
                    'multi-year ETH / FHNW / Pro Senectute studies',
                    'longitudinal Zeitvorsorge outcomes research',
                    'reciprocity-balance trend analyses',
                ],
                'placeholders' => [
                    'partner_name', 'partner_institution', 'tenant_name',
                    'dpo_name', 'dpo_email', 'cohort_window_years',
                    'jurisdiction',
                ],
                'body' => <<<'MD'
# Research Collaboration Agreement — Longitudinal Cohort Study

**Cooperative / Tenant:** {{tenant_name}}
**Research Partner:** {{partner_name}} ({{partner_institution}})
**Cohort Window:** {{cohort_window_years}} years (rolling)
**Governing Law:** {{jurisdiction}} (Swiss FADP / nDSG)

## 1. Purpose
This Agreement governs a longitudinal study using period-stratified
aggregate metrics drawn from the Cooperative's caring-community
programmes. The Research Partner will receive cohort-level summaries
on a recurring schedule for the Cohort Window above.

## 2. Cohort Definition
A cohort is the set of consenting members active at the Cooperative
during a calendar year. Cohort-level metrics are reported per
calendar year and per matched-cohort period. Member-level records
are never shared.

## 3. Re-Consent and Withdrawal
The Cooperative shall obtain renewed `research-v1` consent at the
start of each calendar year. A member's withdrawal removes their
contribution to all cohort summaries generated after the withdrawal
date.

## 4. Data Scope and Suppression
Same as the standard aggregate dataset agreement: suppression
threshold N≥5, no direct identifiers, no free-text. Cohort matching
is performed on hashed pseudonyms internal to the Cooperative; the
hash mapping is never shared with the Research Partner.

## 5. Publication and Pre-Print
Aggregate longitudinal findings may be published or pre-printed. The
Cooperative reserves the right to be cited as the data source and
to be acknowledged in the publication.

## 6. Term
The Cohort Window above governs the data scope. Either party may
terminate with 60 days' written notice; data already shared remains
governed by sections 4 and 5 of the Anonymised Aggregate Dataset
Agreement (incorporated by reference).

## 7. Data-Protection Contacts
- Cooperative DPO: {{dpo_name}} <{{dpo_email}}>
- Research Partner Contact: {{partner_name}} ({{partner_institution}})

## 8. Signatures
Cooperative: ____________________  Date: __________
Research Partner: ____________________  Date: __________
MD,
            ],

            'pilot_evaluation_v1' => [
                'title'   => 'Pilot / Service Evaluation Agreement',
                'summary' => 'Short-form agreement for evaluating a single pilot region or programme over a fixed window. Designed for cantonal social departments, municipal sponsors, and foundation evaluators.',
                'suitable_for' => [
                    'AG83 pilot scoreboard evaluations',
                    'cantonal social-department reviews',
                    'Age-Stiftung / KISS evaluation reports',
                ],
                'placeholders' => [
                    'partner_name', 'partner_institution', 'tenant_name',
                    'dpo_name', 'dpo_email', 'pilot_region',
                    'period_start', 'period_end',
                ],
                'body' => <<<'MD'
# Pilot / Service Evaluation Agreement

**Cooperative / Tenant:** {{tenant_name}}
**Pilot Region:** {{pilot_region}}
**Evaluator:** {{partner_name}} ({{partner_institution}})
**Evaluation Window:** {{period_start}} — {{period_end}}

## 1. Purpose
This Agreement governs an evaluation of the Caring Community pilot in
the Pilot Region above. The Evaluator will receive aggregate metrics
from the AG83 pilot scoreboard and may publish an evaluation report.

## 2. Data Scope
- AG83 pilot-scoreboard ten-metric aggregate dashboard
- AG66 KPI baseline and current values
- AG76 municipal ROI methodology and CHF cost-offset estimate
- No member-level data

## 3. Methodology Disclosure
The Cooperative will disclose the CHF 35/hour rate (SECO 2024 formal
care-assistant reference), the 2× prevention multiplier, and the
90-day rolling-window definition. The Evaluator may publish caveats
on these methodological choices.

## 4. Pre-Pilot Baseline
The pre-pilot baseline captured at session-zero (AG83) is the
reference point for all delta claims. The Evaluator may not redefine
the baseline window without the Cooperative's written agreement.

## 5. Publication
The Evaluator may publish a stand-alone evaluation report or
contribute findings to a third-party report (e.g. cantonal social
department, Age-Stiftung). The Cooperative shall be cited as data
source.

## 6. Term
The Evaluation Window above. Either party may terminate with 30
days' written notice.

## 7. Data-Protection Contacts
- Cooperative DPO: {{dpo_name}} <{{dpo_email}}>
- Evaluator Contact: {{partner_name}} ({{partner_institution}})

## 8. Signatures
Cooperative: ____________________  Date: __________
Evaluator: ____________________  Date: __________
MD,
            ],

            'cross_node_federation_v1' => [
                'title'   => 'Cross-Node Federation Aggregate Study Agreement',
                'summary' => 'For studies spanning multiple Cooperatives via federation aggregates (AG20). Each participating Cooperative must independently sign and configure its `federation_aggregate_consents` opt-in.',
                'suitable_for' => [
                    'national Fondation KISS comparative studies',
                    'multi-canton policy research',
                    'cross-Verein federation impact analyses',
                ],
                'placeholders' => [
                    'partner_name', 'partner_institution', 'tenant_name',
                    'dpo_name', 'dpo_email', 'study_title',
                    'jurisdiction',
                ],
                'body' => <<<'MD'
# Cross-Node Federation Aggregate Study Agreement

**Lead Cooperative / Tenant:** {{tenant_name}}
**Research Partner:** {{partner_name}} ({{partner_institution}})
**Study Title:** {{study_title}}
**Governing Law:** {{jurisdiction}} (Swiss FADP / nDSG)

## 1. Purpose
This Agreement governs a study spanning multiple participating
Cooperatives. Data is sourced from the platform's signed
federation-aggregate JSON contract (AG20).

## 2. Per-Node Opt-In
Each participating Cooperative must:
- have a current `federation_aggregate_consents` row enabled,
- have rotated its HMAC partnership secret within the past 12 months,
- be listed in the study annex maintained by the Lead Cooperative.

This Agreement does not bind any Cooperative that has not signed it
independently or that has not opted in via its own admin panel.

## 3. Privacy Bucketing
Member counts are reported in the platform-mandated buckets
(`<50` / `50-200` / `200-1000` / `>1000`); top-categories are capped
at ten; partner organisations are reported by count only, never by
name. The Research Partner shall not publish reconstructions that
narrow these buckets.

## 4. Audit Trail
All federation-aggregate queries against participating Cooperatives
are logged for twelve months in the platform's
`federation_aggregate_query_log`. The Research Partner consents to
retention of query logs for that period.

## 5. Publication
Findings may be published as combined aggregate analyses. Per-Cooperative
identification is permitted only with the explicit written consent of
that Cooperative.

## 6. Termination
Withdrawal of any participating Cooperative's
`federation_aggregate_consents` opt-in halts further data flow from
that Cooperative. Already-shared data remains governed by section 3.

## 7. Data-Protection Contacts
- Lead Cooperative DPO: {{dpo_name}} <{{dpo_email}}>
- Research Partner Contact: {{partner_name}} ({{partner_institution}})

## 8. Signatures
Lead Cooperative: ____________________  Date: __________
Research Partner: ____________________  Date: __________
MD,
            ],
        ];

        return self::$cache;
    }
}
