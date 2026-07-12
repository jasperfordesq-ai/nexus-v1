// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  expect,
  request as playwrightRequest,
  test,
  type APIRequestContext,
  type APIResponse,
  type Page,
  type Response,
  type TestInfo,
} from '@playwright/test';
import { CreateGroupPage, GroupDetailPage, GroupsPage } from '../../page-objects/GroupsPage';

const TENANT = process.env.E2E_TENANT || 'hour-timebank';
const FRONTEND_BASE_URL = (process.env.E2E_BASE_URL || 'http://localhost:5173').replace(/\/+$/, '');
const API_BASE_URL = (process.env.E2E_API_URL || 'http://localhost:8090').replace(/\/+$/, '');
const GROUP_DESCRIPTION = 'Run-scoped Groups E2E fixture. This record must be removed by verified teardown.';
const OWNER_SECTION_KEYS = new Map<string, string>([
  ['Feed', 'feed'],
  ['Discussion', 'discussion'],
  ['Members', 'members'],
  ['Events', 'events'],
  ['Files', 'files'],
  ['Announcements', 'announcements'],
  ['Q&A', 'qa'],
  ['Wiki', 'wiki'],
  ['Gallery', 'media'],
  ['Channels', 'chatrooms'],
  ['Tasks', 'tasks'],
  ['Challenges', 'challenges'],
  ['Analytics', 'analytics'],
  ['Automation', 'automation'],
]);

interface ActorSession {
  token: string;
  refreshToken: string;
  name: string;
  tenantId: number;
}

interface SeededGroup {
  id: number;
  name: string;
  description: string;
  visibility: 'public' | 'private';
  ownerToken: string;
}

interface GroupsHarness {
  member: ActorSession;
  admin: ActorSession;
  ownedGroup: SeededGroup;
  childGroup: SeededGroup;
  joinableGroup: SeededGroup;
  privatePinnedCanary: string;
}

interface JsonEnvelope<T> {
  success?: boolean;
  data?: T;
  error?: string;
  errors?: unknown;
}

let apiContext: APIRequestContext | undefined;
let harness: GroupsHarness | undefined;
const transientGroupNames = new Set<string>();

test.describe.configure({ mode: 'serial', timeout: 90_000 });

function apiUrl(pathname: string): string {
  return `${API_BASE_URL}/api${pathname.startsWith('/') ? pathname : `/${pathname}`}`;
}

function actorHeaders(token?: string): Record<string, string> {
  return {
    'Content-Type': 'application/json',
    'X-Tenant-Slug': TENANT,
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

async function bridgeDockerAssetHost(page: Page): Promise<void> {
  const api = new URL(API_BASE_URL);
  if (!['localhost', '127.0.0.1', '::1'].includes(api.hostname)) return;
  const frontend = new URL(FRONTEND_BASE_URL);

  await page.route(
    (url) => url.origin === frontend.origin && url.pathname.startsWith('/api/'),
    async (route) => {
      const target = new URL(route.request().url());
      target.protocol = api.protocol;
      target.host = api.host;
      await route.continue({ url: target.toString() });
    },
  );

  await page.route(/^http:\/\/host\.docker\.internal(?::\d+)?\//, async (route) => {
    const target = new URL(route.request().url());
    target.protocol = api.protocol;
    target.host = api.host;
    await route.continue({ url: target.toString() });
  });
}

function requiredCredential(name: 'E2E_USER_EMAIL' | 'E2E_USER_PASSWORD' | 'E2E_ADMIN_EMAIL' | 'E2E_ADMIN_PASSWORD'): string {
  const value = process.env[name]?.trim();
  if (!value) {
    throw new Error(`${name} is required for the deterministic Groups E2E fixture.`);
  }
  return value;
}

function assertSafeFixtureTarget(): void {
  for (const [label, rawUrl] of [['frontend', FRONTEND_BASE_URL], ['API', API_BASE_URL]] as const) {
    const hostname = new URL(rawUrl).hostname.toLowerCase();
    if (hostname === 'project-nexus.ie' || hostname.endsWith('.project-nexus.ie')) {
      throw new Error(`Groups E2E refuses to create fixtures on the production ${label} host: ${hostname}`);
    }

    const isLoopback = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    if (!isLoopback && process.env.E2E_GROUPS_ALLOW_REMOTE_FIXTURES !== '1') {
      throw new Error(
        `Groups E2E fixture target ${hostname} is not local. ` +
        'Set E2E_GROUPS_ALLOW_REMOTE_FIXTURES=1 only for an isolated non-production test environment.',
      );
    }
  }
}

async function responseBody(response: APIResponse | Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return await response.text();
  }
}

async function loginActor(email: string, password: string): Promise<ActorSession> {
  if (!apiContext) throw new Error('Groups E2E API context is not initialized.');

  const response = await apiContext.post(apiUrl('/auth/login'), {
    data: { email, password, tenant_slug: TENANT },
    headers: actorHeaders(),
  });
  const body = await responseBody(response) as JsonEnvelope<{
    access_token?: string;
    refresh_token?: string;
    tenant_id?: number;
    user?: { first_name?: string; last_name?: string; name?: string; email?: string; tenant_id?: number; tenant?: { id?: number } };
  }> & {
    access_token?: string;
    refresh_token?: string;
    tenant_id?: number;
    user?: { first_name?: string; last_name?: string; name?: string; email?: string; tenant_id?: number; tenant?: { id?: number } };
  };

  if (!response.ok()) {
    throw new Error(`Groups E2E login failed for ${email} (${response.status()}): ${JSON.stringify(body)}`);
  }

  const token = body.data?.access_token ?? body.access_token;
  const refreshToken = body.data?.refresh_token ?? body.refresh_token;
  const user = body.data?.user ?? body.user;
  const tenantId = Number(body.data?.tenant_id ?? body.tenant_id ?? user?.tenant_id ?? user?.tenant?.id);
  const name = user?.name || `${user?.first_name ?? ''} ${user?.last_name ?? ''}`.trim() || user?.email || '';
  if (!token || !refreshToken || !Number.isSafeInteger(tenantId) || !name) {
    throw new Error(
      `Groups E2E login returned an incomplete session for ${email} ` +
      `(token=${Boolean(token)}, refreshToken=${Boolean(refreshToken)}, ` +
      `tenantId=${Number.isSafeInteger(tenantId)}, name=${Boolean(name)}).`
    );
  }

  return { token, refreshToken, tenantId, name };
}

