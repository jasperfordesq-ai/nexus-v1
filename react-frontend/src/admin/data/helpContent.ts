// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Contextual help registry for admin routes.
 *
 * Every display value is a key in the lazily loaded admin_help namespace.
 * Keeping route structure here preserves type-safe navigation while ensuring
 * that article copy follows the active administrator locale.
 */

export interface HelpStep {
  label: string;
  detail?: string;
}

export interface HelpArticle {
  title: string;
  summary: string;
  steps?: HelpStep[];
  tips?: string[];
  caution?: string;
  relatedPaths?: Array<{ label: string; path: string }>;
}

export const HELP_CONTENT: Record<string, HelpArticle> = {
  "/caring": {
    "title": "articles.caring.title",
    "summary": "articles.caring.summary",
    "steps": [
      {
        "label": "articles.caring.steps.0.label",
        "detail": "articles.caring.steps.0.detail"
      },
      {
        "label": "articles.caring.steps.1.label",
        "detail": "articles.caring.steps.1.detail"
      },
      {
        "label": "articles.caring.steps.2.label",
        "detail": "articles.caring.steps.2.detail"
      }
    ],
    "tips": [
      "articles.caring.tips.0",
      "articles.caring.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring.related_paths.0.label",
        "path": "/caring/sla-dashboard"
      },
      {
        "label": "articles.caring.related_paths.1.label",
        "path": "/caring/safeguarding"
      },
      {
        "label": "articles.caring.related_paths.2.label",
        "path": "/caring/launch-readiness"
      }
    ]
  },
  "/caring/workflow": {
    "title": "articles.caring_workflow.title",
    "summary": "articles.caring_workflow.summary",
    "steps": [
      {
        "label": "articles.caring_workflow.steps.0.label",
        "detail": "articles.caring_workflow.steps.0.detail"
      },
      {
        "label": "articles.caring_workflow.steps.1.label",
        "detail": "articles.caring_workflow.steps.1.detail"
      },
      {
        "label": "articles.caring_workflow.steps.2.label",
        "detail": "articles.caring_workflow.steps.2.detail"
      },
      {
        "label": "articles.caring_workflow.steps.3.label",
        "detail": "articles.caring_workflow.steps.3.detail"
      }
    ],
    "tips": [
      "articles.caring_workflow.tips.0",
      "articles.caring_workflow.tips.1",
      "articles.caring_workflow.tips.2"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_workflow.related_paths.0.label",
        "path": "/caring/hour-transfers"
      },
      {
        "label": "articles.caring_workflow.related_paths.1.label",
        "path": "/caring/sla-dashboard"
      },
      {
        "label": "articles.caring_workflow.related_paths.2.label",
        "path": "/caring/kpi-baselines"
      }
    ]
  },
  "/caring/projects": {
    "title": "articles.caring_projects.title",
    "summary": "articles.caring_projects.summary",
    "steps": [
      {
        "label": "articles.caring_projects.steps.0.label",
        "detail": "articles.caring_projects.steps.0.detail"
      },
      {
        "label": "articles.caring_projects.steps.1.label",
        "detail": "articles.caring_projects.steps.1.detail"
      },
      {
        "label": "articles.caring_projects.steps.2.label",
        "detail": "articles.caring_projects.steps.2.detail"
      },
      {
        "label": "articles.caring_projects.steps.3.label",
        "detail": "articles.caring_projects.steps.3.detail"
      },
      {
        "label": "articles.caring_projects.steps.4.label",
        "detail": "articles.caring_projects.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_projects.tips.0",
      "articles.caring_projects.tips.1",
      "articles.caring_projects.tips.2"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_projects.related_paths.0.label",
        "path": "/caring/workflow"
      },
      {
        "label": "articles.caring_projects.related_paths.1.label",
        "path": "/caring/municipal-roi"
      }
    ]
  },
  "/caring/trust-tier": {
    "title": "articles.caring_trust_tier.title",
    "summary": "articles.caring_trust_tier.summary",
    "steps": [
      {
        "label": "articles.caring_trust_tier.steps.0.label",
        "detail": "articles.caring_trust_tier.steps.0.detail"
      },
      {
        "label": "articles.caring_trust_tier.steps.1.label",
        "detail": "articles.caring_trust_tier.steps.1.detail"
      },
      {
        "label": "articles.caring_trust_tier.steps.2.label",
        "detail": "articles.caring_trust_tier.steps.2.detail"
      },
      {
        "label": "articles.caring_trust_tier.steps.3.label",
        "detail": "articles.caring_trust_tier.steps.3.detail"
      },
      {
        "label": "articles.caring_trust_tier.steps.4.label",
        "detail": "articles.caring_trust_tier.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_trust_tier.tips.0",
      "articles.caring_trust_tier.tips.1",
      "articles.caring_trust_tier.tips.2"
    ],
    "caution": "articles.caring_trust_tier.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_trust_tier.related_paths.0.label",
        "path": "/caring/warmth-pass"
      },
      {
        "label": "articles.caring_trust_tier.related_paths.1.label",
        "path": "/caring/safeguarding"
      }
    ]
  },
  "/caring/warmth-pass": {
    "title": "articles.caring_warmth_pass.title",
    "summary": "articles.caring_warmth_pass.summary",
    "steps": [
      {
        "label": "articles.caring_warmth_pass.steps.0.label",
        "detail": "articles.caring_warmth_pass.steps.0.detail"
      },
      {
        "label": "articles.caring_warmth_pass.steps.1.label",
        "detail": "articles.caring_warmth_pass.steps.1.detail"
      },
      {
        "label": "articles.caring_warmth_pass.steps.2.label",
        "detail": "articles.caring_warmth_pass.steps.2.detail"
      },
      {
        "label": "articles.caring_warmth_pass.steps.3.label",
        "detail": "articles.caring_warmth_pass.steps.3.detail"
      }
    ],
    "tips": [
      "articles.caring_warmth_pass.tips.0",
      "articles.caring_warmth_pass.tips.1",
      "articles.caring_warmth_pass.tips.2"
    ],
    "caution": "articles.caring_warmth_pass.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_warmth_pass.related_paths.0.label",
        "path": "/caring/trust-tier"
      },
      {
        "label": "articles.caring_warmth_pass.related_paths.1.label",
        "path": "/partner-timebanks/caring/peers"
      }
    ]
  },
  "/caring/safeguarding": {
    "title": "articles.caring_safeguarding.title",
    "summary": "articles.caring_safeguarding.summary",
    "steps": [
      {
        "label": "articles.caring_safeguarding.steps.0.label",
        "detail": "articles.caring_safeguarding.steps.0.detail"
      },
      {
        "label": "articles.caring_safeguarding.steps.1.label",
        "detail": "articles.caring_safeguarding.steps.1.detail"
      },
      {
        "label": "articles.caring_safeguarding.steps.2.label",
        "detail": "articles.caring_safeguarding.steps.2.detail"
      },
      {
        "label": "articles.caring_safeguarding.steps.3.label",
        "detail": "articles.caring_safeguarding.steps.3.detail"
      },
      {
        "label": "articles.caring_safeguarding.steps.4.label",
        "detail": "articles.caring_safeguarding.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_safeguarding.tips.0",
      "articles.caring_safeguarding.tips.1",
      "articles.caring_safeguarding.tips.2"
    ],
    "caution": "articles.caring_safeguarding.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_safeguarding.related_paths.0.label",
        "path": "/caring/sla-dashboard"
      },
      {
        "label": "articles.caring_safeguarding.related_paths.1.label",
        "path": "/caring/data-quality"
      }
    ]
  },
  "/caring/category-coefficients": {
    "title": "articles.caring_category_coefficients.title",
    "summary": "articles.caring_category_coefficients.summary",
    "steps": [
      {
        "label": "articles.caring_category_coefficients.steps.0.label",
        "detail": "articles.caring_category_coefficients.steps.0.detail"
      },
      {
        "label": "articles.caring_category_coefficients.steps.1.label",
        "detail": "articles.caring_category_coefficients.steps.1.detail"
      },
      {
        "label": "articles.caring_category_coefficients.steps.2.label",
        "detail": "articles.caring_category_coefficients.steps.2.detail"
      },
      {
        "label": "articles.caring_category_coefficients.steps.3.label",
        "detail": "articles.caring_category_coefficients.steps.3.detail"
      }
    ],
    "tips": [
      "articles.caring_category_coefficients.tips.0",
      "articles.caring_category_coefficients.tips.1",
      "articles.caring_category_coefficients.tips.2"
    ],
    "caution": "articles.caring_category_coefficients.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_category_coefficients.related_paths.0.label",
        "path": "/caring/operating-policy"
      },
      {
        "label": "articles.caring_category_coefficients.related_paths.1.label",
        "path": "/caring/municipal-roi"
      }
    ]
  },
  "/caring/operating-policy": {
    "title": "articles.caring_operating_policy.title",
    "summary": "articles.caring_operating_policy.summary",
    "steps": [
      {
        "label": "articles.caring_operating_policy.steps.0.label",
        "detail": "articles.caring_operating_policy.steps.0.detail"
      },
      {
        "label": "articles.caring_operating_policy.steps.1.label",
        "detail": "articles.caring_operating_policy.steps.1.detail"
      },
      {
        "label": "articles.caring_operating_policy.steps.2.label",
        "detail": "articles.caring_operating_policy.steps.2.detail"
      },
      {
        "label": "articles.caring_operating_policy.steps.3.label",
        "detail": "articles.caring_operating_policy.steps.3.detail"
      },
      {
        "label": "articles.caring_operating_policy.steps.4.label",
        "detail": "articles.caring_operating_policy.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_operating_policy.tips.0",
      "articles.caring_operating_policy.tips.1"
    ],
    "caution": "articles.caring_operating_policy.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_operating_policy.related_paths.0.label",
        "path": "/caring/category-coefficients"
      },
      {
        "label": "articles.caring_operating_policy.related_paths.1.label",
        "path": "/caring/municipal-roi"
      }
    ]
  },
  "/caring/isolated-node": {
    "title": "articles.caring_isolated_node.title",
    "summary": "articles.caring_isolated_node.summary",
    "steps": [
      {
        "label": "articles.caring_isolated_node.steps.0.label",
        "detail": "articles.caring_isolated_node.steps.0.detail"
      },
      {
        "label": "articles.caring_isolated_node.steps.1.label",
        "detail": "articles.caring_isolated_node.steps.1.detail"
      },
      {
        "label": "articles.caring_isolated_node.steps.2.label",
        "detail": "articles.caring_isolated_node.steps.2.detail"
      },
      {
        "label": "articles.caring_isolated_node.steps.3.label",
        "detail": "articles.caring_isolated_node.steps.3.detail"
      }
    ],
    "tips": [
      "articles.caring_isolated_node.tips.0",
      "articles.caring_isolated_node.tips.1"
    ],
    "caution": "articles.caring_isolated_node.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_isolated_node.related_paths.0.label",
        "path": "/caring/commercial-boundary"
      },
      {
        "label": "articles.caring_isolated_node.related_paths.1.label",
        "path": "/caring/disclosure-pack"
      },
      {
        "label": "articles.caring_isolated_node.related_paths.2.label",
        "path": "/partner-timebanks/caring/peers"
      }
    ]
  },
  "/caring/commercial-boundary": {
    "title": "articles.caring_commercial_boundary.title",
    "summary": "articles.caring_commercial_boundary.summary",
    "steps": [
      {
        "label": "articles.caring_commercial_boundary.steps.0.label",
        "detail": "articles.caring_commercial_boundary.steps.0.detail"
      },
      {
        "label": "articles.caring_commercial_boundary.steps.1.label",
        "detail": "articles.caring_commercial_boundary.steps.1.detail"
      },
      {
        "label": "articles.caring_commercial_boundary.steps.2.label",
        "detail": "articles.caring_commercial_boundary.steps.2.detail"
      }
    ],
    "tips": [
      "articles.caring_commercial_boundary.tips.0",
      "articles.caring_commercial_boundary.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_commercial_boundary.related_paths.0.label",
        "path": "/caring/isolated-node"
      },
      {
        "label": "articles.caring_commercial_boundary.related_paths.1.label",
        "path": "/caring/disclosure-pack"
      }
    ]
  },
  "/caring/disclosure-pack": {
    "title": "articles.caring_disclosure_pack.title",
    "summary": "articles.caring_disclosure_pack.summary",
    "steps": [
      {
        "label": "articles.caring_disclosure_pack.steps.0.label",
        "detail": "articles.caring_disclosure_pack.steps.0.detail"
      },
      {
        "label": "articles.caring_disclosure_pack.steps.1.label",
        "detail": "articles.caring_disclosure_pack.steps.1.detail"
      },
      {
        "label": "articles.caring_disclosure_pack.steps.2.label",
        "detail": "articles.caring_disclosure_pack.steps.2.detail"
      },
      {
        "label": "articles.caring_disclosure_pack.steps.3.label",
        "detail": "articles.caring_disclosure_pack.steps.3.detail"
      },
      {
        "label": "articles.caring_disclosure_pack.steps.4.label",
        "detail": "articles.caring_disclosure_pack.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_disclosure_pack.tips.0",
      "articles.caring_disclosure_pack.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_disclosure_pack.related_paths.0.label",
        "path": "/caring/isolated-node"
      },
      {
        "label": "articles.caring_disclosure_pack.related_paths.1.label",
        "path": "/caring/commercial-boundary"
      }
    ]
  },
  "/caring/municipal-roi": {
    "title": "articles.caring_municipal_roi.title",
    "summary": "articles.caring_municipal_roi.summary",
    "steps": [
      {
        "label": "articles.caring_municipal_roi.steps.0.label",
        "detail": "articles.caring_municipal_roi.steps.0.detail"
      },
      {
        "label": "articles.caring_municipal_roi.steps.1.label",
        "detail": "articles.caring_municipal_roi.steps.1.detail"
      },
      {
        "label": "articles.caring_municipal_roi.steps.2.label",
        "detail": "articles.caring_municipal_roi.steps.2.detail"
      },
      {
        "label": "articles.caring_municipal_roi.steps.3.label",
        "detail": "articles.caring_municipal_roi.steps.3.detail"
      },
      {
        "label": "articles.caring_municipal_roi.steps.4.label",
        "detail": "articles.caring_municipal_roi.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_municipal_roi.tips.0",
      "articles.caring_municipal_roi.tips.1",
      "articles.caring_municipal_roi.tips.2"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_municipal_roi.related_paths.0.label",
        "path": "/caring/category-coefficients"
      },
      {
        "label": "articles.caring_municipal_roi.related_paths.1.label",
        "path": "/caring/operating-policy"
      },
      {
        "label": "articles.caring_municipal_roi.related_paths.2.label",
        "path": "/caring/kpi-baselines"
      },
      {
        "label": "articles.caring_municipal_roi.related_paths.3.label",
        "path": "/caring/data-quality"
      }
    ]
  },
  "/caring/municipal-impact": {
    "title": "articles.caring_municipal_impact.title",
    "summary": "articles.caring_municipal_impact.summary",
    "steps": [
      {
        "label": "articles.caring_municipal_impact.steps.0.label",
        "detail": "articles.caring_municipal_impact.steps.0.detail"
      },
      {
        "label": "articles.caring_municipal_impact.steps.1.label",
        "detail": "articles.caring_municipal_impact.steps.1.detail"
      },
      {
        "label": "articles.caring_municipal_impact.steps.2.label",
        "detail": "articles.caring_municipal_impact.steps.2.detail"
      },
      {
        "label": "articles.caring_municipal_impact.steps.3.label",
        "detail": "articles.caring_municipal_impact.steps.3.detail"
      }
    ],
    "tips": [
      "articles.caring_municipal_impact.tips.0",
      "articles.caring_municipal_impact.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_municipal_impact.related_paths.0.label",
        "path": "/caring/municipal-roi"
      },
      {
        "label": "articles.caring_municipal_impact.related_paths.1.label",
        "path": "/caring/success-stories"
      },
      {
        "label": "articles.caring_municipal_impact.related_paths.2.label",
        "path": "/caring/kpi-baselines"
      }
    ]
  },
  "/caring/kpi-baselines": {
    "title": "articles.caring_kpi_baselines.title",
    "summary": "articles.caring_kpi_baselines.summary",
    "steps": [
      {
        "label": "articles.caring_kpi_baselines.steps.0.label",
        "detail": "articles.caring_kpi_baselines.steps.0.detail"
      },
      {
        "label": "articles.caring_kpi_baselines.steps.1.label",
        "detail": "articles.caring_kpi_baselines.steps.1.detail"
      },
      {
        "label": "articles.caring_kpi_baselines.steps.2.label",
        "detail": "articles.caring_kpi_baselines.steps.2.detail"
      },
      {
        "label": "articles.caring_kpi_baselines.steps.3.label",
        "detail": "articles.caring_kpi_baselines.steps.3.detail"
      },
      {
        "label": "articles.caring_kpi_baselines.steps.4.label",
        "detail": "articles.caring_kpi_baselines.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_kpi_baselines.tips.0",
      "articles.caring_kpi_baselines.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_kpi_baselines.related_paths.0.label",
        "path": "/caring/municipal-roi"
      },
      {
        "label": "articles.caring_kpi_baselines.related_paths.1.label",
        "path": "/caring/pilot-scoreboard"
      }
    ]
  },
  "/caring/sla-dashboard": {
    "title": "articles.caring_sla_dashboard.title",
    "summary": "articles.caring_sla_dashboard.summary",
    "steps": [
      {
        "label": "articles.caring_sla_dashboard.steps.0.label",
        "detail": "articles.caring_sla_dashboard.steps.0.detail"
      },
      {
        "label": "articles.caring_sla_dashboard.steps.1.label",
        "detail": "articles.caring_sla_dashboard.steps.1.detail"
      },
      {
        "label": "articles.caring_sla_dashboard.steps.2.label",
        "detail": "articles.caring_sla_dashboard.steps.2.detail"
      },
      {
        "label": "articles.caring_sla_dashboard.steps.3.label",
        "detail": "articles.caring_sla_dashboard.steps.3.detail"
      },
      {
        "label": "articles.caring_sla_dashboard.steps.4.label",
        "detail": "articles.caring_sla_dashboard.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_sla_dashboard.tips.0",
      "articles.caring_sla_dashboard.tips.1"
    ],
    "caution": "articles.caring_sla_dashboard.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_sla_dashboard.related_paths.0.label",
        "path": "/caring/safeguarding"
      },
      {
        "label": "articles.caring_sla_dashboard.related_paths.1.label",
        "path": "/caring/nudges"
      },
      {
        "label": "articles.caring_sla_dashboard.related_paths.2.label",
        "path": "/caring/operating-policy"
      }
    ]
  },
  "/caring/data-quality": {
    "title": "articles.caring_data_quality.title",
    "summary": "articles.caring_data_quality.summary",
    "steps": [
      {
        "label": "articles.caring_data_quality.steps.0.label",
        "detail": "articles.caring_data_quality.steps.0.detail"
      },
      {
        "label": "articles.caring_data_quality.steps.1.label",
        "detail": "articles.caring_data_quality.steps.1.detail"
      },
      {
        "label": "articles.caring_data_quality.steps.2.label",
        "detail": "articles.caring_data_quality.steps.2.detail"
      },
      {
        "label": "articles.caring_data_quality.steps.3.label",
        "detail": "articles.caring_data_quality.steps.3.detail"
      },
      {
        "label": "articles.caring_data_quality.steps.4.label",
        "detail": "articles.caring_data_quality.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_data_quality.tips.0",
      "articles.caring_data_quality.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_data_quality.related_paths.0.label",
        "path": "/caring/municipal-roi"
      },
      {
        "label": "articles.caring_data_quality.related_paths.1.label",
        "path": "/caring/safeguarding"
      }
    ]
  },
  "/caring/launch-readiness": {
    "title": "articles.caring_launch_readiness.title",
    "summary": "articles.caring_launch_readiness.summary",
    "steps": [
      {
        "label": "articles.caring_launch_readiness.steps.0.label",
        "detail": "articles.caring_launch_readiness.steps.0.detail"
      },
      {
        "label": "articles.caring_launch_readiness.steps.1.label",
        "detail": "articles.caring_launch_readiness.steps.1.detail"
      },
      {
        "label": "articles.caring_launch_readiness.steps.2.label",
        "detail": "articles.caring_launch_readiness.steps.2.detail"
      },
      {
        "label": "articles.caring_launch_readiness.steps.3.label",
        "detail": "articles.caring_launch_readiness.steps.3.detail"
      },
      {
        "label": "articles.caring_launch_readiness.steps.4.label",
        "detail": "articles.caring_launch_readiness.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_launch_readiness.tips.0",
      "articles.caring_launch_readiness.tips.1",
      "articles.caring_launch_readiness.tips.2"
    ],
    "caution": "articles.caring_launch_readiness.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_launch_readiness.related_paths.0.label",
        "path": "/caring/pilot-scoreboard"
      },
      {
        "label": "articles.caring_launch_readiness.related_paths.1.label",
        "path": "/caring/kpi-baselines"
      },
      {
        "label": "articles.caring_launch_readiness.related_paths.2.label",
        "path": "/caring/operating-policy"
      }
    ]
  },
  "/caring/pilot-scoreboard": {
    "title": "articles.caring_pilot_scoreboard.title",
    "summary": "articles.caring_pilot_scoreboard.summary",
    "steps": [
      {
        "label": "articles.caring_pilot_scoreboard.steps.0.label",
        "detail": "articles.caring_pilot_scoreboard.steps.0.detail"
      },
      {
        "label": "articles.caring_pilot_scoreboard.steps.1.label",
        "detail": "articles.caring_pilot_scoreboard.steps.1.detail"
      },
      {
        "label": "articles.caring_pilot_scoreboard.steps.2.label",
        "detail": "articles.caring_pilot_scoreboard.steps.2.detail"
      },
      {
        "label": "articles.caring_pilot_scoreboard.steps.3.label",
        "detail": "articles.caring_pilot_scoreboard.steps.3.detail"
      }
    ],
    "tips": [
      "articles.caring_pilot_scoreboard.tips.0",
      "articles.caring_pilot_scoreboard.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_pilot_scoreboard.related_paths.0.label",
        "path": "/caring/kpi-baselines"
      },
      {
        "label": "articles.caring_pilot_scoreboard.related_paths.1.label",
        "path": "/caring/sla-dashboard"
      },
      {
        "label": "articles.caring_pilot_scoreboard.related_paths.2.label",
        "path": "/caring/launch-readiness"
      }
    ]
  },
  "/caring/nudges": {
    "title": "articles.caring_nudges.title",
    "summary": "articles.caring_nudges.summary",
    "steps": [
      {
        "label": "articles.caring_nudges.steps.0.label",
        "detail": "articles.caring_nudges.steps.0.detail"
      },
      {
        "label": "articles.caring_nudges.steps.1.label",
        "detail": "articles.caring_nudges.steps.1.detail"
      },
      {
        "label": "articles.caring_nudges.steps.2.label",
        "detail": "articles.caring_nudges.steps.2.detail"
      },
      {
        "label": "articles.caring_nudges.steps.3.label",
        "detail": "articles.caring_nudges.steps.3.detail"
      },
      {
        "label": "articles.caring_nudges.steps.4.label",
        "detail": "articles.caring_nudges.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_nudges.tips.0",
      "articles.caring_nudges.tips.1",
      "articles.caring_nudges.tips.2"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_nudges.related_paths.0.label",
        "path": "/caring/copilot"
      },
      {
        "label": "articles.caring_nudges.related_paths.1.label",
        "path": "/caring/sla-dashboard"
      }
    ]
  },
  "/caring/emergency-alerts": {
    "title": "articles.caring_emergency_alerts.title",
    "summary": "articles.caring_emergency_alerts.summary",
    "steps": [
      {
        "label": "articles.caring_emergency_alerts.steps.0.label",
        "detail": "articles.caring_emergency_alerts.steps.0.detail"
      },
      {
        "label": "articles.caring_emergency_alerts.steps.1.label",
        "detail": "articles.caring_emergency_alerts.steps.1.detail"
      },
      {
        "label": "articles.caring_emergency_alerts.steps.2.label",
        "detail": "articles.caring_emergency_alerts.steps.2.detail"
      },
      {
        "label": "articles.caring_emergency_alerts.steps.3.label",
        "detail": "articles.caring_emergency_alerts.steps.3.detail"
      },
      {
        "label": "articles.caring_emergency_alerts.steps.4.label",
        "detail": "articles.caring_emergency_alerts.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_emergency_alerts.tips.0",
      "articles.caring_emergency_alerts.tips.1"
    ],
    "caution": "articles.caring_emergency_alerts.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_emergency_alerts.related_paths.0.label",
        "path": "/caring/sub-regions"
      },
      {
        "label": "articles.caring_emergency_alerts.related_paths.1.label",
        "path": "/caring/copilot"
      }
    ]
  },
  "/caring/surveys": {
    "title": "articles.caring_surveys.title",
    "summary": "articles.caring_surveys.summary",
    "steps": [
      {
        "label": "articles.caring_surveys.steps.0.label",
        "detail": "articles.caring_surveys.steps.0.detail"
      },
      {
        "label": "articles.caring_surveys.steps.1.label",
        "detail": "articles.caring_surveys.steps.1.detail"
      },
      {
        "label": "articles.caring_surveys.steps.2.label",
        "detail": "articles.caring_surveys.steps.2.detail"
      },
      {
        "label": "articles.caring_surveys.steps.3.label",
        "detail": "articles.caring_surveys.steps.3.detail"
      },
      {
        "label": "articles.caring_surveys.steps.4.label",
        "detail": "articles.caring_surveys.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_surveys.tips.0",
      "articles.caring_surveys.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_surveys.related_paths.0.label",
        "path": "/caring/lead-nurture"
      },
      {
        "label": "articles.caring_surveys.related_paths.1.label",
        "path": "/caring/municipal-impact"
      }
    ]
  },
  "/caring/copilot": {
    "title": "articles.caring_copilot.title",
    "summary": "articles.caring_copilot.summary",
    "steps": [
      {
        "label": "articles.caring_copilot.steps.0.label",
        "detail": "articles.caring_copilot.steps.0.detail"
      },
      {
        "label": "articles.caring_copilot.steps.1.label",
        "detail": "articles.caring_copilot.steps.1.detail"
      },
      {
        "label": "articles.caring_copilot.steps.2.label",
        "detail": "articles.caring_copilot.steps.2.detail"
      },
      {
        "label": "articles.caring_copilot.steps.3.label",
        "detail": "articles.caring_copilot.steps.3.detail"
      },
      {
        "label": "articles.caring_copilot.steps.4.label",
        "detail": "articles.caring_copilot.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_copilot.tips.0",
      "articles.caring_copilot.tips.1"
    ],
    "caution": "articles.caring_copilot.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_copilot.related_paths.0.label",
        "path": "/caring/nudges"
      },
      {
        "label": "articles.caring_copilot.related_paths.1.label",
        "path": "/caring/emergency-alerts"
      },
      {
        "label": "articles.caring_copilot.related_paths.2.label",
        "path": "/caring/civic-digest"
      }
    ]
  },
  "/caring/civic-digest": {
    "title": "articles.caring_civic_digest.title",
    "summary": "articles.caring_civic_digest.summary",
    "steps": [
      {
        "label": "articles.caring_civic_digest.steps.0.label",
        "detail": "articles.caring_civic_digest.steps.0.detail"
      },
      {
        "label": "articles.caring_civic_digest.steps.1.label",
        "detail": "articles.caring_civic_digest.steps.1.detail"
      },
      {
        "label": "articles.caring_civic_digest.steps.2.label",
        "detail": "articles.caring_civic_digest.steps.2.detail"
      },
      {
        "label": "articles.caring_civic_digest.steps.3.label",
        "detail": "articles.caring_civic_digest.steps.3.detail"
      },
      {
        "label": "articles.caring_civic_digest.steps.4.label",
        "detail": "articles.caring_civic_digest.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_civic_digest.tips.0",
      "articles.caring_civic_digest.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_civic_digest.related_paths.0.label",
        "path": "/caring/nudges"
      },
      {
        "label": "articles.caring_civic_digest.related_paths.1.label",
        "path": "/caring/copilot"
      }
    ]
  },
  "/caring/lead-nurture": {
    "title": "articles.caring_lead_nurture.title",
    "summary": "articles.caring_lead_nurture.summary",
    "steps": [
      {
        "label": "articles.caring_lead_nurture.steps.0.label",
        "detail": "articles.caring_lead_nurture.steps.0.detail"
      },
      {
        "label": "articles.caring_lead_nurture.steps.1.label",
        "detail": "articles.caring_lead_nurture.steps.1.detail"
      },
      {
        "label": "articles.caring_lead_nurture.steps.2.label",
        "detail": "articles.caring_lead_nurture.steps.2.detail"
      },
      {
        "label": "articles.caring_lead_nurture.steps.3.label",
        "detail": "articles.caring_lead_nurture.steps.3.detail"
      },
      {
        "label": "articles.caring_lead_nurture.steps.4.label",
        "detail": "articles.caring_lead_nurture.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_lead_nurture.tips.0",
      "articles.caring_lead_nurture.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_lead_nurture.related_paths.0.label",
        "path": "/caring/surveys"
      },
      {
        "label": "articles.caring_lead_nurture.related_paths.1.label",
        "path": "/admin/pilot-inquiries"
      }
    ]
  },
  "/caring/success-stories": {
    "title": "articles.caring_success_stories.title",
    "summary": "articles.caring_success_stories.summary",
    "steps": [
      {
        "label": "articles.caring_success_stories.steps.0.label",
        "detail": "articles.caring_success_stories.steps.0.detail"
      },
      {
        "label": "articles.caring_success_stories.steps.1.label",
        "detail": "articles.caring_success_stories.steps.1.detail"
      },
      {
        "label": "articles.caring_success_stories.steps.2.label",
        "detail": "articles.caring_success_stories.steps.2.detail"
      },
      {
        "label": "articles.caring_success_stories.steps.3.label",
        "detail": "articles.caring_success_stories.steps.3.detail"
      },
      {
        "label": "articles.caring_success_stories.steps.4.label",
        "detail": "articles.caring_success_stories.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_success_stories.tips.0",
      "articles.caring_success_stories.tips.1"
    ],
    "caution": "articles.caring_success_stories.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_success_stories.related_paths.0.label",
        "path": "/caring/municipal-impact"
      },
      {
        "label": "articles.caring_success_stories.related_paths.1.label",
        "path": "/caring/disclosure-pack"
      }
    ]
  },
  "/caring/feedback": {
    "title": "articles.caring_feedback.title",
    "summary": "articles.caring_feedback.summary",
    "steps": [
      {
        "label": "articles.caring_feedback.steps.0.label",
        "detail": "articles.caring_feedback.steps.0.detail"
      },
      {
        "label": "articles.caring_feedback.steps.1.label",
        "detail": "articles.caring_feedback.steps.1.detail"
      },
      {
        "label": "articles.caring_feedback.steps.2.label",
        "detail": "articles.caring_feedback.steps.2.detail"
      },
      {
        "label": "articles.caring_feedback.steps.3.label",
        "detail": "articles.caring_feedback.steps.3.detail"
      },
      {
        "label": "articles.caring_feedback.steps.4.label",
        "detail": "articles.caring_feedback.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_feedback.tips.0",
      "articles.caring_feedback.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_feedback.related_paths.0.label",
        "path": "/caring/copilot"
      },
      {
        "label": "articles.caring_feedback.related_paths.1.label",
        "path": "/caring/sla-dashboard"
      }
    ]
  },
  "/caring/verification": {
    "title": "articles.caring_verification.title",
    "summary": "articles.caring_verification.summary",
    "steps": [
      {
        "label": "articles.caring_verification.steps.0.label",
        "detail": "articles.caring_verification.steps.0.detail"
      },
      {
        "label": "articles.caring_verification.steps.1.label",
        "detail": "articles.caring_verification.steps.1.detail"
      },
      {
        "label": "articles.caring_verification.steps.2.label",
        "detail": "articles.caring_verification.steps.2.detail"
      },
      {
        "label": "articles.caring_verification.steps.3.label",
        "detail": "articles.caring_verification.steps.3.detail"
      },
      {
        "label": "articles.caring_verification.steps.4.label",
        "detail": "articles.caring_verification.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_verification.tips.0",
      "articles.caring_verification.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_verification.related_paths.0.label",
        "path": "/caring/lead-nurture"
      },
      {
        "label": "articles.caring_verification.related_paths.1.label",
        "path": "/caring/municipal-impact"
      }
    ]
  },
  "/caring/hour-transfers": {
    "title": "articles.caring_hour_transfers.title",
    "summary": "articles.caring_hour_transfers.summary",
    "steps": [
      {
        "label": "articles.caring_hour_transfers.steps.0.label",
        "detail": "articles.caring_hour_transfers.steps.0.detail"
      },
      {
        "label": "articles.caring_hour_transfers.steps.1.label",
        "detail": "articles.caring_hour_transfers.steps.1.detail"
      },
      {
        "label": "articles.caring_hour_transfers.steps.2.label",
        "detail": "articles.caring_hour_transfers.steps.2.detail"
      },
      {
        "label": "articles.caring_hour_transfers.steps.3.label",
        "detail": "articles.caring_hour_transfers.steps.3.detail"
      },
      {
        "label": "articles.caring_hour_transfers.steps.4.label",
        "detail": "articles.caring_hour_transfers.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_hour_transfers.tips.0",
      "articles.caring_hour_transfers.tips.1"
    ],
    "caution": "articles.caring_hour_transfers.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_hour_transfers.related_paths.0.label",
        "path": "/caring/workflow"
      },
      {
        "label": "articles.caring_hour_transfers.related_paths.1.label",
        "path": "/caring/loyalty"
      }
    ]
  },
  "/caring/loyalty": {
    "title": "articles.caring_loyalty.title",
    "summary": "articles.caring_loyalty.summary",
    "steps": [
      {
        "label": "articles.caring_loyalty.steps.0.label",
        "detail": "articles.caring_loyalty.steps.0.detail"
      },
      {
        "label": "articles.caring_loyalty.steps.1.label",
        "detail": "articles.caring_loyalty.steps.1.detail"
      },
      {
        "label": "articles.caring_loyalty.steps.2.label",
        "detail": "articles.caring_loyalty.steps.2.detail"
      },
      {
        "label": "articles.caring_loyalty.steps.3.label",
        "detail": "articles.caring_loyalty.steps.3.detail"
      },
      {
        "label": "articles.caring_loyalty.steps.4.label",
        "detail": "articles.caring_loyalty.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_loyalty.tips.0",
      "articles.caring_loyalty.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_loyalty.related_paths.0.label",
        "path": "/caring/regional-points"
      },
      {
        "label": "articles.caring_loyalty.related_paths.1.label",
        "path": "/caring/pilot-scoreboard"
      }
    ]
  },
  "/caring/regional-points": {
    "title": "articles.caring_regional_points.title",
    "summary": "articles.caring_regional_points.summary",
    "steps": [
      {
        "label": "articles.caring_regional_points.steps.0.label",
        "detail": "articles.caring_regional_points.steps.0.detail"
      },
      {
        "label": "articles.caring_regional_points.steps.1.label",
        "detail": "articles.caring_regional_points.steps.1.detail"
      },
      {
        "label": "articles.caring_regional_points.steps.2.label",
        "detail": "articles.caring_regional_points.steps.2.detail"
      },
      {
        "label": "articles.caring_regional_points.steps.3.label",
        "detail": "articles.caring_regional_points.steps.3.detail"
      },
      {
        "label": "articles.caring_regional_points.steps.4.label",
        "detail": "articles.caring_regional_points.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_regional_points.tips.0",
      "articles.caring_regional_points.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_regional_points.related_paths.0.label",
        "path": "/caring/sub-regions"
      },
      {
        "label": "articles.caring_regional_points.related_paths.1.label",
        "path": "/caring/loyalty"
      }
    ]
  },
  "/caring/sub-regions": {
    "title": "articles.caring_sub_regions.title",
    "summary": "articles.caring_sub_regions.summary",
    "steps": [
      {
        "label": "articles.caring_sub_regions.steps.0.label",
        "detail": "articles.caring_sub_regions.steps.0.detail"
      },
      {
        "label": "articles.caring_sub_regions.steps.1.label",
        "detail": "articles.caring_sub_regions.steps.1.detail"
      },
      {
        "label": "articles.caring_sub_regions.steps.2.label",
        "detail": "articles.caring_sub_regions.steps.2.detail"
      },
      {
        "label": "articles.caring_sub_regions.steps.3.label",
        "detail": "articles.caring_sub_regions.steps.3.detail"
      },
      {
        "label": "articles.caring_sub_regions.steps.4.label",
        "detail": "articles.caring_sub_regions.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_sub_regions.tips.0",
      "articles.caring_sub_regions.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_sub_regions.related_paths.0.label",
        "path": "/caring/regional-points"
      },
      {
        "label": "articles.caring_sub_regions.related_paths.1.label",
        "path": "/caring/emergency-alerts"
      }
    ]
  },
  "/partner-timebanks/caring/peers": {
    "title": "articles.partner_timebanks_caring_peers.title",
    "summary": "articles.partner_timebanks_caring_peers.summary",
    "steps": [
      {
        "label": "articles.partner_timebanks_caring_peers.steps.0.label",
        "detail": "articles.partner_timebanks_caring_peers.steps.0.detail"
      },
      {
        "label": "articles.partner_timebanks_caring_peers.steps.1.label",
        "detail": "articles.partner_timebanks_caring_peers.steps.1.detail"
      },
      {
        "label": "articles.partner_timebanks_caring_peers.steps.2.label",
        "detail": "articles.partner_timebanks_caring_peers.steps.2.detail"
      },
      {
        "label": "articles.partner_timebanks_caring_peers.steps.3.label",
        "detail": "articles.partner_timebanks_caring_peers.steps.3.detail"
      },
      {
        "label": "articles.partner_timebanks_caring_peers.steps.4.label",
        "detail": "articles.partner_timebanks_caring_peers.steps.4.detail"
      }
    ],
    "tips": [
      "articles.partner_timebanks_caring_peers.tips.0",
      "articles.partner_timebanks_caring_peers.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.partner_timebanks_caring_peers.related_paths.0.label",
        "path": "/caring/warmth-pass"
      },
      {
        "label": "articles.partner_timebanks_caring_peers.related_paths.1.label",
        "path": "/caring/isolated-node"
      }
    ]
  },
  "/caring/providers": {
    "title": "articles.caring_providers.title",
    "summary": "articles.caring_providers.summary",
    "steps": [
      {
        "label": "articles.caring_providers.steps.0.label",
        "detail": "articles.caring_providers.steps.0.detail"
      },
      {
        "label": "articles.caring_providers.steps.1.label",
        "detail": "articles.caring_providers.steps.1.detail"
      },
      {
        "label": "articles.caring_providers.steps.2.label",
        "detail": "articles.caring_providers.steps.2.detail"
      },
      {
        "label": "articles.caring_providers.steps.3.label",
        "detail": "articles.caring_providers.steps.3.detail"
      },
      {
        "label": "articles.caring_providers.steps.4.label",
        "detail": "articles.caring_providers.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_providers.tips.0",
      "articles.caring_providers.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_providers.related_paths.0.label",
        "path": "/caring/recipient-circle"
      },
      {
        "label": "articles.caring_providers.related_paths.1.label",
        "path": "/caring/workflow"
      }
    ]
  },
  "/caring/recipient-circle": {
    "title": "articles.caring_recipient_circle.title",
    "summary": "articles.caring_recipient_circle.summary",
    "steps": [
      {
        "label": "articles.caring_recipient_circle.steps.0.label",
        "detail": "articles.caring_recipient_circle.steps.0.detail"
      },
      {
        "label": "articles.caring_recipient_circle.steps.1.label",
        "detail": "articles.caring_recipient_circle.steps.1.detail"
      },
      {
        "label": "articles.caring_recipient_circle.steps.2.label",
        "detail": "articles.caring_recipient_circle.steps.2.detail"
      },
      {
        "label": "articles.caring_recipient_circle.steps.3.label",
        "detail": "articles.caring_recipient_circle.steps.3.detail"
      },
      {
        "label": "articles.caring_recipient_circle.steps.4.label",
        "detail": "articles.caring_recipient_circle.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_recipient_circle.tips.0",
      "articles.caring_recipient_circle.tips.1"
    ],
    "caution": "articles.caring_recipient_circle.caution",
    "relatedPaths": [
      {
        "label": "articles.caring_recipient_circle.related_paths.0.label",
        "path": "/caring/providers"
      },
      {
        "label": "articles.caring_recipient_circle.related_paths.1.label",
        "path": "/caring/safeguarding"
      }
    ]
  },
  "/caring/research": {
    "title": "articles.caring_research.title",
    "summary": "articles.caring_research.summary",
    "steps": [
      {
        "label": "articles.caring_research.steps.0.label",
        "detail": "articles.caring_research.steps.0.detail"
      },
      {
        "label": "articles.caring_research.steps.1.label",
        "detail": "articles.caring_research.steps.1.detail"
      },
      {
        "label": "articles.caring_research.steps.2.label",
        "detail": "articles.caring_research.steps.2.detail"
      },
      {
        "label": "articles.caring_research.steps.3.label",
        "detail": "articles.caring_research.steps.3.detail"
      },
      {
        "label": "articles.caring_research.steps.4.label",
        "detail": "articles.caring_research.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_research.tips.0",
      "articles.caring_research.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_research.related_paths.0.label",
        "path": "/caring/kpi-baselines"
      },
      {
        "label": "articles.caring_research.related_paths.1.label",
        "path": "/caring/disclosure-pack"
      }
    ]
  },
  "/caring/external-integrations": {
    "title": "articles.caring_external_integrations.title",
    "summary": "articles.caring_external_integrations.summary",
    "steps": [
      {
        "label": "articles.caring_external_integrations.steps.0.label",
        "detail": "articles.caring_external_integrations.steps.0.detail"
      },
      {
        "label": "articles.caring_external_integrations.steps.1.label",
        "detail": "articles.caring_external_integrations.steps.1.detail"
      },
      {
        "label": "articles.caring_external_integrations.steps.2.label",
        "detail": "articles.caring_external_integrations.steps.2.detail"
      },
      {
        "label": "articles.caring_external_integrations.steps.3.label",
        "detail": "articles.caring_external_integrations.steps.3.detail"
      },
      {
        "label": "articles.caring_external_integrations.steps.4.label",
        "detail": "articles.caring_external_integrations.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_external_integrations.tips.0",
      "articles.caring_external_integrations.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_external_integrations.related_paths.0.label",
        "path": "/caring/integration-showcase"
      },
      {
        "label": "articles.caring_external_integrations.related_paths.1.label",
        "path": "/caring/disclosure-pack"
      }
    ]
  },
  "/caring/integration-showcase": {
    "title": "articles.caring_integration_showcase.title",
    "summary": "articles.caring_integration_showcase.summary",
    "steps": [
      {
        "label": "articles.caring_integration_showcase.steps.0.label",
        "detail": "articles.caring_integration_showcase.steps.0.detail"
      },
      {
        "label": "articles.caring_integration_showcase.steps.1.label",
        "detail": "articles.caring_integration_showcase.steps.1.detail"
      },
      {
        "label": "articles.caring_integration_showcase.steps.2.label",
        "detail": "articles.caring_integration_showcase.steps.2.detail"
      },
      {
        "label": "articles.caring_integration_showcase.steps.3.label",
        "detail": "articles.caring_integration_showcase.steps.3.detail"
      },
      {
        "label": "articles.caring_integration_showcase.steps.4.label",
        "detail": "articles.caring_integration_showcase.steps.4.detail"
      }
    ],
    "tips": [
      "articles.caring_integration_showcase.tips.0",
      "articles.caring_integration_showcase.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.caring_integration_showcase.related_paths.0.label",
        "path": "/caring/external-integrations"
      }
    ]
  },
  "/super-admin/national/kiss": {
    "title": "articles.super_admin_national_kiss.title",
    "summary": "articles.super_admin_national_kiss.summary",
    "steps": [
      {
        "label": "articles.super_admin_national_kiss.steps.0.label",
        "detail": "articles.super_admin_national_kiss.steps.0.detail"
      },
      {
        "label": "articles.super_admin_national_kiss.steps.1.label",
        "detail": "articles.super_admin_national_kiss.steps.1.detail"
      },
      {
        "label": "articles.super_admin_national_kiss.steps.2.label",
        "detail": "articles.super_admin_national_kiss.steps.2.detail"
      },
      {
        "label": "articles.super_admin_national_kiss.steps.3.label",
        "detail": "articles.super_admin_national_kiss.steps.3.detail"
      },
      {
        "label": "articles.super_admin_national_kiss.steps.4.label",
        "detail": "articles.super_admin_national_kiss.steps.4.detail"
      }
    ],
    "tips": [
      "articles.super_admin_national_kiss.tips.0",
      "articles.super_admin_national_kiss.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.super_admin_national_kiss.related_paths.0.label",
        "path": "/admin/ki-agents"
      },
      {
        "label": "articles.super_admin_national_kiss.related_paths.1.label",
        "path": "/admin/pilot-inquiries"
      }
    ]
  },
  "/admin/ki-agents": {
    "title": "articles.admin_ki_agents.title",
    "summary": "articles.admin_ki_agents.summary",
    "steps": [
      {
        "label": "articles.admin_ki_agents.steps.0.label",
        "detail": "articles.admin_ki_agents.steps.0.detail"
      },
      {
        "label": "articles.admin_ki_agents.steps.1.label",
        "detail": "articles.admin_ki_agents.steps.1.detail"
      },
      {
        "label": "articles.admin_ki_agents.steps.2.label",
        "detail": "articles.admin_ki_agents.steps.2.detail"
      },
      {
        "label": "articles.admin_ki_agents.steps.3.label",
        "detail": "articles.admin_ki_agents.steps.3.detail"
      },
      {
        "label": "articles.admin_ki_agents.steps.4.label",
        "detail": "articles.admin_ki_agents.steps.4.detail"
      }
    ],
    "tips": [
      "articles.admin_ki_agents.tips.0",
      "articles.admin_ki_agents.tips.1"
    ],
    "caution": "articles.admin_ki_agents.caution",
    "relatedPaths": [
      {
        "label": "articles.admin_ki_agents.related_paths.0.label",
        "path": "/caring/nudges"
      },
      {
        "label": "articles.admin_ki_agents.related_paths.1.label",
        "path": "/super-admin/national/kiss"
      }
    ]
  },
  "/admin/pilot-inquiries": {
    "title": "articles.admin_pilot_inquiries.title",
    "summary": "articles.admin_pilot_inquiries.summary",
    "steps": [
      {
        "label": "articles.admin_pilot_inquiries.steps.0.label",
        "detail": "articles.admin_pilot_inquiries.steps.0.detail"
      },
      {
        "label": "articles.admin_pilot_inquiries.steps.1.label",
        "detail": "articles.admin_pilot_inquiries.steps.1.detail"
      },
      {
        "label": "articles.admin_pilot_inquiries.steps.2.label",
        "detail": "articles.admin_pilot_inquiries.steps.2.detail"
      },
      {
        "label": "articles.admin_pilot_inquiries.steps.3.label",
        "detail": "articles.admin_pilot_inquiries.steps.3.detail"
      },
      {
        "label": "articles.admin_pilot_inquiries.steps.4.label",
        "detail": "articles.admin_pilot_inquiries.steps.4.detail"
      }
    ],
    "tips": [
      "articles.admin_pilot_inquiries.tips.0",
      "articles.admin_pilot_inquiries.tips.1"
    ],
    "relatedPaths": [
      {
        "label": "articles.admin_pilot_inquiries.related_paths.0.label",
        "path": "/caring/lead-nurture"
      },
      {
        "label": "articles.admin_pilot_inquiries.related_paths.1.label",
        "path": "/caring/launch-readiness"
      },
      {
        "label": "articles.admin_pilot_inquiries.related_paths.2.label",
        "path": "/super-admin/national/kiss"
      }
    ]
  }
};
