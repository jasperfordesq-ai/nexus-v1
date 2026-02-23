// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Development Status Page
 *
 * Public page explaining the platform's current release stage (RC),
 * what's stable, what may be rough, and how to help test.
 */

import { Card, CardBody, CardHeader, Divider, Chip } from '@heroui/react';
import { FlaskConical, CheckCircle, AlertTriangle, Bug, Shield, Users, ExternalLink } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { RELEASE_STATUS } from '@/config/releaseStatus';

export function DevelopmentStatusPage() {
  usePageTitle('Development Status — Release Candidate (RC)');

  return (
    <div className="max-w-3xl mx-auto space-y-6 py-4">
      {/* Header */}
      <div className="flex items-start gap-3">
        <FlaskConical className="w-8 h-8 text-amber-500 shrink-0 mt-1" aria-hidden="true" />
        <div>
          <h1 className="text-2xl font-bold text-foreground">
            Development status: {RELEASE_STATUS.stageLabel}
          </h1>
          <Chip
            color="warning"
            variant="flat"
            size="sm"
            className="mt-2"
          >
            {RELEASE_STATUS.stageKey.toUpperCase()}
          </Chip>
        </div>
      </div>

      {/* Where we are */}
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold">Where we are now</h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-3 text-sm text-foreground-600">
          <p>
            Project NEXUS is currently in <strong>Release Candidate (RC)</strong> — the final stage
            before a full public launch. We have reached feature-completeness for V1, and the platform
            is in active use by real communities. Our focus now is hardening, performance, and catching
            edge-case bugs before we declare General Availability.
          </p>
          <p>
            Everything you see here is running on production infrastructure (Azure, Cloudflare, MariaDB).
            Your data is real and treated seriously. We are not in a sandboxed demo environment.
          </p>
        </CardBody>
      </Card>

      {/* What's stable */}
      <Card>
        <CardHeader className="flex gap-2">
          <CheckCircle className="w-5 h-5 text-success" aria-hidden="true" />
          <h2 className="text-lg font-semibold">What's stable and working</h2>
        </CardHeader>
        <Divider />
        <CardBody>
          <ul className="text-sm text-foreground-600 space-y-2 list-none">
            {[
              'Member registration, login, and onboarding',
              'Time credit wallet — earning, spending, and viewing transaction history',
              'Listings — posting, browsing, and requesting exchanges',
              'Messaging — direct conversations between members',
              'Events — creating, discovering, and signing up for community events',
              'Groups — community sub-groups with member management',
              'Achievements and gamification (XP, badges, leaderboard)',
              'Admin panel — tenant configuration, user management, moderation',
              'Multi-tenant architecture — each community has isolated data',
              'Email notifications (registration, exchange requests, messages)',
              'Dark mode, accessibility, and mobile-responsive UI',
            ].map((item) => (
              <li key={item} className="flex items-start gap-2">
                <CheckCircle className="w-3.5 h-3.5 text-success shrink-0 mt-0.5" aria-hidden="true" />
                {item}
              </li>
            ))}
          </ul>
        </CardBody>
      </Card>

      {/* What may be rough */}
      <Card>
        <CardHeader className="flex gap-2">
          <AlertTriangle className="w-5 h-5 text-warning" aria-hidden="true" />
          <h2 className="text-lg font-semibold">What may still be rough</h2>
        </CardHeader>
        <Divider />
        <CardBody>
          <ul className="text-sm text-foreground-600 space-y-2 list-none">
            {[
              'Volunteering opportunities module — newer feature, less battle-tested',
              'Federation (cross-community) features — experimental, may have edge cases',
              'AI Chat assistant — depends on OpenAI API; prompts and context still being tuned',
              'Push notifications (mobile) — may have delivery delays on some devices',
              'Some admin reporting charts may show incorrect data in edge cases',
              'Localisation — the platform is global-ready but English-only for now',
            ].map((item) => (
              <li key={item} className="flex items-start gap-2">
                <AlertTriangle className="w-3.5 h-3.5 text-warning shrink-0 mt-0.5" aria-hidden="true" />
                {item}
              </li>
            ))}
          </ul>
        </CardBody>
      </Card>

      {/* How we catch bugs */}
      <Card>
        <CardHeader className="flex gap-2">
          <Shield className="w-5 h-5 text-primary" aria-hidden="true" />
          <h2 className="text-lg font-semibold">How we catch bugs</h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600 space-y-2">
          <p>
            We use a multi-layer approach to catch and fix issues quickly:
          </p>
          <ul className="space-y-1.5 list-none ml-2">
            <li>• <strong>Automated tests</strong> — PHP unit tests and React component tests run on every commit</li>
            <li>• <strong>Sentry error tracking</strong> — exceptions on both frontend and backend are captured in real time</li>
            <li>• <strong>Cloudflare analytics</strong> — traffic anomalies and error spikes are monitored</li>
            <li>• <strong>Manual QA</strong> — core user journeys are tested before each deployment</li>
            <li>• <strong>Community testing</strong> — real users help us discover edge cases in production</li>
          </ul>
        </CardBody>
      </Card>

      {/* How to help */}
      <Card>
        <CardHeader className="flex gap-2">
          <Users className="w-5 h-5 text-secondary" aria-hidden="true" />
          <h2 className="text-lg font-semibold">How to help</h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600 space-y-4">
          <div className="flex items-start gap-3">
            <Bug className="w-5 h-5 text-danger shrink-0 mt-0.5" aria-hidden="true" />
            <div>
              <h3 className="font-semibold text-foreground mb-1">Report a bug</h3>
              <p>
                Found something broken? Please open an issue on GitHub — it's the fastest way to get a fix
                in front of us. Include what you were doing, what you expected, and what actually happened.
              </p>
              <a
                href="https://github.com/jasperfordesq-ai/nexus-v1/issues"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1 mt-2 text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
              >
                Open an issue on GitHub
                <ExternalLink className="w-3.5 h-3.5" aria-hidden="true" />
              </a>
            </div>
          </div>

          <Divider />

          <div className="flex items-start gap-3">
            <Users className="w-5 h-5 text-secondary shrink-0 mt-0.5" aria-hidden="true" />
            <div>
              <h3 className="font-semibold text-foreground mb-1">Become a tester</h3>
              <p>
                If you run a timebank or community network and want to try Project NEXUS with your members,
                we'd love to work with you. Reach out via GitHub Discussions or the contact page.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Security */}
      <Card className="border border-danger-200 dark:border-danger-800">
        <CardHeader className="flex gap-2">
          <Shield className="w-5 h-5 text-danger" aria-hidden="true" />
          <h2 className="text-lg font-semibold">Security disclosure</h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600">
          <p>
            If you discover a security vulnerability, please do <strong>not</strong> open a public issue.
            Instead, contact us confidentially at{' '}
            {/* TODO: Replace with real security contact when security@project-nexus.ie is set up */}
            <a
              href="mailto:security@project-nexus.ie"
              className="text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
            >
              security@project-nexus.ie
            </a>
            . We will respond within 72 hours and work with you on responsible disclosure.
          </p>
        </CardBody>
      </Card>
    </div>
  );
}

export default DevelopmentStatusPage;