async function createFixtureGroup(
  name: string,
  ownerToken: string,
  options: { description?: string; visibility?: 'public' | 'private'; parentId?: number } = {},
): Promise<SeededGroup> {
  if (!apiContext) throw new Error('Groups E2E API context is not initialized.');

  const description = options.description ?? GROUP_DESCRIPTION;
  const visibility = options.visibility ?? 'public';

  const response = await apiContext.post(apiUrl('/v2/groups'), {
    data: {
      name,
      description,
      visibility,
      ...(options.parentId ? { parent_id: options.parentId } : {}),
    },
    headers: actorHeaders(ownerToken),
  });
  const body = await responseBody(response) as JsonEnvelope<{ id?: number }>;
  const id = Number(body.data?.id);
  if (response.status() !== 201 || !Number.isSafeInteger(id) || id <= 0) {
    throw new Error(`Groups E2E fixture creation failed (${response.status()}): ${JSON.stringify(body)}`);
  }

  return { id, name, description, visibility, ownerToken };
}

async function createPinnedAnnouncement(group: SeededGroup, canary: string): Promise<void> {
  if (!apiContext) throw new Error('Groups E2E API context is not initialized.');

  const response = await apiContext.post(apiUrl(`/v2/groups/${group.id}/announcements`), {
    data: {
      title: canary,
      content: `${canary} content must never survive a group transition.`,
      is_pinned: true,
    },
    headers: actorHeaders(group.ownerToken),
  });
  if (response.status() !== 201) {
    throw new Error(
      `Groups E2E pinned-canary creation failed (${response.status()}): ` +
      JSON.stringify(await responseBody(response)),
    );
  }
}

async function deleteFixtureGroup(
  group: SeededGroup,
  context: APIRequestContext | undefined = apiContext,
): Promise<void> {
  if (!context) throw new Error('Groups E2E API context is not initialized.');

  const response = await context.delete(apiUrl(`/v2/groups/${group.id}`), {
    headers: actorHeaders(group.ownerToken),
  });
  if (![204, 404].includes(response.status())) {
    throw new Error(
      `Groups E2E cleanup failed for ${group.name} (${response.status()}): ` +
      JSON.stringify(await responseBody(response)),
    );
  }

  const verification = await context.get(apiUrl(`/v2/groups/${group.id}`), {
    headers: actorHeaders(group.ownerToken),
  });
  if (verification.status() !== 404) {
    throw new Error(`Groups E2E cleanup verification failed for ${group.name}; GET returned ${verification.status()}.`);
  }
}

async function findFixtureGroup(
  name: string,
  ownerToken: string,
  context: APIRequestContext | undefined = apiContext,
): Promise<SeededGroup | undefined> {
  if (!context) throw new Error('Groups E2E API context is not initialized.');

  const response = await context.get(apiUrl(`/v2/groups?q=${encodeURIComponent(name)}&per_page=100`), {
    headers: actorHeaders(ownerToken),
  });
  const body = await responseBody(response) as JsonEnvelope<Array<{
    id?: number;
    name?: string;
    description?: string;
  }>>;
  if (!response.ok()) {
    throw new Error(`Could not locate Groups E2E cleanup target ${name} (${response.status()}).`);
  }

  const exact = body.data?.find((group) => group.name === name);
  const id = Number(exact?.id);
  return Number.isSafeInteger(id) && id > 0
    ? { id, name, description: exact?.description ?? GROUP_DESCRIPTION, visibility: 'public', ownerToken }
    : undefined;
}

async function cleanupGroups(
  groups: SeededGroup[],
  context: APIRequestContext | undefined = apiContext,
): Promise<void> {
  const failures: string[] = [];
  for (const group of [...groups].reverse()) {
    try {
      await deleteFixtureGroup(group, context);
    } catch (error) {
      failures.push(error instanceof Error ? error.message : String(error));
    }
  }
  if (failures.length > 0) throw new Error(failures.join('\n'));
}

async function setJoinableMembership(shouldBeMember: boolean): Promise<void> {
  if (!apiContext || !harness) throw new Error('Groups E2E harness is not initialized.');
  const group = harness.joinableGroup;
  const token = harness.member.token;

  const mutation = shouldBeMember
    ? await apiContext.post(apiUrl(`/v2/groups/${group.id}/join`), { headers: actorHeaders(token), data: {} })
    : await apiContext.delete(apiUrl(`/v2/groups/${group.id}/membership`), { headers: actorHeaders(token) });
  const acceptedStatuses = shouldBeMember ? [200, 409] : [200, 204, 409];
  if (!acceptedStatuses.includes(mutation.status())) {
    throw new Error(
      `Could not normalize ${group.name} membership to ${shouldBeMember ? 'active' : 'none'} ` +
      `(${mutation.status()}): ${JSON.stringify(await responseBody(mutation))}`,
    );
  }

  const verification = await apiContext.get(apiUrl(`/v2/groups/${group.id}`), {
    headers: actorHeaders(token),
  });
  const body = await responseBody(verification) as JsonEnvelope<{
    viewer_membership?: { status?: string } | null;
  }>;
  const isMember = body.data?.viewer_membership?.status === 'active';
  if (!verification.ok() || isMember !== shouldBeMember) {
    throw new Error(
      `Membership normalization verification failed for ${group.name}: ${JSON.stringify(body)}`,
    );
  }
}

function getHarness(): GroupsHarness {
  if (!harness) throw new Error('Groups E2E harness is not initialized.');
  return harness;
}

async function openGroups(page: Page): Promise<GroupsPage> {
  const diagnostics: string[] = [];
  const onPageError = (error: Error) => diagnostics.push(`pageerror: ${error.message}`);
  const onConsole = (message: import('@playwright/test').ConsoleMessage) => {
    if (message.type() === 'error') diagnostics.push(`console: ${message.text()}`);
  };
  const onResponse = (response: Response) => {
    const url = new URL(response.url());
    if (
      (url.pathname.includes('/api/v2/groups') || url.pathname.endsWith('/api/v2/users/me'))
      && response.status() >= 400
    ) {
      diagnostics.push(`${response.request().method()} ${url.pathname}${url.search} -> ${response.status()}`);
    }
  };
  const onRequestFailed = (request: import('@playwright/test').Request) => {
    const reason = request.failure()?.errorText ?? 'unknown failure';
    if (
      reason !== 'NS_BINDING_ABORTED'
      && reason !== 'net::ERR_ABORTED'
      && reason !== 'Load request cancelled'
    ) {
      diagnostics.push(`requestfailed: ${request.method()} ${request.url()} -> ${reason}`);
    }
  };
  page.on('pageerror', onPageError);
  page.on('console', onConsole);
  page.on('response', onResponse);
  page.on('requestfailed', onRequestFailed);
  const groupsPage = new GroupsPage(page);
  try {
    await groupsPage.navigate();
    await groupsPage.waitForLoad();
    return groupsPage;
  } catch (error) {
    const runtime = await page.evaluate(() => ({
      href: window.location.href,
      hasAccessToken: Boolean(localStorage.getItem('nexus_access_token')),
      text: document.body.innerText.slice(0, 500),
    })).catch(() => ({ href: page.url(), hasAccessToken: false, text: '(page runtime unavailable)' }));
    throw new Error(
      `Failed to open the Groups collection. Runtime: ${JSON.stringify(runtime)}\n` +
      `Browser evidence:\n${diagnostics.join('\n') || '(none captured)'}`,
      { cause: error },
    );
  } finally {
    page.off('pageerror', onPageError);
    page.off('console', onConsole);
    page.off('response', onResponse);
    page.off('requestfailed', onRequestFailed);
  }
}

