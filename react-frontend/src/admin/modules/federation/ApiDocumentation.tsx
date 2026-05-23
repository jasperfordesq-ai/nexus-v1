// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation API Documentation
 * Developer portal for external partners integrating with the federation API.
 */

import { useState } from 'react';
import {
  Tabs,
  Tab,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Accordion,
  AccordionItem,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Divider,
} from '@heroui/react';
import BookOpen from 'lucide-react/icons/book-open';
import Shield from 'lucide-react/icons/shield';
import Code from 'lucide-react/icons/code';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Webhook from 'lucide-react/icons/webhook';
import HelpCircle from 'lucide-react/icons/circle-help';
import { usePageTitle } from '@/hooks';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Code block helper
// ─────────────────────────────────────────────────────────────────────────────

function CodeBlock({ children }: { children: string }) {
  return (
    <pre className="bg-content2 rounded-lg p-4 font-mono text-sm overflow-x-auto">
      {children.trim()}
    </pre>
  );
}

function MethodChip({ method }: { method: 'GET' | 'POST' }) {
  return (
    <Chip
      size="sm"
      variant="flat"
      color={method === 'GET' ? 'success' : 'primary'}
      className="font-mono font-bold"
    >
      {method}
    </Chip>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 0: Overview (plain-English)
// ─────────────────────────────────────────────────────────────────────────────

function OverviewTab() {
  const { t } = useTranslation('admin');
  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="flex items-center gap-2">
          <HelpCircle size={20} className="text-primary" />
          <div>
            <h3 className="text-lg font-semibold">{t('federation.api_doc_overview_title')}</h3>
            <p className="text-sm text-default-500">{t('federation.api_doc_overview_subtitle')}</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-5 text-sm leading-relaxed">
          <div>
            <h4 className="font-semibold text-base mb-1">{t('federation.api_doc_what_is_federation')}</h4>
            <p>
              {t('federation.api_doc_federation_intro')}
            </p>
            <p className="mt-2">
              {t('federation.api_doc_federation_agreement')}
            </p>
          </div>

          <Divider />

          <div>
            <h4 className="font-semibold text-base mb-1">{t('federation.api_doc_what_is_api_key')}</h4>
            <p>
              {t('federation.api_doc_api_key_plain_intro')}
            </p>
            <p className="mt-2">
              {t('federation.api_doc_api_key_plain_scope')}
            </p>
          </div>

          <Divider />

          <div>
            <h4 className="font-semibold text-base mb-1">{t('federation.api_doc_what_is_webhook')}</h4>
            <p>
              {t('federation.api_doc_webhook_plain_intro')}
            </p>
            <p className="mt-2">
              {t('federation.api_doc_webhook_signed')}
            </p>
          </div>

          <Divider />

          <div>
            <h4 className="font-semibold text-base mb-2">{t('federation.api_doc_when_need_what')}</h4>
            <Table aria-label={t('federation.api_doc_overview_scenarios_aria')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('federation.api_doc_col_scenario')}</TableColumn>
                <TableColumn>{t('federation.api_doc_col_api_key')}</TableColumn>
                <TableColumn>{t('federation.api_doc_col_webhook')}</TableColumn>
              </TableHeader>
              <TableBody>
                <TableRow key="nexus-nexus">
                  <TableCell>{t('federation.api_doc_scenario_nexus')}</TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                  <TableCell><Chip size="sm" color="default" variant="flat">{t('federation.api_doc_optional')}</Chip></TableCell>
                </TableRow>
                <TableRow key="timeoverflow">
                  <TableCell>{t('federation.api_doc_scenario_timeoverflow')}</TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                </TableRow>
                <TableRow key="komunitin">
                  <TableCell>{t('federation.api_doc_scenario_komunitin')}</TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                </TableRow>
                <TableRow key="listings">
                  <TableCell>{t('federation.api_doc_scenario_reading_listings')}</TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                  <TableCell><Chip size="sm" color="default" variant="flat">{t('federation.api_doc_not_needed')}</Chip></TableCell>
                </TableRow>
                <TableRow key="transfer-send">
                  <TableCell>{t('federation.api_doc_scenario_sending_transfer')}</TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                  <TableCell><Chip size="sm" color="default" variant="flat">{t('federation.api_doc_not_needed')}</Chip></TableCell>
                </TableRow>
                <TableRow key="transfer-cancel">
                  <TableCell>{t('federation.api_doc_scenario_transfer_cancelled')}</TableCell>
                  <TableCell><Chip size="sm" color="default" variant="flat">{t('federation.api_doc_not_needed')}</Chip></TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                </TableRow>
                <TableRow key="messages">
                  <TableCell>{t('federation.api_doc_scenario_receiving_messages')}</TableCell>
                  <TableCell><Chip size="sm" color="default" variant="flat">{t('federation.api_doc_not_needed')}</Chip></TableCell>
                  <TableCell><Chip size="sm" color="success" variant="flat">{t('federation.api_doc_required')}</Chip></TableCell>
                </TableRow>
              </TableBody>
            </Table>
            <p className="mt-3 text-default-600">
              {t('federation.api_doc_rule_of_thumb')}
            </p>
          </div>

          <Divider />

          <div>
            <h4 className="font-semibold text-base mb-2">{t('federation.api_doc_setup_title')}</h4>
            <ol className="list-decimal pl-6 space-y-1">
              <li>{t('federation.api_doc_setup_step_partner')}</li>
              <li>{t('federation.api_doc_setup_step_agree')}</li>
              <li>{t('federation.api_doc_setup_step_keys')}</li>
              <li>{t('federation.api_doc_setup_step_webhooks')}</li>
              <li>{t('federation.api_doc_setup_step_handshake')}</li>
              <li>{t('federation.api_doc_setup_step_features')}</li>
            </ol>
          </div>

          <Divider />

          <div>
            <h4 className="font-semibold text-base mb-2">{t('federation.api_doc_safeguards_title')}</h4>
            <Table aria-label={t('federation.api_doc_overview_safeguards_aria')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('federation.api_doc_col_worry')}</TableColumn>
                <TableColumn>{t('federation.api_doc_col_safeguard')}</TableColumn>
              </TableHeader>
              <TableBody>
                <TableRow key="stolen-key">
                  <TableCell>{t('federation.api_doc_worry_stolen_key')}</TableCell>
                  <TableCell>{t('federation.api_doc_safeguard_stolen_key')}</TableCell>
                </TableRow>
                <TableRow key="fake-webhook">
                  <TableCell>{t('federation.api_doc_worry_fake_webhook')}</TableCell>
                  <TableCell>{t('federation.api_doc_safeguard_fake_webhook')}</TableCell>
                </TableRow>
                <TableRow key="dup-webhook">
                  <TableCell>{t('federation.api_doc_worry_duplicate_webhook')}</TableCell>
                  <TableCell>{t('federation.api_doc_safeguard_duplicate_webhook')}</TableCell>
                </TableRow>
                <TableRow key="partner-down">
                  <TableCell>{t('federation.api_doc_worry_partner_down')}</TableCell>
                  <TableCell>{t('federation.api_doc_safeguard_partner_down')}</TableCell>
                </TableRow>
                <TableRow key="misbehaves">
                  <TableCell>{t('federation.api_doc_worry_partner_misbehaves')}</TableCell>
                  <TableCell>{t('federation.api_doc_safeguard_partner_misbehaves')}</TableCell>
                </TableRow>
                <TableRow key="global">
                  <TableCell>{t('federation.api_doc_worry_global_trust')}</TableCell>
                  <TableCell>{t('federation.api_doc_safeguard_global_trust')}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <Divider />

          <div>
            <h4 className="font-semibold text-base mb-1">{t('federation.api_doc_summary_title')}</h4>
            <blockquote className="border-l-4 border-primary pl-4 italic text-default-700">
              {t('federation.api_doc_summary_body')}
            </blockquote>
          </div>

          <Divider />

          <div className="text-default-500">
            <p>
              {t('federation.api_doc_technical_tabs_hint')}
            </p>
            <p className="mt-2">
              {t('federation.api_doc_full_manual')} <code>docs/FEDERATION_API_MANUAL.md</code>.
            </p>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 1: Authentication
// ─────────────────────────────────────────────────────────────────────────────

function AuthenticationTab() {
  const { t } = useTranslation('admin');
  return (
    <div className="space-y-6">
      {/* API Key Auth */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Shield size={20} className="text-primary" />
          <div>
            <h3 className="text-lg font-semibold">{t('federation.api_doc_api_key_auth')}</h3>
            <p className="text-sm text-default-500">{t('federation.api_doc_api_key_desc')}</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t('federation.api_doc_api_key_instructions')}
          </p>
          <CodeBlock>{`
GET /api/v1/federation/timebanks HTTP/1.1
Host: api.project-nexus.ie
Authorization: Bearer fed_live_abc123...
Content-Type: application/json
          `}</CodeBlock>
          <p className="text-sm text-default-500">
            {t('federation.api_doc_api_key_scopes')}
          </p>
        </CardBody>
      </Card>

      {/* HMAC-SHA256 Auth */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Shield size={20} className="text-warning" />
          <div>
            <h3 className="text-lg font-semibold">{t('federation.api_doc_hmac_auth')}</h3>
            <p className="text-sm text-default-500">{t('federation.api_doc_hmac_desc')}</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t('federation.api_doc_hmac_instructions')}
          </p>
          <p className="text-sm font-semibold text-default-700">{t('federation.api_doc_string_to_sign')}</p>
          <CodeBlock>{`
METHOD\n
URL\n
TIMESTAMP\n
BODY
          `}</CodeBlock>
          <p className="text-sm text-default-600">
            {t('federation.api_doc_string_to_sign_desc')}
          </p>
          <p className="text-sm font-semibold text-default-700">{t('federation.api_doc_required_headers')}</p>
          <Table aria-label={t('api_docs.aria_hmac_headers')} removeWrapper>
            <TableHeader>
              <TableColumn>{t('federation.api_doc_col_header')}</TableColumn>
              <TableColumn>{t('federation.api_doc_col_description')}</TableColumn>
            </TableHeader>
            <TableBody>
              <TableRow key="sig">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Federation-Signature</code></TableCell>
                <TableCell>{t('federation.api_doc_header_signature_desc')}</TableCell>
              </TableRow>
              <TableRow key="ts">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Federation-Timestamp</code></TableCell>
                <TableCell>{t('federation.api_doc_header_timestamp_desc')}</TableCell>
              </TableRow>
              <TableRow key="nonce">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Federation-Nonce</code></TableCell>
                <TableCell>{t('federation.api_doc_header_nonce_desc')}</TableCell>
              </TableRow>
              <TableRow key="platform-id">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Federation-Platform-Id</code></TableCell>
                <TableCell>{t('federation.api_doc_header_platform_id_desc')}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* JWT Bearer Auth */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Shield size={20} className="text-success" />
          <div>
            <h3 className="text-lg font-semibold">{t('federation.api_doc_jwt_auth')}</h3>
            <p className="text-sm text-default-500">{t('federation.api_doc_jwt_desc')}</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t('federation.api_doc_jwt_instructions')}
          </p>
          <CodeBlock>{`
POST /api/v1/federation/oauth/token HTTP/1.1
Host: api.project-nexus.ie
Content-Type: application/x-www-form-urlencoded
Authorization: Basic base64(client_id:client_secret)

grant_type=client_credentials&scope=members:read listings:read

// client_id = API key prefix (e.g. "fed_live_abc123")
// client_secret = full API key

// Response:
{
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
          `}</CodeBlock>
        </CardBody>
      </Card>

      {/* Rate Limits */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <AlertTriangle size={20} className="text-danger" />
          <div>
            <h3 className="text-lg font-semibold">{t('federation.api_doc_rate_limits')}</h3>
            <p className="text-sm text-default-500">{t('federation.api_doc_rate_limits_desc')}</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t('federation.api_doc_rate_limit_instructions')}
          </p>
          <Table aria-label={t('api_docs.aria_rate_limit_headers')} removeWrapper>
            <TableHeader>
              <TableColumn>{t('federation.api_doc_col_header')}</TableColumn>
              <TableColumn>{t('federation.api_doc_col_description')}</TableColumn>
            </TableHeader>
            <TableBody>
              <TableRow key="limit">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-RateLimit-Limit</code></TableCell>
                <TableCell>{t('federation.api_doc_rate_limit_limit_desc')}</TableCell>
              </TableRow>
              <TableRow key="remaining">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-RateLimit-Remaining</code></TableCell>
                <TableCell>{t('federation.api_doc_rate_limit_remaining_desc')}</TableCell>
              </TableRow>
              <TableRow key="reset">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-RateLimit-Reset</code></TableCell>
                <TableCell>{t('federation.api_doc_rate_limit_reset_desc')}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 2: Endpoints
// ─────────────────────────────────────────────────────────────────────────────

interface EndpointDef {
  method: 'GET' | 'POST';
  path: string;
  permission: string;
  description: string;
  params?: { name: string; type: string; required: boolean; description: string }[];
  response: string;
}

const ENDPOINTS: EndpointDef[] = [
  {
    method: 'POST',
    path: '/api/v1/federation/oauth/token',
    permission: 'none',
    description: 'Exchange API credentials for a JWT bearer token. Uses OAuth2 client_credentials grant. Authenticate with HTTP Basic auth where client_id is the API key prefix and client_secret is the full API key.',
    params: [
      { name: 'grant_type', type: 'string', required: true, description: 'Must be "client_credentials"' },
      { name: 'scope', type: 'string', required: false, description: 'Space-separated list of requested scopes (e.g. "members:read listings:read")' },
    ],
    response: `{
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "expires_in": 3600,
  "token_type": "Bearer"
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation',
    permission: 'none',
    description: 'Returns API version info and a list of available endpoints. Useful as a health check.',
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "api": "Federation API",
  "version": "1.0",
  "documentation": "/docs/api/federation",
  "endpoints": {
    "GET /api/v1/federation/timebanks": "List partner timebanks",
    "GET /api/v1/federation/members": "Search federated members",
    ...
  }
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/timebanks',
    permission: 'timebanks:read',
    description: 'List all partner timebanks with active partnerships. Returns name, location, member count, and partnership status.',
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "data": [
    {
      "id": 3,
      "name": "Greenville Community Exchange",
      "tagline": "Sharing time, building community",
      "location": { "city": "Springfield", "country": "US" },
      "member_count": 142,
      "partnership_status": "active",
      "partnership_since": "2025-06-15 10:30:00"
    }
  ],
  "count": 1
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/members',
    permission: 'members:read',
    description: 'Search federated members across partner timebanks. Supports full-text search, filtering by skills, location, and timebank.',
    params: [
      { name: 'q', type: 'string', required: false, description: 'Search query (name, username, skills)' },
      { name: 'timebank_id', type: 'integer', required: false, description: 'Filter by specific timebank ID' },
      { name: 'skills', type: 'string', required: false, description: 'Comma-separated skill filters' },
      { name: 'location', type: 'string', required: false, description: 'Location filter (partial match)' },
      { name: 'page', type: 'integer', required: false, description: 'Page number (default: 1)' },
      { name: 'per_page', type: 'integer', required: false, description: 'Results per page (default: 20, max: 100)' },
    ],
    response: `{
  "success": true,
  "data": [
    {
      "id": 45,
      "username": "jane_doe",
      "name": "Jane Doe",
      "avatar": "https://...",
      "bio": "Community organiser",
      "skills": ["gardening", "tutoring"],
      "location": "Cork",
      "timebank": { "id": 3, "name": "Greenville Community Exchange" },
      "service_reach": "regional",
      "joined": "2024-03-10 09:00:00"
    }
  ],
  "pagination": { "total": 142, "page": 1, "per_page": 20, "total_pages": 8, "has_more": true }
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/members/{id}',
    permission: 'members:read',
    description: 'Get detailed profile for a single federated member, including messaging and transaction preferences.',
    params: [
      { name: 'id', type: 'integer', required: true, description: 'Member ID (URL parameter)' },
    ],
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "data": {
    "id": 45,
    "username": "jane_doe",
    "name": "Jane Doe",
    "avatar": "https://...",
    "bio": "Community organiser",
    "skills": ["gardening", "tutoring"],
    "location": "Cork",
    "timebank": { "id": 3, "name": "Greenville Community Exchange" },
    "service_reach": "regional",
    "accepts_messages": true,
    "accepts_transactions": true,
    "joined": "2024-03-10 09:00:00"
  }
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/listings',
    permission: 'listings:read',
    description: 'Search federated listings (offers and requests) across partner timebanks.',
    params: [
      { name: 'q', type: 'string', required: false, description: 'Search query (title, description)' },
      { name: 'type', type: 'string', required: false, description: '"offer" or "request"' },
      { name: 'timebank_id', type: 'integer', required: false, description: 'Filter by specific timebank ID' },
      { name: 'category', type: 'integer', required: false, description: 'Category ID (integer)' },
      { name: 'page', type: 'integer', required: false, description: 'Page number (default: 1)' },
      { name: 'per_page', type: 'integer', required: false, description: 'Results per page (default: 20, max: 100)' },
    ],
    response: `{
  "success": true,
  "data": [
    {
      "id": 201,
      "title": "Guitar Lessons",
      "description": "30-minute beginner guitar sessions",
      "type": "offer",
      "category": "Music",
      "rate": "1.0",
      "owner": { "id": 45, "name": "Jane Doe", "avatar": "https://..." },
      "timebank": { "id": 3, "name": "Greenville Community Exchange" },
      "created_at": "2025-08-20 14:30:00"
    }
  ],
  "pagination": { "total": 89, "page": 1, "per_page": 20, "total_pages": 5, "has_more": true }
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/listings/{id}',
    permission: 'listings:read',
    description: 'Get detailed information for a single federated listing.',
    params: [
      { name: 'id', type: 'integer', required: true, description: 'Listing ID (URL parameter)' },
    ],
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "data": {
    "id": 201,
    "title": "Guitar Lessons",
    "description": "30-minute beginner guitar sessions",
    "type": "offer",
    "category": "Music",
    "rate": "1.0",
    "owner": {
      "id": 45,
      "name": "Jane Doe",
      "avatar": "https://...",
      "location": "Cork"
    },
    "timebank": { "id": 3, "name": "Greenville Community Exchange" },
    "created_at": "2025-08-20 14:30:00",
    "updated_at": "2025-09-01 10:00:00"
  }
}`,
  },
  {
    method: 'POST',
    path: '/api/v1/federation/messages',
    permission: 'messages:write',
    description: 'Send a federated message to a member in a partner timebank. The sender must belong to your tenant.',
    params: [
      { name: 'sender_id', type: 'integer', required: true, description: 'Sender user ID (must belong to your tenant)' },
      { name: 'recipient_id', type: 'integer', required: true, description: 'Recipient user ID' },
      { name: 'subject', type: 'string', required: true, description: 'Message subject' },
      { name: 'body', type: 'string', required: true, description: 'Message body text' },
    ],
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "message_id": 1234,
  "status": "sent"
}`,
  },
  {
    method: 'POST',
    path: '/api/v1/federation/transactions',
    permission: 'transactions:write',
    description: 'Create a time credit transfer between members of partner timebanks. Requires an active credit agreement between tenants and sufficient sender balance.',
    params: [
      { name: 'sender_id', type: 'integer', required: true, description: 'Sender user ID (must belong to your tenant)' },
      { name: 'recipient_id', type: 'integer', required: true, description: 'Recipient user ID' },
      { name: 'amount', type: 'integer', required: true, description: 'Amount in whole hours (1-100)' },
      { name: 'description', type: 'string', required: true, description: 'Transaction description' },
    ],
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "transaction_id": 567,
  "status": "completed",
  "amount": 2,
  "note": "Transaction completed successfully"
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/messages',
    permission: 'messages:read',
    description: 'Retrieve federated messages involving your tenant. Supports filtering by direction (inbound/outbound) and a since timestamp for incremental syncing.',
    params: [
      { name: 'since', type: 'string', required: false, description: 'ISO 8601 timestamp -- only return messages after this time' },
      { name: 'direction', type: 'string', required: false, description: '"inbound", "outbound", or "all" (default: "all")' },
      { name: 'page', type: 'integer', required: false, description: 'Page number (default: 1)' },
      { name: 'per_page', type: 'integer', required: false, description: 'Results per page (default: 20, max: 100)' },
    ],
    response: `{
  "success": true,
  "data": [
    {
      "id": 1234,
      "subject": "Time exchange request",
      "body": "Hi Jane, I saw your guitar lessons listing...",
      "sender": {
        "id": 12,
        "name": "John Smith",
        "tenant_id": 2,
        "tenant_name": "Hour Timebank"
      },
      "receiver": {
        "id": 45,
        "name": "Jane Doe",
        "tenant_id": 3,
        "tenant_name": "Greenville Community Exchange"
      },
      "is_read": false,
      "created_at": "2025-09-15 14:30:00"
    }
  ],
  "pagination": { "total": 24, "page": 1, "per_page": 20, "total_pages": 2, "has_more": true }
}`,
  },
  {
    method: 'POST',
    path: '/api/v1/federation/reviews',
    permission: 'reviews:write',
    description: 'Create a federated review for a member in a partner timebank. The reviewer must belong to your tenant and the reviewee must have federated reviews enabled.',
    params: [
      { name: 'reviewer_id', type: 'integer', required: true, description: 'Reviewer user ID (must belong to your tenant)' },
      { name: 'reviewee_id', type: 'integer', required: true, description: 'Reviewee user ID (must have federated reviews enabled)' },
      { name: 'rating', type: 'integer', required: true, description: 'Rating from 1 to 5' },
      { name: 'comment', type: 'string', required: false, description: 'Review text (max 5000 characters)' },
      { name: 'transaction_id', type: 'integer', required: false, description: 'Associated transaction ID (optional)' },
    ],
    response: `{
  "success": true,
  "data": {
    "id": 789,
    "reviewer_id": 12,
    "reviewee_id": 45,
    "rating": 5,
    "comment": "Excellent guitar lesson, very patient teacher!",
    "transaction_id": 567,
    "review_type": "federated",
    "status": "approved",
    "created_at": "2025-09-20 16:00:00"
  }
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/reviews',
    permission: 'reviews:read',
    description: 'Get federated reviews for a specific user. Only returns approved reviews where the user has federated reviews enabled.',
    params: [
      { name: 'user_id', type: 'integer', required: true, description: 'User ID to get reviews for' },
      { name: 'page', type: 'integer', required: false, description: 'Page number (default: 1)' },
      { name: 'per_page', type: 'integer', required: false, description: 'Results per page (default: 20, max: 100)' },
    ],
    response: `{
  "success": true,
  "data": [
    {
      "id": 789,
      "rating": 5,
      "comment": "Excellent guitar lesson, very patient teacher!",
      "review_type": "federated",
      "transaction_id": 567,
      "reviewer": {
        "id": 12,
        "name": "John Smith",
        "avatar": "https://...",
        "tenant_name": "Hour Timebank"
      },
      "created_at": "2025-09-20 16:00:00"
    }
  ],
  "pagination": { "total": 5, "page": 1, "per_page": 20, "total_pages": 1, "has_more": false }
}`,
  },
  {
    method: 'GET',
    path: '/api/v1/federation/transactions/{id}',
    permission: 'transactions:read',
    description: 'Get the status and details of a specific federated transaction. Your tenant must be either the sender or receiver tenant.',
    params: [
      { name: 'id', type: 'integer', required: true, description: 'Transaction ID (URL parameter)' },
    ],
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "data": {
    "id": 567,
    "amount": 2,
    "status": "completed",
    "description": "Guitar lesson - 2 hours",
    "sender": {
      "id": 12,
      "name": "John Smith",
      "tenant_id": 2,
      "tenant_name": "Hour Timebank"
    },
    "receiver": {
      "id": 45,
      "name": "Jane Doe",
      "tenant_id": 3,
      "tenant_name": "Greenville Community Exchange"
    },
    "created_at": "2025-09-15 14:30:00"
  }
}`,
  },
  {
    method: 'POST',
    path: '/api/v1/federation/webhooks/test',
    permission: 'none',
    description: 'Verify HMAC signature correctness for webhook payloads. Tests that your signing implementation produces valid signatures.',
    response: `{
  "success": true,
  "timestamp": "2026-03-28T12:00:00+00:00",
  "valid": true,
  "message": "Signature verified successfully",
  "platform": {
    "id": 1,
    "name": "Partner Platform"
  },
  "timestamp_age_seconds": 2
}`,
  },
];

function EndpointsTab() {
  const { t } = useTranslation('admin');
  return (
    <Accordion variant="bordered" selectionMode="multiple">
      {ENDPOINTS.map((ep, idx) => (
        <AccordionItem
          key={idx}
          aria-label={`${ep.method} ${ep.path}`}
          title={
            <div className="flex items-center gap-3">
              <MethodChip method={ep.method} />
              <code className="text-sm font-mono">{ep.path}</code>
              {ep.permission !== 'none' && (
                <Chip size="sm" variant="flat" color="warning" className="text-xs">
                  {ep.permission}
                </Chip>
              )}
            </div>
          }
        >
          <div className="space-y-4 pb-2">
            <p className="text-sm text-default-600">{ep.description}</p>

            {ep.params && ep.params.length > 0 && (
              <>
                <p className="text-sm font-semibold text-default-700">{t('federation.api_doc_parameters')}</p>
                <Table aria-label={t('api_docs.aria_parameters')} removeWrapper>
                  <TableHeader>
                    <TableColumn>{t('federation.api_doc_col_name')}</TableColumn>
                    <TableColumn>{t('federation.api_doc_col_type')}</TableColumn>
                    <TableColumn>{t('federation.api_doc_col_required')}</TableColumn>
                    <TableColumn>{t('federation.api_doc_col_description')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {ep.params.map((p) => (
                      <TableRow key={p.name}>
                        <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{p.name}</code></TableCell>
                        <TableCell><span className="text-sm text-default-500">{p.type}</span></TableCell>
                        <TableCell>
                          <Chip size="sm" variant="flat" color={p.required ? 'danger' : 'default'}>
                            {p.required ? t('federation.api_doc_yes') : t('federation.api_doc_no')}
                          </Chip>
                        </TableCell>
                        <TableCell><span className="text-sm text-default-600">{p.description}</span></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </>
            )}

            <p className="text-sm font-semibold text-default-700">{t('federation.api_doc_example_response')}</p>
            <CodeBlock>{ep.response}</CodeBlock>
          </div>
        </AccordionItem>
      ))}
    </Accordion>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 3: Examples
// ─────────────────────────────────────────────────────────────────────────────

const EXAMPLES = {
  apiKey: {
    curl: `# List federated members with API key auth
curl -X GET "https://api.project-nexus.ie/api/v1/federation/members?q=gardening&per_page=10" \\
  -H "Authorization: Bearer fed_live_abc123..." \\
  -H "Content-Type: application/json"`,
    js: `// List federated members with API key auth
const response = await fetch(
  'https://api.project-nexus.ie/api/v1/federation/members?q=gardening&per_page=10',
  {
    method: 'GET',
    headers: {
      'Authorization': 'Bearer fed_live_abc123...',
      'Content-Type': 'application/json',
    },
  }
);

const data = await response.json();
console.log(data.data); // Array of members`,
    python: `# List federated members with API key auth
import requests

response = requests.get(
    'https://api.project-nexus.ie/api/v1/federation/members',
    params={'q': 'gardening', 'per_page': 10},
    headers={
        'Authorization': 'Bearer fed_live_abc123...',
        'Content-Type': 'application/json',
    },
)

data = response.json()
print(data['data'])  # List of members`,
  },
  hmac: {
    curl: `# HMAC-signed request
TIMESTAMP=$(date +%s)
NONCE=$(uuidgen)
BODY=''  # Empty string for GET requests
STRING_TO_SIGN="GET\\n/api/v1/federation/timebanks\\n$TIMESTAMP\\n$BODY"
SIGNATURE=$(echo -n "$STRING_TO_SIGN" | openssl dgst -sha256 -hmac "your_api_secret" | cut -d' ' -f2)

curl -X GET "https://api.project-nexus.ie/api/v1/federation/timebanks" \\
  -H "Authorization: Bearer fed_live_abc123..." \\
  -H "X-Federation-Signature: $SIGNATURE" \\
  -H "X-Federation-Timestamp: $TIMESTAMP" \\
  -H "X-Federation-Nonce: $NONCE" \\
  -H "X-Federation-Platform-Id: fed_live_abc123" \\
  -H "Content-Type: application/json"`,
    js: `// HMAC-signed request
import crypto from 'crypto';

const apiKey = 'fed_live_abc123...';
const apiSecret = 'your_api_secret';
const platformId = 'fed_live_abc123';  // API key prefix
const timestamp = Math.floor(Date.now() / 1000).toString();
const nonce = crypto.randomUUID();
const body = '';  // Empty string for GET requests

const stringToSign = \`GET\\n/api/v1/federation/timebanks\\n\${timestamp}\\n\${body}\`;
const signature = crypto
  .createHmac('sha256', apiSecret)
  .update(stringToSign)
  .digest('hex');

const response = await fetch(
  'https://api.project-nexus.ie/api/v1/federation/timebanks',
  {
    method: 'GET',
    headers: {
      'Authorization': \`Bearer \${apiKey}\`,
      'X-Federation-Signature': signature,
      'X-Federation-Timestamp': timestamp,
      'X-Federation-Nonce': nonce,
      'X-Federation-Platform-Id': platformId,
      'Content-Type': 'application/json',
    },
  }
);`,
    python: `# HMAC-signed request
import hmac
import hashlib
import time
import uuid
import requests

api_key = 'fed_live_abc123...'
api_secret = 'your_api_secret'
platform_id = 'fed_live_abc123'  # API key prefix
timestamp = str(int(time.time()))
nonce = str(uuid.uuid4())
body = ''  # Empty string for GET requests

string_to_sign = f"GET\\n/api/v1/federation/timebanks\\n{timestamp}\\n{body}"
signature = hmac.new(
    api_secret.encode(), string_to_sign.encode(), hashlib.sha256
).hexdigest()

response = requests.get(
    'https://api.project-nexus.ie/api/v1/federation/timebanks',
    headers={
        'Authorization': f'Bearer {api_key}',
        'X-Federation-Signature': signature,
        'X-Federation-Timestamp': timestamp,
        'X-Federation-Nonce': nonce,
        'X-Federation-Platform-Id': platform_id,
        'Content-Type': 'application/json',
    },
)`,
  },
  message: {
    curl: `# Send a federated message
curl -X POST "https://api.project-nexus.ie/api/v1/federation/messages" \\
  -H "Authorization: Bearer fed_live_abc123..." \\
  -H "Content-Type: application/json" \\
  -d '{
    "sender_id": 12,
    "recipient_id": 45,
    "subject": "Time exchange request",
    "body": "Hi Jane, I saw your guitar lessons listing. Would you be available next Tuesday?"
  }'`,
    js: `// Send a federated message
const response = await fetch(
  'https://api.project-nexus.ie/api/v1/federation/messages',
  {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer fed_live_abc123...',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      sender_id: 12,
      recipient_id: 45,
      subject: 'Time exchange request',
      body: 'Hi Jane, I saw your guitar lessons listing. Would you be available next Tuesday?',
    }),
  }
);

const data = await response.json();
console.log(data.message_id); // 1234`,
    python: `# Send a federated message
import requests

response = requests.post(
    'https://api.project-nexus.ie/api/v1/federation/messages',
    headers={
        'Authorization': 'Bearer fed_live_abc123...',
        'Content-Type': 'application/json',
    },
    json={
        'sender_id': 12,
        'recipient_id': 45,
        'subject': 'Time exchange request',
        'body': 'Hi Jane, I saw your guitar lessons listing. Would you be available next Tuesday?',
    },
)

data = response.json()
print(data['message_id'])  # 1234`,
  },
};

type ExampleLang = 'curl' | 'js' | 'python';

function ExamplesTab() {
  const { t } = useTranslation('admin');
  const [lang, setLang] = useState<ExampleLang>('curl');

  const langLabels: Record<ExampleLang, string> = {
    curl: 'cURL',
    js: 'JavaScript',
    python: 'Python',
  };

  return (
    <div className="space-y-6">
      {/* Language switcher */}
      <Tabs
        selectedKey={lang}
        onSelectionChange={(key) => setLang(key as ExampleLang)}
        variant="bordered"
        size="sm"
      >
        {Object.entries(langLabels).map(([key, label]) => (
          <Tab key={key} title={label} />
        ))}
      </Tabs>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('federation.api_doc_example_api_key')}</h3>
        </CardHeader>
        <CardBody>
          <CodeBlock>{EXAMPLES.apiKey[lang]}</CodeBlock>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('federation.api_doc_example_hmac')}</h3>
        </CardHeader>
        <CardBody>
          <CodeBlock>{EXAMPLES.hmac[lang]}</CodeBlock>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('federation.api_doc_example_message')}</h3>
        </CardHeader>
        <CardBody>
          <CodeBlock>{EXAMPLES.message[lang]}</CodeBlock>
        </CardBody>
      </Card>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 4: Error Codes
// ─────────────────────────────────────────────────────────────────────────────

const ERROR_CODES = [
  { code: 400, nameKey: 'federation.api_doc_error_bad_request', descriptionKey: 'federation.api_doc_error_bad_request_desc' },
  { code: 401, nameKey: 'federation.api_doc_error_unauthorized', descriptionKey: 'federation.api_doc_error_unauthorized_desc' },
  { code: 403, nameKey: 'federation.api_doc_error_forbidden', descriptionKey: 'federation.api_doc_error_forbidden_desc' },
  { code: 404, nameKey: 'federation.api_doc_error_not_found', descriptionKey: 'federation.api_doc_error_not_found_desc' },
  { code: 409, nameKey: 'federation.api_doc_error_conflict', descriptionKey: 'federation.api_doc_error_conflict_desc' },
  { code: 429, nameKey: 'federation.api_doc_error_rate_limited', descriptionKey: 'federation.api_doc_error_rate_limited_desc' },
  { code: 500, nameKey: 'federation.api_doc_error_internal', descriptionKey: 'federation.api_doc_error_internal_desc' },
];

function ErrorCodesTab() {
  const { t } = useTranslation('admin');
  return (
    <div className="space-y-4">
      <p className="text-sm text-default-600">
        {t('federation.api_doc_error_format_intro')}
      </p>
      <CodeBlock>{`{
  "error": true,
  "code": "ERROR_CODE",
  "message": "Human-readable description of what went wrong",
  "timestamp": "2026-03-28T12:00:00+00:00"
}`}</CodeBlock>

      <Table aria-label={t('federation.api_doc_error_codes_aria')}>
        <TableHeader>
          <TableColumn>{t('federation.api_doc_col_code')}</TableColumn>
          <TableColumn>{t('federation.api_doc_col_name')}</TableColumn>
          <TableColumn>{t('federation.api_doc_col_description')}</TableColumn>
        </TableHeader>
        <TableBody>
          {ERROR_CODES.map((err) => (
            <TableRow key={err.code}>
              <TableCell>
                <Chip
                  size="sm"
                  variant="flat"
                  color={err.code < 500 ? 'warning' : 'danger'}
                  className="font-mono"
                >
                  {err.code}
                </Chip>
              </TableCell>
              <TableCell><span className="text-sm font-medium">{t(err.nameKey)}</span></TableCell>
              <TableCell><span className="text-sm text-default-600">{t(err.descriptionKey)}</span></TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 5: Webhooks
// ─────────────────────────────────────────────────────────────────────────────

const WEBHOOK_EVENTS = [
  { event: 'partnership.requested', descriptionKey: 'federation.api_doc_webhook_event_partnership_requested' },
  { event: 'partnership.approved', descriptionKey: 'federation.api_doc_webhook_event_partnership_approved' },
  { event: 'partnership.rejected', descriptionKey: 'federation.api_doc_webhook_event_partnership_rejected' },
  { event: 'partnership.terminated', descriptionKey: 'federation.api_doc_webhook_event_partnership_terminated' },
  { event: 'member.opted_in', descriptionKey: 'federation.api_doc_webhook_event_member_opted_in' },
  { event: 'member.opted_out', descriptionKey: 'federation.api_doc_webhook_event_member_opted_out' },
  { event: 'message.sent', descriptionKey: 'federation.api_doc_webhook_event_message_sent' },
  { event: 'message.received', descriptionKey: 'federation.api_doc_webhook_event_message_received' },
  { event: 'transaction.created', descriptionKey: 'federation.api_doc_webhook_event_transaction_created' },
  { event: 'transaction.completed', descriptionKey: 'federation.api_doc_webhook_event_transaction_completed' },
  { event: 'connection.requested', descriptionKey: 'federation.api_doc_webhook_event_connection_requested' },
  { event: 'connection.accepted', descriptionKey: 'federation.api_doc_webhook_event_connection_accepted' },
  { event: 'listing.shared', descriptionKey: 'federation.api_doc_webhook_event_listing_shared' },
];

function WebhooksTab() {
  const { t } = useTranslation('admin');
  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Webhook size={20} className="text-primary" />
          <div>
            <h3 className="text-lg font-semibold">{t('federation.api_doc_webhook_events')}</h3>
            <p className="text-sm text-default-500">{t('federation.api_doc_webhook_events_desc')}</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t('federation.api_doc_webhook_delivery_intro')}{' '}
            <a href="../webhooks" className="text-primary underline">{t('federation.api_docs_webhooks')}</a>
            {' '}{t('federation.api_doc_webhook_delivery_suffix')}
          </p>
          <Table aria-label={t('federation.api_doc_webhook_events_aria')} removeWrapper>
            <TableHeader>
              <TableColumn>{t('federation.api_doc_col_event')}</TableColumn>
              <TableColumn>{t('federation.api_doc_col_description')}</TableColumn>
            </TableHeader>
            <TableBody>
              {WEBHOOK_EVENTS.map((evt) => (
                <TableRow key={evt.event}>
                  <TableCell>
                    <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{evt.event}</code>
                  </TableCell>
                  <TableCell><span className="text-sm text-default-600">{t(evt.descriptionKey)}</span></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('federation.api_doc_payload_format')}</h3>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t('federation.api_doc_payload_format_desc')}
          </p>
          <CodeBlock>{`{
  "event": "message.received",
  "timestamp": "2025-09-15T14:30:00Z",
  "webhook_id": 42,
  "delivery_id": "d_abc123",
  "data": {
    "message_id": 1234,
    "sender": {
      "id": 45,
      "name": "Jane Doe",
      "timebank": { "id": 3, "name": "Greenville Community Exchange" }
    },
    "recipient": {
      "id": 12,
      "name": "John Smith",
      "timebank": { "id": 2, "name": "Hour Timebank" }
    },
    "subject": "Time exchange request",
    "preview": "Hi John, I saw your listing..."
  }
}`}</CodeBlock>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('federation.api_doc_signature_verification')}</h3>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t('federation.api_doc_signature_verification_desc')}{' '}
            <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Webhook-Signature</code>.
          </p>

          <Divider />

          <p className="text-sm font-semibold text-default-700">{t('federation.api_doc_node_verification_example')}</p>
          <CodeBlock>{`const crypto = require('crypto');

function verifyWebhookSignature(payload, signature, secret) {
  const expected = crypto
    .createHmac('sha256', secret)
    .update(payload, 'utf8')
    .digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expected)
  );
}

// In your webhook handler:
app.post('/webhooks/nexus', (req, res) => {
  const signature = req.headers['x-webhook-signature'];
  const isValid = verifyWebhookSignature(
    JSON.stringify(req.body),
    signature,
    process.env.WEBHOOK_SECRET
  );

  if (!isValid) {
    return res.status(401).json({ error: 'Invalid signature' });
  }

  // Process the event
  const { event, data } = req.body;
  console.log(\`Received \${event}\`, data);

  res.status(200).json({ received: true });
});`}</CodeBlock>

          <p className="text-sm font-semibold text-default-700">{t('federation.api_doc_python_verification_example')}</p>
          <CodeBlock>{`import hashlib
import hmac
from flask import Flask, request, jsonify

app = Flask(__name__)
WEBHOOK_SECRET = 'your_webhook_secret'

def verify_signature(payload: bytes, signature: str, secret: str) -> bool:
    expected = hmac.new(
        secret.encode(), payload, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(signature, expected)

@app.route('/webhooks/nexus', methods=['POST'])
def handle_webhook():
    signature = request.headers.get('X-Webhook-Signature')
    if not verify_signature(request.data, signature, WEBHOOK_SECRET):
        return jsonify({'error': 'Invalid signature'}), 401

    event = request.json
    print(f"Received {event['event']}", event['data'])
    return jsonify({'received': True}), 200`}</CodeBlock>
        </CardBody>
      </Card>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function ApiDocumentation() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.api_docs_title'));

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('federation.api_docs_title')}
        description={t('federation.api_docs_desc')}
      />

      <Tabs
        aria-label={t('api_docs.aria_api_documentation_sections')}
        variant="underlined"
        classNames={{
          tabList: 'gap-6',
          tab: 'h-12',
        }}
      >
        <Tab
          key="overview"
          title={
            <div className="flex items-center gap-2">
              <HelpCircle size={16} />
              <span>{t('federation.api_docs_overview')}</span>
            </div>
          }
        >
          <div className="pt-4">
            <OverviewTab />
          </div>
        </Tab>

        <Tab
          key="auth"
          title={
            <div className="flex items-center gap-2">
              <Shield size={16} />
              <span>{t('federation.api_docs_auth')}</span>
            </div>
          }
        >
          <div className="pt-4">
            <AuthenticationTab />
          </div>
        </Tab>

        <Tab
          key="endpoints"
          title={
            <div className="flex items-center gap-2">
              <BookOpen size={16} />
              <span>{t('federation.api_docs_endpoints')}</span>
            </div>
          }
        >
          <div className="pt-4">
            <EndpointsTab />
          </div>
        </Tab>

        <Tab
          key="examples"
          title={
            <div className="flex items-center gap-2">
              <Code size={16} />
              <span>{t('federation.api_docs_examples')}</span>
            </div>
          }
        >
          <div className="pt-4">
            <ExamplesTab />
          </div>
        </Tab>

        <Tab
          key="errors"
          title={
            <div className="flex items-center gap-2">
              <AlertTriangle size={16} />
              <span>{t('federation.api_docs_errors')}</span>
            </div>
          }
        >
          <div className="pt-4">
            <ErrorCodesTab />
          </div>
        </Tab>

        <Tab
          key="webhooks"
          title={
            <div className="flex items-center gap-2">
              <Webhook size={16} />
              <span>{t('federation.api_docs_webhooks')}</span>
            </div>
          }
        >
          <div className="pt-4">
            <WebhooksTab />
          </div>
        </Tab>
      </Tabs>
    </div>
  );
}

export default ApiDocumentation;
