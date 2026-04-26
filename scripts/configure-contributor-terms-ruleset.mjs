#!/usr/bin/env node
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const RULESET_NAME = 'Project NEXUS contributor terms gate';
const DEFAULT_BRANCH = 'main';
const DEFAULT_CHECK_CONTEXT = 'Contributor Terms Acceptance';
const API_VERSION = '2026-03-10';

function usage() {
  console.log(`Usage:
  node scripts/configure-contributor-terms-ruleset.mjs [options]

Options:
  --repo owner/repo             GitHub repository. Defaults to remote.origin.url.
  --branch name                Branch to protect. Defaults to ${DEFAULT_BRANCH}.
  --check-context name         Required status check. Defaults to "${DEFAULT_CHECK_CONTEXT}".
  --enforcement active|evaluate|disabled
                               Ruleset enforcement. Defaults to active.
  --dry-run                    Print the ruleset payload without calling GitHub.
  --help                       Show this help.

Before running against GitHub:
  gh auth login -h github.com
  git push origin main
`);
}

function parseArgs(argv) {
  const options = {
    repo: null,
    branch: DEFAULT_BRANCH,
    checkContext: DEFAULT_CHECK_CONTEXT,
    enforcement: 'active',
    dryRun: false,
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];

    if (arg === '--help' || arg === '-h') {
      usage();
      process.exit(0);
    }

    if (arg === '--dry-run') {
      options.dryRun = true;
      continue;
    }

    const nextValue = () => {
      const value = argv[i + 1];
      if (!value || value.startsWith('--')) {
        throw new Error(`Missing value for ${arg}`);
      }
      i += 1;
      return value;
    };

    if (arg === '--repo') {
      options.repo = nextValue();
    } else if (arg === '--branch') {
      options.branch = nextValue();
    } else if (arg === '--check-context') {
      options.checkContext = nextValue();
    } else if (arg === '--enforcement') {
      options.enforcement = nextValue();
    } else {
      throw new Error(`Unknown option: ${arg}`);
    }
  }

  if (!['active', 'evaluate', 'disabled'].includes(options.enforcement)) {
    throw new Error('--enforcement must be one of: active, evaluate, disabled');
  }

  return options;
}

function run(command, args, options = {}) {
  const output = execFileSync(command, args, {
    encoding: 'utf8',
    stdio: options.stdio ?? ['ignore', 'pipe', 'pipe'],
  });

  return typeof output === 'string' ? output.trim() : '';
}

function resolveRepo(explicitRepo) {
  if (explicitRepo) {
    return explicitRepo;
  }

  const remote = run('git', ['config', '--get', 'remote.origin.url']);
  const match =
    remote.match(/^https:\/\/github\.com\/([^/]+)\/([^/.]+)(?:\.git)?$/) ||
    remote.match(/^git@github\.com:([^/]+)\/([^/.]+)(?:\.git)?$/);

  if (!match) {
    throw new Error(`Could not parse GitHub owner/repo from remote.origin.url: ${remote}`);
  }

  return `${match[1]}/${match[2]}`;
}

function rulesetPayload({ branch, checkContext, enforcement }) {
  return {
    name: RULESET_NAME,
    target: 'branch',
    enforcement,
    conditions: {
      ref_name: {
        include: [`refs/heads/${branch}`],
        exclude: [],
      },
    },
    rules: [
      {
        type: 'pull_request',
        parameters: {
          allowed_merge_methods: ['merge', 'squash', 'rebase'],
          dismiss_stale_reviews_on_push: true,
          require_code_owner_review: true,
          require_last_push_approval: true,
          required_approving_review_count: 1,
          required_review_thread_resolution: true,
        },
      },
      {
        type: 'required_status_checks',
        parameters: {
          required_status_checks: [
            {
              context: checkContext,
            },
          ],
          strict_required_status_checks_policy: true,
        },
      },
      {
        type: 'deletion',
      },
      {
        type: 'non_fast_forward',
      },
    ],
  };
}

function ghApi(args, options = {}) {
  return run(
    'gh',
    [
      'api',
      '-H',
      'Accept: application/vnd.github+json',
      '-H',
      `X-GitHub-Api-Version: ${API_VERSION}`,
      ...args,
    ],
    options,
  );
}

function findExistingRuleset(owner, repo) {
  const response = ghApi([`repos/${owner}/${repo}/rulesets?per_page=100`]);
  const rulesets = JSON.parse(response || '[]');
  return rulesets.find((ruleset) => ruleset.name === RULESET_NAME) ?? null;
}

function applyRuleset(owner, repo, payload) {
  const dir = mkdtempSync(join(tmpdir(), 'nexus-ruleset-'));
  const inputPath = join(dir, 'ruleset.json');

  try {
    writeFileSync(inputPath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
    const existing = findExistingRuleset(owner, repo);

    if (existing) {
      console.log(`Updating existing ruleset ${existing.id} (${RULESET_NAME})...`);
      ghApi(['--method', 'PUT', `repos/${owner}/${repo}/rulesets/${existing.id}`, '--input', inputPath], {
        stdio: 'inherit',
      });
      return;
    }

    console.log(`Creating ruleset (${RULESET_NAME})...`);
    ghApi(['--method', 'POST', `repos/${owner}/${repo}/rulesets`, '--input', inputPath], {
      stdio: 'inherit',
    });
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

try {
  const options = parseArgs(process.argv.slice(2));
  const repository = resolveRepo(options.repo);
  const [owner, repo] = repository.split('/');
  const payload = rulesetPayload(options);

  if (!owner || !repo) {
    throw new Error(`Invalid repository: ${repository}`);
  }

  if (options.dryRun) {
    console.log(JSON.stringify({ repository, ...payload }, null, 2));
    process.exit(0);
  }

  run('gh', ['auth', 'status'], { stdio: 'inherit' });
  applyRuleset(owner, repo, payload);
  console.log(`Contributor terms ruleset is configured for ${repository}:${options.branch}.`);
} catch (error) {
  console.error(`Error: ${error.message}`);
  process.exit(1);
}