async function openGroup(page: Page, group: SeededGroup): Promise<GroupDetailPage> {
  const diagnostics: string[] = [];
  const onPageError = (error: Error) => diagnostics.push(`pageerror: ${error.message}`);
  const onConsole = (message: import('@playwright/test').ConsoleMessage) => {
    if (message.type() === 'error') diagnostics.push(`console: ${message.text()}`);
  };
  const onResponse = (response: Response) => {
    const url = new URL(response.url());
    if (url.pathname.includes('/api/v2/groups') && response.status() >= 400) {
      diagnostics.push(`${response.request().method()} ${url.pathname}${url.search} -> ${response.status()}`);
    }
  };
  page.on('pageerror', onPageError);
  page.on('console', onConsole);
  page.on('response', onResponse);
  const detailPage = new GroupDetailPage(page);
  try {
    await detailPage.navigateToGroup(group.id);
    await detailPage.waitForLoad();
    await expect(detailPage.groupName).toHaveText(group.name);
    return detailPage;
  } catch (error) {
    throw new Error(
      `Failed to open ${group.name}. Browser evidence:\n${diagnostics.join('\n') || '(none captured)'}`,
      { cause: error },
    );
  } finally {
    page.off('pageerror', onPageError);
    page.off('console', onConsole);
    page.off('response', onResponse);
  }
}

function isGroupsCollectionResponse(response: Response, method: string): boolean {
  const url = new URL(response.url());
  return response.request().method() === method && url.pathname.endsWith('/api/v2/groups');
}

function sectionKeyForLabel(label: string): string {
  const normalized = label.trim();
  const direct = OWNER_SECTION_KEYS.get(normalized);
  if (direct) return direct;
  if (/^Subgroups? \(\d+\)$/.test(normalized)) return 'subgroups';
  throw new Error(`Groups E2E found an unmapped available section: ${JSON.stringify(normalized)}.`);
}

