// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Seeded two-actor E2E harness.
 *
 * `seedTwoActors()` runs once in global setup (after auth) and guarantees a
 * DETERMINISTIC fixture against the test tenant so that money/auth specs can
 * assert real outcomes instead of `expect(X || true)`:
 *
 *   - userA  — the primary logged-in member (storage state in fixtures/.auth/user.json)
 *   - admin  — a second, credential-controlled actor (used as transfer recipient /
 *              exchange counterparty / listing owner; storage in admin.json)
 *   - balance — userA is topped up to a known floor (>= BALANCE_FLOOR hours) via the
 *              admin wallet-grant API, so the "Send Credits" button is enabled and
 *              transfer assertions are deterministic
 *   - userB  — a real, searchable member (found via the wallet user-search API) that
 *              the transfer modal autocomplete can resolve
 *   - listing — an active listing owned by admin (best-effort, reuse-or-create)
 *   - exchange — userA -> admin exchange request against that listing (best-effort)
 *
 * The result is written to fixtures/.seed/seed.json (gitignored) and read back by
 * specs via `loadSeed()`. The CORE (auth + balance + userB) is hard; listing/exchange
 * are best-effort and degrade to `undefined` without failing setup.
 *
 * All API calls go through the same proxy/base the React app uses (E2E_API_URL),
 * via plain fetch with a per-call timeout so a slow post-write hook can't stall setup.
 */
import * as fs from 'fs';
import * as path from 'path';

const API = (process.env.E2E_API_URL || 'http://localhost:8090').replace(/\/$/, '');
const TENANT = process.env.E2E_TENANT || 'hour-timebank';
const TENANT_ID = parseInt(process.env.E2E_TENANT_ID || '2', 10);
const BALANCE_FLOOR = 10;
const LISTING_MARKER = 'E2E Seed Offer (harness)';

const SEED_DIR = path.join(__dirname, '..', 'fixtures', '.seed');
const SEED_FILE = path.join(SEED_DIR, 'seed.json');

export interface SeedActor {
  id: number;
  name: string;
  email?: string;
  username?: string;
}

export interface Seed {
  tenant: { slug: string; id: number };
  userA: SeedActor & { balanceFloor: number; balance: number };
  admin: SeedActor;
  userB: SeedActor;
  listing?: { id: number; title: string; ownerId: number };
  exchange?: { id: number; status: string };
  seededAt: string;
}

interface ApiResult<T = any> {
  status: number;
  ok: boolean;
  json: T | null;
}

async function api<T = any>(
  method: string,
  pathname: string,
  token?: string,
  body?: unknown,
  timeoutMs = 12000
): Promise<ApiResult<T>> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const res = await fetch(`${API}/api${pathname}`, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-Tenant-Slug': TENANT,
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
    let json: T | null = null;
    try {
      json = (await res.json()) as T;
    } catch {
      json = null;
    }
    return { status: res.status, ok: res.ok, json };
  } catch {
    // Aborted/network — caller treats as best-effort failure.
    return { status: 0, ok: false, json: null };
  } finally {
    clearTimeout(timer);
  }
}

/** Laravel API wraps payloads in { data: ... }; unwrap one level when present. */
function unwrap<T = any>(json: any): T {
  return json && typeof json === 'object' && 'data' in json ? json.data : json;
}

export interface LoginResult {
  token: string | null;
  user: any | null;
}

export async function apiLogin(email: string, password: string): Promise<LoginResult> {
  // Retry a couple of times: under local load the first request can exceed the
  // per-call timeout, which would otherwise abort the whole seed.
  for (let attempt = 1; attempt <= 3; attempt++) {
    const r = await api('POST', '/auth/login', undefined, { email, password, tenant_slug: TENANT }, 20000);
    const d: any = r.json || {};
    const token = d.access_token ?? d?.data?.access_token ?? null;
    const user = d.user ?? d?.data?.user ?? null;
    if (token && user) return { token, user };
    if (attempt < 3) await new Promise((res) => setTimeout(res, 1000));
  }
  return { token: null, user: null };
}

async function getBalance(token: string): Promise<number> {
  const r = await api('GET', '/v2/wallet/balance', token);
  const d = unwrap<any>(r.json) || {};
  return typeof d.balance === 'number' ? d.balance : 0;
}

/** Find a searchable member distinct from userA via the wallet user-search API. */
async function resolveUserB(token: string, excludeId: number): Promise<SeedActor | null> {
  for (const q of ['a', 'e', 'o', 'i']) {
    const r = await api('GET', `/v2/wallet/user-search?q=${q}&limit=10`, token);
    const users: any[] = unwrap<any>(r.json)?.users || [];
    const hit = users.find((u) => u.id && u.id !== excludeId);
    if (hit) {
      return {
        id: hit.id,
        name: hit.name || `${hit.first_name ?? ''} ${hit.last_name ?? ''}`.trim() || hit.username,
        username: hit.username || undefined,
      };
    }
  }
  return null;
}

