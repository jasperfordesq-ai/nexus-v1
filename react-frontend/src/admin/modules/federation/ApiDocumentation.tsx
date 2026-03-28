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
import {
  BookOpen,
  Shield,
  Code,
  AlertTriangle,
  Webhook,
} from 'lucide-react';
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
// Tab 1: Authentication
// ─────────────────────────────────────────────────────────────────────────────

function AuthenticationTab() {
  return (
    <div className="space-y-6">
      {/* API Key Auth */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Shield size={20} className="text-primary" />
          <div>
            <h3 className="text-lg font-semibold">API Key Authentication</h3>
            <p className="text-sm text-default-500">Simplest method -- suitable for server-to-server calls</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            Include your API key in the <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">Authorization</code> header
            as a Bearer token. Keys can be created and managed on the{' '}
            <a href="../api-keys" className="text-primary underline">API Keys</a> page.
          </p>
          <CodeBlock>{`
GET /api/v1/federation/timebanks HTTP/1.1
Host: api.project-nexus.ie
Authorization: Bearer fed_live_abc123...
Content-Type: application/json
          `}</CodeBlock>
          <p className="text-sm text-default-500">
            Each key has scoped permissions (e.g. <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">timebanks:read</code>,{' '}
            <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">messages:write</code>). Requests requiring a scope your key
            does not have will return <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">403 Forbidden</code>.
          </p>
        </CardBody>
      </Card>

      {/* HMAC-SHA256 Auth */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Shield size={20} className="text-warning" />
          <div>
            <h3 className="text-lg font-semibold">HMAC-SHA256 Signature</h3>
            <p className="text-sm text-default-500">Recommended for production -- verifies request integrity</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            Sign every request using your API secret. The signature proves the request has not been tampered with in transit.
          </p>
          <p className="text-sm font-semibold text-default-700">String to sign:</p>
          <CodeBlock>{`
METHOD\n
URL\n
TIMESTAMP\n
SHA256(body)
          `}</CodeBlock>
          <p className="text-sm text-default-600">
            Concatenate the HTTP method, full URL path, Unix timestamp, and SHA-256 hex digest of the request body
            (use empty string for GET requests), each separated by a newline character.
          </p>
          <p className="text-sm font-semibold text-default-700">Required headers:</p>
          <Table aria-label="HMAC headers" removeWrapper>
            <TableHeader>
              <TableColumn>Header</TableColumn>
              <TableColumn>Description</TableColumn>
            </TableHeader>
            <TableBody>
              <TableRow key="sig">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Federation-Signature</code></TableCell>
                <TableCell>HMAC-SHA256 hex digest of the string-to-sign using your API secret</TableCell>
              </TableRow>
              <TableRow key="ts">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Federation-Timestamp</code></TableCell>
                <TableCell>Unix timestamp (seconds). Requests older than 5 minutes are rejected.</TableCell>
              </TableRow>
              <TableRow key="nonce">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Federation-Nonce</code></TableCell>
                <TableCell>Unique random string per request. Prevents replay attacks.</TableCell>
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
            <h3 className="text-lg font-semibold">JWT Bearer Token</h3>
            <p className="text-sm text-default-500">Token-based auth for session-like integrations</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            Exchange your API key for a short-lived JWT via the token endpoint. Include it as a Bearer token.
            JWTs expire after <strong>24 hours</strong>.
          </p>
          <CodeBlock>{`
POST /api/v1/federation/token HTTP/1.1
Host: api.project-nexus.ie
Content-Type: application/json

{
  "api_key": "fed_live_abc123...",
  "api_secret": "your_secret_here"
}

// Response:
{
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "expires_in": 86400,
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
            <h3 className="text-lg font-semibold">Rate Limits</h3>
            <p className="text-sm text-default-500">Throttling headers returned on every response</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            All API responses include rate-limit headers. When the limit is exceeded, responses return <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">429 Too Many Requests</code>.
          </p>
          <Table aria-label="Rate limit headers" removeWrapper>
            <TableHeader>
              <TableColumn>Header</TableColumn>
              <TableColumn>Description</TableColumn>
            </TableHeader>
            <TableBody>
              <TableRow key="limit">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-RateLimit-Limit</code></TableCell>
                <TableCell>Maximum requests per window (default: 60/min)</TableCell>
              </TableRow>
              <TableRow key="remaining">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-RateLimit-Remaining</code></TableCell>
                <TableCell>Requests remaining in the current window</TableCell>
              </TableRow>
              <TableRow key="reset">
                <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-RateLimit-Reset</code></TableCell>
                <TableCell>Unix timestamp when the window resets</TableCell>
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
    method: 'GET',
    path: '/api/v1/federation',
    permission: 'none',
    description: 'Returns API version info and a list of available endpoints. Useful as a health check.',
    response: `{
  "success": true,
  "data": {
    "api": "Federation API",
    "version": "1.0",
    "documentation": "/docs/api/federation",
    "endpoints": {
      "GET /api/v1/federation/timebanks": "List partner timebanks",
      "GET /api/v1/federation/members": "Search federated members",
      ...
    }
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
  "data": {
    "data": [
      {
        "id": 3,
        "name": "Dublin Time Exchange",
        "tagline": "Sharing time, building community",
        "location": { "city": "Dublin", "country": "IE" },
        "member_count": 142,
        "partnership_status": "active",
        "partnership_since": "2025-06-15 10:30:00"
      }
    ],
    "count": 1
  }
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
      "timebank": { "id": 3, "name": "Dublin Time Exchange" },
      "service_reach": "regional",
      "joined": "2024-03-10 09:00:00"
    }
  ],
  "meta": { "total": 142, "page": 1, "per_page": 20, "total_pages": 8 }
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
  "data": {
    "data": {
      "id": 45,
      "username": "jane_doe",
      "name": "Jane Doe",
      "avatar": "https://...",
      "bio": "Community organiser",
      "skills": ["gardening", "tutoring"],
      "location": "Cork",
      "timebank": { "id": 3, "name": "Dublin Time Exchange" },
      "service_reach": "regional",
      "accepts_messages": true,
      "accepts_transactions": true,
      "joined": "2024-03-10 09:00:00"
    }
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
      { name: 'category', type: 'string', required: false, description: 'Category filter (exact match)' },
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
      "timebank": { "id": 3, "name": "Dublin Time Exchange" },
      "created_at": "2025-08-20 14:30:00"
    }
  ],
  "meta": { "total": 89, "page": 1, "per_page": 20, "total_pages": 5 }
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
  "data": {
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
      "timebank": { "id": 3, "name": "Dublin Time Exchange" },
      "created_at": "2025-08-20 14:30:00",
      "updated_at": "2025-09-01 10:00:00"
    }
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
  "data": {
    "message_id": 1234,
    "status": "sent"
  }
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
      { name: 'amount', type: 'number', required: true, description: 'Amount in hours (0-100)' },
      { name: 'description', type: 'string', required: true, description: 'Transaction description' },
    ],
    response: `{
  "success": true,
  "data": {
    "transaction_id": 567,
    "status": "completed",
    "amount": 1.5,
    "sender_new_balance": 8.5,
    "recipient_new_balance": 11.5
  }
}`,
  },
  {
    method: 'POST',
    path: '/api/v1/federation/webhooks/test',
    permission: 'webhooks:write',
    description: 'Send a test webhook delivery to verify your endpoint configuration. Returns the response code and time.',
    response: `{
  "success": true,
  "data": {
    "response_code": 200,
    "response_time_ms": 145
  }
}`,
  },
];

function EndpointsTab() {
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
                <p className="text-sm font-semibold text-default-700">Parameters</p>
                <Table aria-label="Parameters" removeWrapper>
                  <TableHeader>
                    <TableColumn>Name</TableColumn>
                    <TableColumn>Type</TableColumn>
                    <TableColumn>Required</TableColumn>
                    <TableColumn>Description</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {ep.params.map((p) => (
                      <TableRow key={p.name}>
                        <TableCell><code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{p.name}</code></TableCell>
                        <TableCell><span className="text-sm text-default-500">{p.type}</span></TableCell>
                        <TableCell>
                          <Chip size="sm" variant="flat" color={p.required ? 'danger' : 'default'}>
                            {p.required ? 'Yes' : 'No'}
                          </Chip>
                        </TableCell>
                        <TableCell><span className="text-sm text-default-600">{p.description}</span></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </>
            )}

            <p className="text-sm font-semibold text-default-700">Example Response</p>
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
BODY=''
BODY_HASH=$(echo -n "$BODY" | sha256sum | cut -d' ' -f1)
STRING_TO_SIGN="GET\\n/api/v1/federation/timebanks\\n$TIMESTAMP\\n$BODY_HASH"
SIGNATURE=$(echo -n "$STRING_TO_SIGN" | openssl dgst -sha256 -hmac "your_api_secret" | cut -d' ' -f2)

curl -X GET "https://api.project-nexus.ie/api/v1/federation/timebanks" \\
  -H "Authorization: Bearer fed_live_abc123..." \\
  -H "X-Federation-Signature: $SIGNATURE" \\
  -H "X-Federation-Timestamp: $TIMESTAMP" \\
  -H "X-Federation-Nonce: $NONCE" \\
  -H "Content-Type: application/json"`,
    js: `// HMAC-signed request
import crypto from 'crypto';

const apiKey = 'fed_live_abc123...';
const apiSecret = 'your_api_secret';
const timestamp = Math.floor(Date.now() / 1000).toString();
const nonce = crypto.randomUUID();
const body = '';
const bodyHash = crypto.createHash('sha256').update(body).digest('hex');

const stringToSign = \`GET\\n/api/v1/federation/timebanks\\n\${timestamp}\\n\${bodyHash}\`;
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
      'Content-Type': 'application/json',
    },
  }
);`,
    python: `# HMAC-signed request
import hashlib
import hmac
import time
import uuid
import requests

api_key = 'fed_live_abc123...'
api_secret = 'your_api_secret'
timestamp = str(int(time.time()))
nonce = str(uuid.uuid4())
body = ''
body_hash = hashlib.sha256(body.encode()).hexdigest()

string_to_sign = f"GET\\n/api/v1/federation/timebanks\\n{timestamp}\\n{body_hash}"
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
console.log(data.data.message_id); // 1234`,
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
print(data['data']['message_id'])  # 1234`,
  },
};

type ExampleLang = 'curl' | 'js' | 'python';

function ExamplesTab() {
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
          <h3 className="text-lg font-semibold">API Key Authentication + List Members</h3>
        </CardHeader>
        <CardBody>
          <CodeBlock>{EXAMPLES.apiKey[lang]}</CodeBlock>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">HMAC-Signed Request</h3>
        </CardHeader>
        <CardBody>
          <CodeBlock>{EXAMPLES.hmac[lang]}</CodeBlock>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Sending a Message</h3>
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
  { code: 400, name: 'Bad Request', description: 'The request body is malformed or missing required fields. Check the validation error message for details.' },
  { code: 401, name: 'Unauthorized', description: 'Missing, invalid, or expired authentication credentials. Ensure your API key or JWT is valid.' },
  { code: 403, name: 'Forbidden', description: 'Your API key does not have the required permission scope, or the target resource is restricted.' },
  { code: 404, name: 'Not Found', description: 'The requested resource does not exist, or the member/listing is not accessible via federation.' },
  { code: 409, name: 'Conflict', description: 'The request conflicts with the current state (e.g. duplicate transaction, already opted-in).' },
  { code: 429, name: 'Rate Limited', description: 'Too many requests. Check X-RateLimit-Reset header for when to retry.' },
  { code: 500, name: 'Internal Server Error', description: 'An unexpected error occurred. Contact support if the issue persists.' },
];

function ErrorCodesTab() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-default-600">
        All error responses follow a consistent JSON format:
      </p>
      <CodeBlock>{`{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable description of what went wrong",
    "status": 400
  }
}`}</CodeBlock>

      <Table aria-label="Error codes">
        <TableHeader>
          <TableColumn>Code</TableColumn>
          <TableColumn>Name</TableColumn>
          <TableColumn>Description</TableColumn>
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
              <TableCell><span className="text-sm font-medium">{err.name}</span></TableCell>
              <TableCell><span className="text-sm text-default-600">{err.description}</span></TableCell>
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
  { event: 'partnership.requested', description: 'A new partnership request was sent to your timebank' },
  { event: 'partnership.approved', description: 'A partnership request was approved' },
  { event: 'partnership.rejected', description: 'A partnership request was rejected' },
  { event: 'partnership.terminated', description: 'An active partnership was terminated' },
  { event: 'member.opted_in', description: 'A member opted in to federation' },
  { event: 'member.opted_out', description: 'A member opted out of federation' },
  { event: 'message.sent', description: 'A federated message was sent from your timebank' },
  { event: 'message.received', description: 'A federated message was received by a member in your timebank' },
  { event: 'transaction.created', description: 'A time credit transaction was initiated' },
  { event: 'transaction.completed', description: 'A time credit transaction was completed' },
  { event: 'connection.requested', description: 'A cross-timebank connection request was sent' },
  { event: 'connection.accepted', description: 'A cross-timebank connection was accepted' },
  { event: 'listing.shared', description: 'A listing was shared to the federation network' },
];

function WebhooksTab() {
  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Webhook size={20} className="text-primary" />
          <div>
            <h3 className="text-lg font-semibold">Webhook Events</h3>
            <p className="text-sm text-default-500">Real-time notifications for federation activity</p>
          </div>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            Webhooks are sent as <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">POST</code> requests
            to your configured HTTPS endpoint. Configure webhooks on the{' '}
            <a href="../webhooks" className="text-primary underline">Webhooks</a> page.
          </p>
          <Table aria-label="Webhook events" removeWrapper>
            <TableHeader>
              <TableColumn>Event</TableColumn>
              <TableColumn>Description</TableColumn>
            </TableHeader>
            <TableBody>
              {WEBHOOK_EVENTS.map((evt) => (
                <TableRow key={evt.event}>
                  <TableCell>
                    <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{evt.event}</code>
                  </TableCell>
                  <TableCell><span className="text-sm text-default-600">{evt.description}</span></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Payload Format</h3>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            Every webhook delivery includes these standard fields:
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
      "timebank": { "id": 3, "name": "Dublin Time Exchange" }
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
          <h3 className="text-lg font-semibold">Signature Verification</h3>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            Every webhook delivery is signed with your webhook secret using HMAC-SHA256.
            Verify the signature to ensure the payload is authentic and has not been tampered with.
            The signature is sent in the <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">X-Webhook-Signature</code> header.
          </p>

          <Divider />

          <p className="text-sm font-semibold text-default-700">Node.js verification example:</p>
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

          <p className="text-sm font-semibold text-default-700">Python verification example:</p>
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
    signature = request.headers.get('X-Webhook-Signature', '')
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
  usePageTitle(t('federation.api_docs_title', 'Federation API Documentation'));

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('federation.api_docs_title', 'API Documentation')}
        description={t(
          'federation.api_docs_desc',
          'Developer reference for integrating with the Project NEXUS Federation API'
        )}
      />

      <Tabs
        aria-label="API Documentation sections"
        variant="underlined"
        classNames={{
          tabList: 'gap-6',
          tab: 'h-12',
        }}
      >
        <Tab
          key="auth"
          title={
            <div className="flex items-center gap-2">
              <Shield size={16} />
              <span>{t('federation.api_docs_auth', 'Authentication')}</span>
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
              <span>{t('federation.api_docs_endpoints', 'Endpoints')}</span>
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
              <span>{t('federation.api_docs_examples', 'Examples')}</span>
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
              <span>{t('federation.api_docs_errors', 'Error Codes')}</span>
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
              <span>{t('federation.api_docs_webhooks', 'Webhooks')}</span>
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