function collectGroupsBrowserFailures(page: Page, groupId: number): string[] {
  const failures: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) {
      failures.push(`console: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => {
    if (
      error.message === 'ResizeObserver loop completed with undelivered notifications.'
      || error.message === 'ResizeObserver loop limit exceeded'
    ) return;
    failures.push(`pageerror: ${error.message}`);
  });
  page.on('requestfailed', (request) => {
    const reason = request.failure()?.errorText ?? 'unknown failure';
    if (
      reason === 'net::ERR_ABORTED'
      || reason === 'NS_BINDING_ABORTED'
      || reason === 'Load request cancelled'
    ) return;
    failures.push(`requestfailed: ${request.method()} ${request.url()} -> ${reason}`);
  });
  page.on('response', (response) => {
    const url = new URL(response.url());
    const isGroupsApi = url.pathname.includes('/api/v2/groups');
    const isGroupEventsApi = url.pathname.endsWith('/api/v2/events')
      && url.searchParams.get('group_id') === String(groupId);
    if ((isGroupsApi || isGroupEventsApi) && response.status() >= 400) {
      failures.push(`${response.request().method()} ${url.pathname}${url.search} -> ${response.status()}`);
    }
  });

  return failures;
}

async function visibleSectionLabels(locator: import('@playwright/test').Locator): Promise<string[]> {
  const labels = (await locator.allTextContents()).map((label) => label.trim()).filter(Boolean);
  expect(labels.length, 'the owner fixture must expose at least one Groups section').toBeGreaterThan(0);
  expect(new Set(labels).size, 'available Groups section labels must be unique').toBe(labels.length);
  expect(labels, 'Automation must be reachable for the owner fixture').toContain('Automation');
  return labels;
}

async function expectSectionReady(page: Page, label: string, viewportWidth: number): Promise<void> {
  const panel = page.getByRole('tabpanel', { name: label, exact: true });
  await expect(panel).toBeVisible({ timeout: 45_000 });
  await expect(
    panel.locator('h2:visible, h3:visible, [role="alert"]:visible, [role="searchbox"]:visible, article:visible, a:visible, button:visible').first(),
    `${label} must expose a visible heading, state, or operable control`,
  ).toBeVisible({ timeout: 45_000 });

  const dimensions = await page.evaluate(() => ({
    pageWidth: document.documentElement.scrollWidth,
    viewportWidth: window.innerWidth,
  }));
  expect(
    dimensions.pageWidth,
    `${label} must not make the ${viewportWidth}px Groups page overflow horizontally`,
  ).toBeLessThanOrEqual(dimensions.viewportWidth + 1);
}

async function expectSubgroupCardRoute(
  page: Page,
  label: string,
  childGroup: SeededGroup,
): Promise<void> {
  const panel = page.getByRole('tabpanel', { name: label, exact: true });
  const subgroupLink = panel.locator(`a[href$="/groups/${childGroup.id}"]`).filter({
    hasText: childGroup.name,
  });
  await expect(subgroupLink, 'the seeded child group must be visible in the Subgroups section').toBeVisible();
  await expect(subgroupLink).toHaveAttribute(
    'href',
    new RegExp(`/${TENANT}/groups/${childGroup.id}$`),
  );
}

async function cancelFixtureChallenge(group: SeededGroup, challengeId: number): Promise<void> {
  if (!apiContext) throw new Error('Groups E2E API context is not initialized.');
  const response = await apiContext.delete(apiUrl(`/v2/groups/${group.id}/challenges/${challengeId}`), {
    headers: actorHeaders(group.ownerToken),
  });
  if (![200, 404].includes(response.status())) {
    throw new Error(
      `Groups E2E challenge cancellation cleanup failed (${response.status()}): ${JSON.stringify(await responseBody(response))}`,
    );
  }
}

test.beforeAll(async ({ browserName: _browserName }, testInfo: TestInfo) => {
  testInfo.setTimeout(120_000);
  assertSafeFixtureTarget();
  const context = await playwrightRequest.newContext();
  apiContext = context;

  const created: SeededGroup[] = [];
  try {
    const [member, admin] = await Promise.all([
      loginActor(requiredCredential('E2E_USER_EMAIL'), requiredCredential('E2E_USER_PASSWORD')),
      loginActor(requiredCredential('E2E_ADMIN_EMAIL'), requiredCredential('E2E_ADMIN_PASSWORD')),
    ]);
    const runMarker = `${testInfo.project.name}-${testInfo.workerIndex}-${Date.now()}`;
    const ownedGroup = await createFixtureGroup(`E2E Groups ${runMarker} Owner`, member.token, {
      description: `E2E private Group A description ${runMarker}`,
      visibility: 'private',
    });
    created.push(ownedGroup);
    const childGroup = await createFixtureGroup(`E2E Groups ${runMarker} Child`, member.token, {
      description: `E2E run-scoped subgroup ${runMarker}`,
      visibility: 'private',
      parentId: ownedGroup.id,
    });
    created.push(childGroup);
    const joinableGroup = await createFixtureGroup(`E2E Groups ${runMarker} Joinable`, admin.token, {
      description: `E2E public Group B description ${runMarker}`,
      visibility: 'public',
    });
    created.push(joinableGroup);
    const privatePinnedCanary = `E2E PRIVATE PINNED CANARY ${runMarker}`;
    await createPinnedAnnouncement(ownedGroup, privatePinnedCanary);
    harness = { member, admin, ownedGroup, childGroup, joinableGroup, privatePinnedCanary };
    await setJoinableMembership(false);
  } catch (error) {
    let cleanupError: unknown;
    try {
      await cleanupGroups(created, context);
    } catch (candidate) {
      cleanupError = candidate;
    }
    await context.dispose();
    if (apiContext === context) apiContext = undefined;
    if (cleanupError) {
      throw new Error(
        `Groups E2E setup failed: ${error instanceof Error ? error.message : String(error)}\n` +
        `Setup cleanup also failed: ${cleanupError instanceof Error ? cleanupError.message : String(cleanupError)}`,
      );
    }
    throw error;
  }
});

test.beforeEach(async ({ page }) => {
  const { member } = getHarness();
  await bridgeDockerAssetHost(page);
  await page.addInitScript(({ token, refreshToken, tenantId }) => {
    localStorage.setItem('nexus_access_token', token);
    localStorage.setItem('nexus_refresh_token', refreshToken);
    localStorage.setItem('nexus_tenant_id', String(tenantId));
    localStorage.setItem('dev_notice_dismissed', '2.1');
  }, { token: member.token, refreshToken: member.refreshToken, tenantId: member.tenantId });
});

test.afterAll(async ({ browserName: _browserName }, testInfo: TestInfo) => {
  testInfo.setTimeout(120_000);
  const context = apiContext;
  const currentHarness = harness;
  apiContext = undefined;
  harness = undefined;
  if (!context) return;

  try {
    if (currentHarness) {
      const cleanupTargets = [
        currentHarness.ownedGroup,
        currentHarness.childGroup,
        currentHarness.joinableGroup,
      ];
      for (const name of transientGroupNames) {
        let candidate: SeededGroup | undefined;
        for (let attempt = 0; attempt < 5 && !candidate; attempt += 1) {
          candidate = await findFixtureGroup(name, currentHarness.member.token, context);
          if (!candidate) await new Promise((resolve) => setTimeout(resolve, 250));
        }
        if (candidate) cleanupTargets.push(candidate);
      }
      await cleanupGroups(cleanupTargets, context);
    }
  } finally {
    transientGroupNames.clear();
    await context.dispose();
  }
});

test.describe('Groups - Browse', () => {
  test('displays the authenticated groups page', async ({ page }) => {
    const groupsPage = await openGroups(page);

    await expect(page).toHaveURL(new RegExp(`/${TENANT}/groups$`));
    await expect(groupsPage.pageHeading).toBeVisible();
  });

  test('shows the run-scoped owner fixture instead of relying on ambient records', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const groupsPage = await openGroups(page);
    await groupsPage.searchGroups(ownedGroup.name);

    await expect(groupsPage.groupCardNamed(ownedGroup.name)).toBeVisible();
  });

  test('always exposes search', async ({ page }) => {
    const groupsPage = await openGroups(page);

    await expect(groupsPage.groupsSearchInput).toBeVisible();
    await expect(groupsPage.groupsSearchInput).toBeEditable();
  });

  test('always exposes create group for the authenticated actor', async ({ page }) => {
    const groupsPage = await openGroups(page);

    await expect(groupsPage.createGroupButton).toBeVisible();
    await expect(groupsPage.createGroupButton).toHaveAttribute('href', new RegExp(`/${TENANT}/groups/create$`));
  });

  test('searches by the exact fixture marker', async ({ page }) => {
    const { ownedGroup, joinableGroup } = getHarness();
    const groupsPage = await openGroups(page);
    await groupsPage.searchGroups(ownedGroup.name);

    await expect(groupsPage.groupCardNamed(ownedGroup.name)).toBeVisible();
    await expect(groupsPage.groupCardNamed(joinableGroup.name)).toHaveCount(0);
    await expect.poll(() => new URL(page.url()).searchParams.get('q')).toBe(ownedGroup.name);
  });

  test('renders required information on the fixture card', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const groupsPage = await openGroups(page);
    await groupsPage.searchGroups(ownedGroup.name);
    const card = groupsPage.groupCardNamed(ownedGroup.name);

    await expect(card.getByRole('heading', { level: 3, name: ownedGroup.name })).toBeVisible();
    await expect(card).toContainText(ownedGroup.description);
    await expect(
      page.getByRole('link', { name: `${ownedGroup.name} - Private group - 1 member`, exact: true })
    ).toBeVisible();
  });

  test('navigates from the fixture card to its exact detail route', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const failures = collectGroupsBrowserFailures(page, ownedGroup.id);
    const groupsPage = await openGroups(page);
    await groupsPage.searchGroups(ownedGroup.name);
    await groupsPage.openGroupNamed(ownedGroup.name);

    try {
      await expect(page).toHaveURL(new RegExp(`/${TENANT}/groups/${ownedGroup.id}(?:\\?tab=feed)?$`));
      await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible({ timeout: 45_000 });
      expect(failures, 'card-to-detail navigation must avoid failed Groups APIs and browser errors').toEqual([]);
    } catch (error) {
      throw new Error(
        `Card-to-detail navigation failed. Browser evidence:\n${failures.join('\n') || '(none captured)'}`,
        { cause: error },
      );
    }
  });
});

test.describe('Groups - My Groups', () => {
  test('shows the member-owned fixture in My Groups', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigateToMyGroups();
    await groupsPage.searchGroups(ownedGroup.name);

    await expect(groupsPage.groupCardNamed(ownedGroup.name)).toBeVisible();
    await expect(page.getByRole('radio', { name: 'My Groups', exact: true })).toHaveAttribute('aria-checked', 'true');
  });

  test('shows a real empty state for a fixture the actor has not joined', async ({ page }) => {
    const { joinableGroup } = getHarness();
    await setJoinableMembership(false);
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigateToMyGroups();
    await groupsPage.searchGroups(joinableGroup.name);

    await expect(groupsPage.noGroupsMessage).toBeVisible({ timeout: 15_000 });
    await expect(groupsPage.groupCardNamed(joinableGroup.name)).toHaveCount(0);
  });
});

test.describe('Groups - Create', () => {
  test('navigates to create group from the visible action', async ({ page }) => {
    const groupsPage = await openGroups(page);
    await groupsPage.clickCreateGroup();

    await expect(page).toHaveURL(new RegExp(`/${TENANT}/groups/create$`));
    await expect(page.getByRole('heading', { level: 1, name: 'Create New Group' })).toBeVisible();
  });

  test('displays the complete create group form', async ({ page }) => {
    const createPage = new CreateGroupPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await expect(createPage.nameInput).toBeVisible();
    await expect(createPage.descriptionTextarea).toBeVisible();
    await expect(createPage.visibilitySelect).toBeVisible();
    await expect(createPage.submitButton).toBeVisible();
  });

  test('reports both required-field errors without a fallback assertion', async ({ page }) => {
    const createPage = new CreateGroupPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();
    await createPage.submit();

    await expect(page.getByText('Group name is required', { exact: true })).toBeVisible();
    await expect(page.getByText('Description is required', { exact: true })).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/${TENANT}/groups/create$`));
  });

  test('selects public/private visibility deterministically', async ({ page }) => {
    const createPage = new CreateGroupPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await expect(createPage.visibilitySelect).toContainText('Public');
    await createPage.selectVisibility('private');
    await expect(createPage.visibilitySelect).toContainText('Private');
    await createPage.selectVisibility('public');
    await expect(createPage.visibilitySelect).toContainText('Public');
  });

  test('creates a run-scoped group and deletes it in a verified finally block', async ({ page }) => {
    const { member } = getHarness();
    const createPage = new CreateGroupPage(page);
    const name = `E2E Groups UI ${test.info().project.name}-${Date.now()}`;
    let created: SeededGroup | undefined;
    transientGroupNames.add(name);

    try {
      await createPage.navigate();
      await createPage.waitForLoad();
      const responsePromise = page.waitForResponse((response) => isGroupsCollectionResponse(response, 'POST'));
      await createPage.createGroup({ name, description: GROUP_DESCRIPTION, visibility: 'public' });
      const response = await responsePromise;
      const body = await responseBody(response) as JsonEnvelope<{ id?: number }>;
      const id = Number(body.data?.id);
      expect(response.status()).toBe(201);
      expect(Number.isSafeInteger(id) && id > 0).toBe(true);
      created = { id, name, description: GROUP_DESCRIPTION, visibility: 'public', ownerToken: member.token };

      await expect(page).toHaveURL(new RegExp(`/${TENANT}/groups/${id}(?:\\?tab=feed)?$`));
      await expect(page.getByRole('heading', { level: 1, name })).toBeVisible({ timeout: 45_000 });
    } finally {
      const cleanupTarget = created ?? await findFixtureGroup(name, member.token);
      if (cleanupTarget) {
        await deleteFixtureGroup(cleanupTarget);
        transientGroupNames.delete(name);
      }
    }
  });
});

