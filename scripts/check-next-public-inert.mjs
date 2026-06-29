#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const CANARY_TEMPLATE = 'scripts/deploy/apache/next-public-foundation-canary.conf.example';
const BLUEGREEN_DEPLOY = 'scripts/deploy/bluegreen-deploy.sh';
const COMPOSE_BLUEGREEN = 'compose.bluegreen.yml';
const CONFIG_APP = 'config/app.php';
const PRERENDER_FALLBACK = 'react-frontend/scripts/prerender.mjs';

function toPosix(relativePath) {
  return relativePath.split(path.sep).join('/');
}

function readText(root, relativePath) {
  const fullPath = path.join(root, relativePath);

  return fs.existsSync(fullPath) ? fs.readFileSync(fullPath, 'utf8') : null;
}

function isTruthy(value) {
  if (typeof value !== 'string') {
    return false;
  }

  return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
}

function serviceBlock(composeText, serviceName) {
  const lines = composeText.split(/\r?\n/);
  const start = lines.findIndex((line) => line === `  ${serviceName}:`);

  if (start === -1) {
    return '';
  }

  const block = [];

  for (const line of lines.slice(start)) {
    if (block.length > 0 && /^  [A-Za-z0-9_-]+:\s*$/.test(line)) {
      break;
    }

    block.push(line);
  }

  return block.join('\n');
}

function check(status, issue) {
  return status === 'pass'
    ? { status }
    : { status, issue };
}

/**
 * @param {{root?: string, env?: NodeJS.ProcessEnv}} options
 * @returns {{status: string, checks: Record<string, {status: string, issue?: {code: string, message: string, path?: string}}>, issues: Array<{code: string, message: string, path?: string}>}}
 */
export function checkNextPublicInert(options = {}) {
  const root = options.root ?? process.cwd();
  const env = options.env ?? process.env;
  const composeText = readText(root, COMPOSE_BLUEGREEN);
  const deployText = readText(root, BLUEGREEN_DEPLOY);
  const configText = readText(root, CONFIG_APP);
  const nextServiceBlock = composeText === null ? '' : serviceBlock(composeText, 'next_public_frontend');
  const issues = [];

  const checks = {
    cutoverEnvFlagOff: check(
      isTruthy(env.NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED) ? 'blocker' : 'pass',
      {
        code: 'cutover_env_flag_enabled',
        message: 'NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED is enabled in the current environment.',
      },
    ),
    cutoverConfigDefaultsOff: check(
      configText !== null && /env\(\s*['"]NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED['"]\s*,\s*false\s*\)/.test(configText)
        ? 'pass'
        : 'blocker',
      {
        code: 'cutover_config_default_not_false',
        message: 'config/app.php must keep NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED defaulting to false.',
        path: CONFIG_APP,
      },
    ),
    apacheCanaryTemplateExists: check(
      fs.existsSync(path.join(root, CANARY_TEMPLATE)) ? 'pass' : 'blocker',
      {
        code: 'apache_canary_template_missing',
        message: 'The inert Apache canary example template is missing.',
        path: CANARY_TEMPLATE,
      },
    ),
    apacheCanaryTemplateNotIncluded: check(
      deployText !== null
        && composeText !== null
        && !deployText.includes(CANARY_TEMPLATE)
        && !deployText.includes(path.basename(CANARY_TEMPLATE))
        && !composeText.includes(CANARY_TEMPLATE)
        && !composeText.includes(path.basename(CANARY_TEMPLATE))
        ? 'pass'
        : 'blocker',
      {
        code: 'apache_canary_template_referenced_by_deploy',
        message: 'The inert Apache canary template is referenced by deploy automation or compose.',
        path: CANARY_TEMPLATE,
      },
    ),
    shadowComposeProfile: check(
      nextServiceBlock.includes('profiles:')
        && nextServiceBlock.includes('next-public-shadow')
        ? 'pass'
        : 'blocker',
      {
        code: nextServiceBlock === ''
          ? 'next_public_compose_service_missing'
          : 'next_public_compose_profile_missing',
        message: 'The next_public_frontend service must remain behind the next-public-shadow compose profile.',
        path: COMPOSE_BLUEGREEN,
      },
    ),
    shadowComposePortIsExplicit: check(
      nextServiceBlock.includes('${NEXUS_NEXT_PUBLIC_PORT:-3200}:3000')
        ? 'pass'
        : 'blocker',
      {
        code: 'next_public_shadow_port_missing',
        message: 'The next_public_frontend service must stay bound to NEXUS_NEXT_PUBLIC_PORT in shadow mode.',
        path: COMPOSE_BLUEGREEN,
      },
    ),
    prerenderFallbackPresent: check(
      fs.existsSync(path.join(root, PRERENDER_FALLBACK)) ? 'pass' : 'blocker',
      {
        code: 'prerender_fallback_missing',
        message: 'The current prerender fallback script must remain present.',
        path: PRERENDER_FALLBACK,
      },
    ),
  };

  for (const result of Object.values(checks)) {
    if (result.status !== 'pass' && result.issue) {
      issues.push(result.issue);
    }
  }

  return {
    status: issues.length === 0 ? 'pass' : 'blocker',
    checks,
    issues,
  };
}

function printResult(result) {
  if (result.status === 'pass') {
    console.log('Next public frontend inertness OK.');
    return;
  }

  console.error('Next public frontend inertness check failed:');
  for (const issue of result.issues) {
    const location = issue.path ? ` (${toPosix(issue.path)})` : '';
    console.error(`- ${issue.code}${location}: ${issue.message}`);
  }
}

const isCli = process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1]);

if (isCli) {
  const result = checkNextPublicInert();
  printResult(result);
  process.exit(result.status === 'pass' ? 0 : 1);
}