/** Reuse-or-create an active listing owned by `ownerToken`'s user (marker title). */
async function ensureListing(
  ownerToken: string,
  ownerId: number
): Promise<{ id: number; title: string; ownerId: number } | undefined> {
  // 1. Look for an existing seeded listing owned by this user.
  const existing = await api('GET', `/v2/listings?user_id=${ownerId}&per_page=50`, ownerToken);
  const found = (unwrap<any>(existing.json) as any[] | undefined)?.find?.(
    (l: any) => l.title === LISTING_MARKER
  );
  if (found?.id) return { id: found.id, title: LISTING_MARKER, ownerId };

  // 2. Fetch a category id.
  const cats = await api('GET', '/v2/categories', ownerToken);
  const catId = (unwrap<any>(cats.json) as any[] | undefined)?.find?.((c: any) => c.is_active)?.id
    ?? (unwrap<any>(cats.json) as any[] | undefined)?.[0]?.id;
  if (!catId) return undefined;

  // 3. Create (response may be flaky due to post-write hooks — ignore it, re-query).
  await api('POST', '/v2/listings', ownerToken, {
    title: LISTING_MARKER,
    description: 'Seeded listing for the E2E two-actor exchange harness. Safe to delete.',
    type: 'offer',
    service_type: 'remote_only',
    category_id: catId,
    hours_estimate: 1,
    location: 'Remote',
  });

  const recheck = await api('GET', `/v2/listings?user_id=${ownerId}&per_page=50`, ownerToken);
  const created = (unwrap<any>(recheck.json) as any[] | undefined)?.find?.(
    (l: any) => l.title === LISTING_MARKER
  );
  return created?.id ? { id: created.id, title: LISTING_MARKER, ownerId } : undefined;
}

/** Best-effort: ensure an exchange exists from userA against the admin-owned listing. */
async function ensureExchange(
  requesterToken: string,
  listingId: number
): Promise<{ id: number; status: string } | undefined> {
  const create = await api('POST', '/v2/exchanges', requesterToken, {
    listing_id: listingId,
    proposed_hours: 1,
    message: 'E2E seed exchange (harness)',
  });
  const ex = unwrap<any>(create.json);
  if (ex?.id) return { id: ex.id, status: ex.status || 'pending' };

  // Duplicate/validation — try to find an existing exchange for this listing.
  const list = await api('GET', '/v2/exchanges?per_page=50', requesterToken);
  const rows: any[] = unwrap<any>(list.json) || [];
  const hit = Array.isArray(rows) ? rows.find((e) => e.listing_id === listingId) : undefined;
  return hit?.id ? { id: hit.id, status: hit.status || 'pending' } : undefined;
}

/**
 * Seed the two-actor fixture and write seed.json. Returns the seed, or null if the
 * core (userA + admin login) could not be established.
 */
export async function seedTwoActors(creds: {
  userA: { email: string; password: string };
  admin: { email: string; password: string };
}): Promise<Seed | null> {
  const [a, adm] = await Promise.all([
    apiLogin(creds.userA.email, creds.userA.password),
    apiLogin(creds.admin.email, creds.admin.password),
  ]);

  if (!a.token || !a.user) {
    console.warn('   [seed] userA login failed — cannot seed.');
    return null;
  }

  // Accept the latest legal documents for both actors. The "Updated legal
  // documents" gate renders a full-screen opaque modal that blocks ALL clicks
  // for authenticated users until accepted — without this, every interaction
  // spec is blocked. Best-effort (no-op if the tenant has no pending docs).
  await Promise.all([
    api('POST', '/v2/legal/acceptance/accept-all', a.token, {}),
    adm.token ? api('POST', '/v2/legal/acceptance/accept-all', adm.token, {}) : Promise.resolve(),
  ]);

  const userA: SeedActor = {
    id: a.user.id,
    name: `${a.user.first_name ?? ''} ${a.user.last_name ?? ''}`.trim() || a.user.email,
    email: a.user.email,
  };

  // Balance floor (needs admin). Best-effort — degrade to current balance.
  let balance = await getBalance(a.token);
  if (adm.token && balance < BALANCE_FLOOR) {
    await api('POST', '/v2/admin/wallet/grant', adm.token, {
      user_id: userA.id,
      amount: BALANCE_FLOOR - balance,
      reason: 'e2e harness balance floor',
    });
    balance = await getBalance(a.token);
  } else if (!adm.token) {
    console.warn('   [seed] admin login failed — balance floor not guaranteed.');
  }

  const admin: SeedActor | null =
    adm.user && adm.token
      ? {
          id: adm.user.id,
          name: `${adm.user.first_name ?? ''} ${adm.user.last_name ?? ''}`.trim() || adm.user.email,
          email: adm.user.email,
        }
      : null;

  const userB = (await resolveUserB(a.token, userA.id)) ?? admin ?? userA;

  // Best-effort listing + exchange (admin as counterparty/owner).
  let listing: Seed['listing'];
  let exchange: Seed['exchange'];
  if (admin && adm.token) {
    listing = await ensureListing(adm.token, admin.id);
    if (listing) {
      exchange = await ensureExchange(a.token, listing.id);
    }
  }

  const seed: Seed = {
    tenant: { slug: TENANT, id: TENANT_ID },
    userA: { ...userA, balanceFloor: BALANCE_FLOOR, balance },
    admin: admin ?? userA,
    userB,
    listing,
    exchange,
    seededAt: new Date().toISOString(),
  };

  fs.mkdirSync(SEED_DIR, { recursive: true });
  fs.writeFileSync(SEED_FILE, JSON.stringify(seed, null, 2));
  console.log(
    `   [seed] userA=${userA.id} balance=${balance}h  admin=${admin?.id ?? 'n/a'}  ` +
      `userB=${userB.id}  listing=${listing?.id ?? 'n/a'}  exchange=${exchange?.id ?? 'n/a'}`
  );
  return seed;
}

/** Read the seed written by global setup. Returns null if absent. */
export function loadSeed(): Seed | null {
  try {
    return JSON.parse(fs.readFileSync(SEED_FILE, 'utf8')) as Seed;
  } catch {
    return null;
  }
}

/** Like loadSeed() but throws — use in specs that cannot run without the harness. */
export function requireSeed(): Seed {
  const seed = loadSeed();
  if (!seed) {
    throw new Error(
      'E2E seed fixture missing (e2e/fixtures/.seed/seed.json). Did global.setup run with a reachable server + credentials?'
    );
  }
  return seed;
}