test.describe('Groups - Detail', () => {
  test('displays the exact fixture name and description', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await openGroup(page, ownedGroup);

    await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible({ timeout: 45_000 });
    await expect(page.getByText(ownedGroup.description, { exact: true })).toBeVisible();
  });

  test('shows the seeded owner membership count', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const detailPage = await openGroup(page, ownedGroup);

    await expect(detailPage.memberCount).toHaveText('1 member');
    expect(await detailPage.getMemberCount()).toBe(1);
  });

  test('activates every owner header action and cancels destructive deletion safely', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: {
          writeText: async (value: string) => {
            localStorage.setItem('e2e_groups_copied_text', value);
          },
        },
      });
    });
    const detailPage = await openGroup(page, ownedGroup);

    await expect(detailPage.settingsButton).toBeVisible();
    await expect(detailPage.inviteButton).toBeVisible();
    await expect(detailPage.leaveButton).toHaveCount(0);

    await detailPage.settingsButton.click();
    const settingsDialog = page.getByRole('dialog', { name: 'Group Settings' });
    await expect(settingsDialog).toBeVisible();
    await settingsDialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(settingsDialog).toHaveCount(0);

    await page.getByRole('button', { name: 'Notification preferences', exact: true }).click();
    const preferencesDialog = page.getByRole('dialog', { name: 'Notification Preferences' });
    await expect(preferencesDialog).toBeVisible();
    await preferencesDialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(preferencesDialog).toHaveCount(0);

    await detailPage.inviteButton.click();
    const inviteDialog = page.getByRole('dialog', { name: 'Invite Members' });
    await expect(inviteDialog).toBeVisible();
    const linkResponsePromise = page.waitForResponse((response) => {
      const url = new URL(response.url());
      return response.request().method() === 'POST'
        && url.pathname.endsWith(`/api/v2/groups/${ownedGroup.id}/invites/link`);
    });
    await inviteDialog.getByRole('button', { name: 'Generate Invite Link', exact: true }).click();
    const linkResponse = await linkResponsePromise;
    expect(linkResponse.status()).toBe(201);
    const inviteLinkInput = inviteDialog.getByRole('textbox', { name: 'Share a link anyone can use to join:' });
    await expect(inviteLinkInput).toHaveValue(new RegExp(`/${TENANT}/groups/invite/`));
    await inviteDialog.getByRole('button', { name: 'Copy', exact: true }).first().click();
    await expect.poll(() => page.evaluate(() => localStorage.getItem('e2e_groups_copied_text')))
      .toBe(await inviteLinkInput.inputValue());
    await inviteDialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(inviteDialog).toHaveCount(0);

    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    const deleteDialog = page.getByRole('alertdialog', { name: 'Delete Group' });
    await expect(deleteDialog).toBeVisible();
    await expect(deleteDialog.getByRole('button', { name: 'Delete Group', exact: true })).toBeDisabled();
    await deleteDialog.getByText('Cancel', { exact: true }).click();
    await expect(deleteDialog).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible();
  });

  test('joins the known public fixture from a normalized nonmember state', async ({ page }) => {
    const { joinableGroup } = getHarness();
    await setJoinableMembership(false);

    try {
      const detailPage = await openGroup(page, joinableGroup);
      await expect(detailPage.joinButton).toBeVisible();
      await detailPage.join();
      await expect(detailPage.leaveButton).toBeVisible();
    } finally {
      await setJoinableMembership(false);
    }
  });
});

test.describe('Groups - Complete Section Navigation', () => {
  test('walks every available owner section on desktop without API or console failure', async ({ page }) => {
    test.setTimeout(300_000);
    const { ownedGroup, childGroup } = getHarness();
    await page.setViewportSize({ width: 1280, height: 900 });
    const failures = collectGroupsBrowserFailures(page, ownedGroup.id);
    let detailPage: GroupDetailPage;
    try {
      detailPage = await openGroup(page, ownedGroup);
    } catch (error) {
      throw new Error(
        `Owner detail failed before section navigation. Browser evidence:\n${failures.join('\n') || '(none captured)'}`,
        { cause: error },
      );
    }
    const tablist = page.getByRole('tablist', { name: 'Group navigation' });
    await expect(tablist).toBeVisible();
    const labels = await visibleSectionLabels(tablist.getByRole('tab'));

    for (const label of labels) {
      const sectionKey = sectionKeyForLabel(label);
      await detailPage.switchToSection(label, sectionKey);
      await expectSectionReady(page, label, 1280);
      if (sectionKey === 'subgroups') await expectSubgroupCardRoute(page, label, childGroup);
    }

    expect(failures, 'every desktop Groups section must avoid failed API calls and browser errors').toEqual([]);
  });

  test('walks every available owner dropdown section at 390px and 320px without overflow', async ({ page }) => {
    test.setTimeout(300_000);
    const { ownedGroup, childGroup } = getHarness();
    await page.setViewportSize({ width: 390, height: 844 });
    const failures = collectGroupsBrowserFailures(page, ownedGroup.id);
    const detailPage = await openGroup(page, ownedGroup);

    for (const viewport of [390, 320] as const) {
      await page.setViewportSize({ width: viewport, height: viewport === 390 ? 844 : 640 });
      await page.evaluate(() => window.scrollTo(0, 0));

      const trigger = page.getByRole('button', { name: /^Group navigation:/ });
      await expect(trigger).toBeVisible();
      await trigger.click();
      const menu = page.locator('[role="menu"], [role="listbox"]').filter({
        has: page.locator('[role="menuitem"], [role="menuitemradio"], [role="option"]'),
      }).first();
      await expect(menu).toBeVisible();
      const items = menu.locator('[role="menuitem"], [role="menuitemradio"], [role="option"]');
      const labels = await visibleSectionLabels(items);
      await page.keyboard.press('Escape');
      await expect(menu).toBeHidden();

      for (const label of labels) {
        const sectionKey = sectionKeyForLabel(label);
        await detailPage.switchToSection(label, sectionKey);
        await expectSectionReady(page, label, viewport);
        if (sectionKey === 'subgroups') await expectSubgroupCardRoute(page, label, childGroup);
      }
    }

    expect(failures, 'every mobile Groups section must avoid failed API calls and browser errors').toEqual([]);
  });
});

test.describe('Groups - State Boundary', () => {
  test('clears private Group A state before delayed public Group B resolves', async ({ page }) => {
    const { ownedGroup, joinableGroup, privatePinnedCanary } = getHarness();
    await setJoinableMembership(false);
    await openGroup(page, ownedGroup);
    await expect(page.getByText(privatePinnedCanary, { exact: true })).toBeVisible({ timeout: 45_000 });

    const publicDetailPattern = `**/api/v2/groups/${joinableGroup.id}`;
    let releasePublicDetail!: () => void;
    const publicDetailGate = new Promise<void>((resolve) => {
      releasePublicDetail = resolve;
    });
    await page.route(publicDetailPattern, async (route) => {
      await publicDetailGate;
      await route.continue();
    });

    try {
      await page.evaluate(({ path }) => {
        window.history.pushState({}, '', path);
        window.dispatchEvent(new PopStateEvent('popstate'));
      }, { path: `/${TENANT}/groups/${joinableGroup.id}` });
      await expect(page).toHaveURL(new RegExp(`/${TENANT}/groups/${joinableGroup.id}$`));

      await expect(page.getByText(privatePinnedCanary, { exact: true })).toHaveCount(0);
      await expect(page.getByText(ownedGroup.description, { exact: true })).toHaveCount(0);
      await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toHaveCount(0);

      releasePublicDetail();
      await expect(page.getByRole('heading', { level: 1, name: joinableGroup.name })).toBeVisible({ timeout: 45_000 });
      await expect(page.getByText(joinableGroup.description, { exact: true })).toBeVisible();
      await expect(page.getByText(privatePinnedCanary, { exact: true })).toHaveCount(0);
      await expect(page.getByRole('tab', { name: 'Members', exact: true })).toHaveCount(0);
      await expect(page.getByRole('tab', { name: 'Announcements', exact: true })).toHaveCount(0);
    } finally {
      releasePublicDetail();
      await page.unroute(publicDetailPattern);
    }
  });
});

test.describe('Groups - Discussions', () => {
  test('switches to the Discussion section and updates URL state', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const detailPage = await openGroup(page, ownedGroup);
    await detailPage.switchToSection('Discussion', 'discussion');

    await expect(page.getByRole('heading', { level: 2, name: 'Discussions' })).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/${TENANT}/groups/${ownedGroup.id}\\?tab=discussion$`));
  });

  test('shows the new discussion action to a seeded member', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const detailPage = await openGroup(page, ownedGroup);
    await detailPage.switchToSection('Discussion', 'discussion');

    await expect(detailPage.createDiscussionButton).toBeVisible();
  });
});

test.describe('Groups - Posts', () => {
  test('shows the create-post control to a seeded member', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const detailPage = await openGroup(page, ownedGroup);

    await expect(detailPage.createPostButton).toBeVisible();
  });
});

test.describe('Groups - Members Tab', () => {
  test('shows the seeded owner in the Members section', async ({ page }) => {
    const { member, ownedGroup } = getHarness();
    const detailPage = await openGroup(page, ownedGroup);
    await detailPage.switchToMembersTab();

    await expect(detailPage.membersList).toBeVisible();
    await expect(detailPage.memberItems.filter({ hasText: member.name })).toHaveCount(1);
  });
});

test.describe('Groups - Invite', () => {
  test('opens the invite dialog for the seeded group owner', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const detailPage = await openGroup(page, ownedGroup);
    await detailPage.inviteButton.click();

    await expect(page.getByRole('dialog', { name: 'Invite Members' })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Email addresses to invite' })).toBeVisible();
  });
});

test.describe('Groups - Challenges', () => {
  test('creates a run-scoped challenge, dismisses cancellation, then confirms a verified cancel', async ({ page }) => {
    test.setTimeout(180_000);
    const { ownedGroup } = getHarness();
    const detailPage = await openGroup(page, ownedGroup);
    const title = `E2E Challenge ${test.info().project.name}-${Date.now()}`;
    let challengeId: number | undefined;
    let cancelRequests = 0;
    page.on('request', (request) => {
      const url = new URL(request.url());
      if (request.method() === 'DELETE'
        && url.pathname.startsWith(`/api/v2/groups/${ownedGroup.id}/challenges/`)) {
        cancelRequests += 1;
      }
    });

    try {
      await detailPage.switchToSection('Challenges', 'challenges');
      await page.getByRole('button', { name: 'Create Challenge', exact: true }).first().click();
      const createDialog = page.getByRole('dialog', { name: 'Create Challenge' });
      await expect(createDialog).toBeVisible();
      const titleInput = createDialog.getByPlaceholder('Challenge title');
      const descriptionInput = createDialog.getByPlaceholder('Describe the challenge and what participants need to do...');
      const targetInput = createDialog.getByPlaceholder('e.g. 50');
      await expect(titleInput).toHaveAccessibleName(/Title/);
      await expect(descriptionInput).toHaveAccessibleName('Description');
      await expect(targetInput).toHaveAccessibleName(/Target Value/);
      await titleInput.fill(title);
      await descriptionInput.fill('Run-scoped challenge for verified browser cleanup.');
      await targetInput.fill('3');
      await createDialog.locator('input[type="date"]').fill('2099-12-31');

      const createResponsePromise = page.waitForResponse((response) => {
        const url = new URL(response.url());
        return response.request().method() === 'POST'
          && url.pathname.endsWith(`/api/v2/groups/${ownedGroup.id}/challenges`);
      });
      await createDialog.getByRole('button', { name: 'Create Challenge', exact: true }).click();
      const createResponse = await createResponsePromise;
      const createBody = await responseBody(createResponse) as JsonEnvelope<{ id?: number }>;
      challengeId = Number(createBody.data?.id);
      expect(createResponse.status()).toBe(201);
      expect(Number.isSafeInteger(challengeId) && challengeId > 0).toBe(true);
      await expect(page.getByRole('heading', { level: 3, name: title })).toBeVisible();

      const cancelTrigger = page.getByRole('button', { name: `Cancel challenge “${title}”`, exact: true });
      await cancelTrigger.click();
      let cancelDialog = page.getByRole('alertdialog', { name: 'Cancel challenge?' });
      await expect(cancelDialog).toBeVisible();
      await cancelDialog.getByText('Cancel', { exact: true }).click();
      await expect(cancelDialog).toHaveCount(0);
      expect(cancelRequests).toBe(0);
      await expect(page.getByRole('heading', { level: 3, name: title })).toBeVisible();

      await cancelTrigger.click();
      cancelDialog = page.getByRole('alertdialog', { name: 'Cancel challenge?' });
      const cancelResponsePromise = page.waitForResponse((response) => {
        const url = new URL(response.url());
        return response.request().method() === 'DELETE'
          && url.pathname.endsWith(`/api/v2/groups/${ownedGroup.id}/challenges/${challengeId}`);
      });
      await cancelDialog.getByRole('button', { name: 'Cancel challenge', exact: true }).click();
      const cancelResponse = await cancelResponsePromise;
      const cancelBody = await responseBody(cancelResponse) as JsonEnvelope<{
        challenge?: { id?: number; status?: string };
        changed?: boolean;
      }>;
      expect(cancelResponse.status()).toBe(200);
      expect(cancelBody.data?.challenge).toMatchObject({ id: challengeId, status: 'cancelled' });
      expect(cancelBody.data?.changed).toBe(true);
      await expect(cancelTrigger).toHaveCount(0);
      expect(cancelRequests).toBe(1);

      const challengeHistory = page.getByRole('button', { name: /Challenge history/ });
      if (await challengeHistory.getAttribute('aria-expanded') !== 'true') {
        await challengeHistory.click();
      }
      await expect(challengeHistory).toHaveAttribute('aria-expanded', 'true');
      await expect(page.getByRole('heading', { level: 3, name: title })).toBeVisible();
      await expect(page.getByText('Cancelled', { exact: true })).toBeVisible();
    } finally {
      if (challengeId) await cancelFixtureChallenge(ownedGroup, challengeId);
    }
  });
});

test.describe('Groups - Leave', () => {
  test('leaves the known fixture and restores its nonmember baseline', async ({ page }) => {
    const { joinableGroup } = getHarness();
    await setJoinableMembership(true);

    try {
      const detailPage = await openGroup(page, joinableGroup);
      await expect(detailPage.leaveButton).toBeVisible();
      await detailPage.leave();
      await expect(detailPage.joinButton).toBeVisible();
    } finally {
      await setJoinableMembership(false);
    }
  });
});

test.describe('Groups - Accessibility', () => {
  test('has exactly one visible level-one page heading', async ({ page }) => {
    const groupsPage = await openGroups(page);

    await expect(groupsPage.pageHeading).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
  });

  test('opens a fixture card using keyboard activation', async ({ page }) => {
    const { ownedGroup } = getHarness();
    const groupsPage = await openGroups(page);
    await groupsPage.searchGroups(ownedGroup.name);
    const link = groupsPage.groupLinkNamed(ownedGroup.name);

    await link.focus();
    await expect(link).toBeFocused();
    await Promise.all([
      page.waitForURL(new RegExp(`/${TENANT}/groups/${ownedGroup.id}`)),
      link.press('Enter'),
    ]);
    await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible({ timeout: 45_000 });
  });
});

test.describe('Groups - Rendering Modes and History', () => {
  test('renders the owner surface in both light and dark themes', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await openGroup(page, ownedGroup);

    for (const theme of ['light', 'dark'] as const) {
      await page.evaluate((nextTheme) => localStorage.setItem('nexus_theme', nextTheme), theme);
      await page.reload({ waitUntil: 'domcontentloaded' });
      await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible({ timeout: 45_000 });
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
      const widths = await page.evaluate(() => ({
        page: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
      }));
      expect(widths.page, `${theme} theme must not overflow`).toBeLessThanOrEqual(widths.viewport + 1);
    }
  });

  test('selects and persists Arabic with a real RTL document direction', async ({ page }) => {
    const { member, ownedGroup } = getHarness();
    if (!apiContext) throw new Error('Groups E2E API context is not initialized.');
    const profileResponse = await apiContext.get(apiUrl('/v2/users/me'), {
      headers: actorHeaders(member.token),
    });
    const profileBody = await responseBody(profileResponse) as JsonEnvelope<{ preferred_language?: string }>;
    expect(profileResponse.ok()).toBe(true);
    const originalLanguage = profileBody.data?.preferred_language ?? 'en';
    const initialViewport = page.viewportSize();

    try {
      if (initialViewport && initialViewport.width < 768) {
        await page.setViewportSize({ width: 1280, height: 800 });
      }
      await openGroup(page, ownedGroup);
      const languageResponse = page.waitForResponse((response) => {
        const url = new URL(response.url());
        return response.request().method() === 'PUT'
          && url.pathname.endsWith('/api/v2/users/me/language');
      });
      await page.getByRole('button', { name: /^Language:/ }).click();
      await page.getByText('AR', { exact: true }).click();
      expect((await languageResponse).status()).toBeLessThan(400);

      await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
      if (initialViewport && initialViewport.width < 768) {
        await page.setViewportSize(initialViewport);
      }
      await page.reload({ waitUntil: 'domcontentloaded' });
      await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible({ timeout: 45_000 });
      await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
      const widths = await page.evaluate(() => ({
        page: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
      }));
      expect(widths.page, 'Arabic RTL Groups detail must not overflow').toBeLessThanOrEqual(widths.viewport + 1);
    } finally {
      const restoreResponse = await apiContext.put(apiUrl('/v2/users/me/language'), {
        data: { language: originalLanguage },
        headers: actorHeaders(member.token),
      });
      expect(restoreResponse.ok(), JSON.stringify(await responseBody(restoreResponse))).toBe(true);
    }
  });

  test('remains operable when reduced motion is requested', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await openGroup(page, ownedGroup);

    expect(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true);
    await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible();
    await expect(page.getByRole('tabpanel', { name: 'Feed', exact: true })).toBeVisible();
  });

  test('keeps the Groups surface usable in forced-colors mode or records runtime support', async ({ page }, testInfo) => {
    const { ownedGroup } = getHarness();
    let emulationAccepted = true;
    try {
      await page.emulateMedia({ forcedColors: 'active' });
    } catch (error) {
      emulationAccepted = false;
      testInfo.annotations.push({
        type: 'forced-colors-limit',
        description: `Browser runtime rejected forced-colors emulation: ${error instanceof Error ? error.message : String(error)}`,
      });
    }

    await openGroup(page, ownedGroup);
    const mediaActive = await page.evaluate(() => matchMedia('(forced-colors: active)').matches);
    if (emulationAccepted && !mediaActive) {
      testInfo.annotations.push({
        type: 'forced-colors-limit',
        description: 'Browser accepted emulation but did not expose (forced-colors: active).',
      });
    } else if (emulationAccepted) {
      expect(mediaActive).toBe(true);
    }
    await expect(page.getByRole('heading', { level: 1, name: ownedGroup.name })).toBeVisible();
    await expect(page.getByRole('tabpanel', { name: 'Feed', exact: true })).toBeVisible();
  });

  test('changes desktop sections using only the keyboard', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await page.setViewportSize({ width: 1280, height: 800 });
    await openGroup(page, ownedGroup);

    const tabs = page.getByRole('tablist', { name: 'Group navigation' }).getByRole('tab');
    const feedTab = tabs.nth(0);
    const nextTab = tabs.nth(1);
    const nextLabel = (await nextTab.innerText()).trim();
    const nextSection = sectionKeyForLabel(nextLabel);
    await feedTab.focus();
    await expect(feedTab).toBeFocused();
    await page.keyboard.press('ArrowRight');

    await expect(nextTab).toBeFocused();
    await expect(nextTab).toHaveAttribute('aria-selected', 'true');
    await expect.poll(() => new URL(page.url()).searchParams.get('tab')).toBe(nextSection);
    await expect(page.getByRole('tabpanel', { name: nextLabel, exact: true })).toBeVisible();
  });

  test('restores section state through browser Back and Forward', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await page.setViewportSize({ width: 1280, height: 800 });
    const detailPage = await openGroup(page, ownedGroup);

    await detailPage.switchToSection('Discussion', 'discussion');
    await detailPage.switchToSection('Members', 'members');
    await page.goBack();
    await expect.poll(() => new URL(page.url()).searchParams.get('tab')).toBe('discussion');
    await expect(page.getByRole('tab', { name: 'Discussion', exact: true })).toHaveAttribute('aria-selected', 'true');
    await page.goForward();
    await expect.poll(() => new URL(page.url()).searchParams.get('tab')).toBe('members');
    await expect(page.getByRole('tab', { name: 'Members', exact: true })).toHaveAttribute('aria-selected', 'true');
  });
});

test.describe('Groups - Mobile Behavior', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('uses the mobile section menu without horizontal page overflow', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await openGroup(page, ownedGroup);

    await expect(page.getByRole('button', { name: /^Group navigation:/ })).toBeVisible();
    const desktopTablist = page.getByRole('tablist', { name: 'Group navigation', includeHidden: true });
    await expect(desktopTablist).toHaveCount(1);
    await expect(desktopTablist).toBeHidden();
    const hasHorizontalOverflow = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth + 1,
    );
    expect(hasHorizontalOverflow).toBe(false);
  });

  test('keeps the active navigation visible below the fixed header across the viewport matrix', async ({ page }) => {
    const { ownedGroup } = getHarness();
    await openGroup(page, ownedGroup);

    const viewports = [
      { width: 320, height: 640, mode: 'mobile' },
      { width: 390, height: 844, mode: 'mobile' },
      { width: 768, height: 1024, mode: 'desktop' },
      { width: 1280, height: 800, mode: 'desktop' },
    ] as const;

    for (const viewport of viewports) {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.evaluate(() => window.scrollTo(0, 0));

      const mobileTrigger = page.getByRole('button', { name: /^Group navigation: Feed$/ });
      const desktopTablist = page.getByRole('tablist', { name: 'Group navigation', includeHidden: true });
      const navigationSurface = viewport.mode === 'mobile'
        ? page.locator('div.sticky').filter({ has: mobileTrigger })
        : page.locator('div.sticky').filter({ has: desktopTablist });

      if (viewport.mode === 'mobile') {
        await expect(mobileTrigger).toBeVisible();
        await expect(desktopTablist).toBeHidden();
      } else {
        await expect(mobileTrigger).toBeHidden();
        await expect(desktopTablist).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Feed', exact: true })).toHaveAttribute('aria-selected', 'true');
      }

      const pageMetrics = await page.evaluate(() => ({
        viewportWidth: window.innerWidth,
        pageWidth: document.documentElement.scrollWidth,
      }));
      expect(
        pageMetrics.pageWidth,
        `document width at ${viewport.width}px must not exceed its viewport`,
      ).toBeLessThanOrEqual(pageMetrics.viewportWidth + 1);

      const initialNavigationBox = await navigationSurface.boundingBox();
      expect(initialNavigationBox, `navigation surface must exist at ${viewport.width}px`).not.toBeNull();
      expect(initialNavigationBox!.x).toBeGreaterThanOrEqual(-1);
      expect(initialNavigationBox!.x + initialNavigationBox!.width).toBeLessThanOrEqual(viewport.width + 1);

      await navigationSurface.evaluate((element) => {
        const documentTop = element.getBoundingClientRect().top + window.scrollY;
        window.scrollTo(0, documentTop + 160);
      });
      await page.waitForTimeout(350);

      const headerBox = await page.locator('header').first().boundingBox();
      const stickyNavigationBox = await navigationSurface.boundingBox();
      expect(headerBox, `fixed header must exist at ${viewport.width}px`).not.toBeNull();
      expect(stickyNavigationBox, `sticky navigation must remain visible at ${viewport.width}px`).not.toBeNull();
      const headerBottom = headerBox!.y + headerBox!.height;
      expect(
        stickyNavigationBox!.y,
        `sticky navigation at ${viewport.width}px must not sit behind the fixed header`,
      ).toBeGreaterThanOrEqual(headerBottom - 2);
      expect(stickyNavigationBox!.y).toBeLessThan(viewport.height);
    }
  });
});
