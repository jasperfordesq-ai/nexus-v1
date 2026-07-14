#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const routeSourcePath = path.join(root, 'routes', 'api.php');
const openApiPath = path.join(root, 'openapi.json');
const issues = [];

function addIssue(message) {
  issues.push(message);
}

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function operationKey(method, apiPath) {
  return `${method.toUpperCase()} ${apiPath}`;
}

function parseLaravelRoutes(source) {
  const routes = new Map();
  const routePattern = /Route::(get|post|put|patch|delete|options|head)\(\s*['"]([^'"]+)['"]\s*,\s*\[\s*\\?([A-Za-z0-9_\\]+)::class\s*,\s*['"]([^'"]+)['"]\s*\]/g;
  let match;

  while ((match = routePattern.exec(source)) !== null) {
    const [, method, routePath, controller, action] = match;
    const apiPath = `/api${routePath}`;
    routes.set(operationKey(method, apiPath), `${controller.replace(/^\\/, '')}@${action}`);
  }

  return routes;
}

function getOperation(spec, method, apiPath) {
  return spec.paths?.[apiPath]?.[method.toLowerCase()];
}

function hasBearerSecurity(operation) {
  return Array.isArray(operation.security)
    && operation.security.some((requirement) => Object.hasOwn(requirement, 'bearerAuth'));
}

function checkStatusResponses(key, operation, statuses) {
  for (const status of statuses) {
    if (!operation.responses?.[status]) {
      addIssue(`${key}: OpenAPI response ${status} is required`);
    }
  }
}

if (!fs.existsSync(routeSourcePath) || !fs.existsSync(openApiPath)) {
  console.error('OpenAPI route contract check requires routes/api.php and openapi.json.');
  process.exit(1);
}

let spec;
try {
  spec = JSON.parse(read('openapi.json'));
} catch (error) {
  console.error(`openapi.json is not valid JSON: ${error.message}`);
  process.exit(1);
}

const sourceRoutes = parseLaravelRoutes(read('routes/api.php'));
const protectedContracts = [
  {
    method: 'get',
    path: '/api/v2/messages/{message}/attachments/{attachment}',
    operationId: 'Messages_getAttachment',
    action: 'App\\Http\\Controllers\\Api\\MessageMediaController@attachment',
  },
  {
    method: 'get',
    path: '/api/v2/messages/{message}/voice',
    operationId: 'Messages_getVoice',
    action: 'App\\Http\\Controllers\\Api\\MessageMediaController@voice',
  },
];

for (const contract of protectedContracts) {
  const key = operationKey(contract.method, contract.path);
  const sourceAction = sourceRoutes.get(key);
  if (sourceAction !== contract.action) {
    addIssue(`${key}: routes/api.php must map to ${contract.action}; found ${sourceAction ?? 'no route'}`);
  }

  const operation = getOperation(spec, contract.method, contract.path);
  if (!operation) {
    addIssue(`${key}: missing from openapi.json`);
    continue;
  }

  if (operation.operationId !== contract.operationId) {
    addIssue(`${key}: operationId must be ${contract.operationId}`);
  }
  if (operation['x-controller-action'] !== contract.action) {
    addIssue(`${key}: x-controller-action must match routes/api.php`);
  }
  if (!hasBearerSecurity(operation)) {
    addIssue(`${key}: private message media must explicitly require bearerAuth`);
  }

  checkStatusResponses(key, operation, ['200', '401', '403', '404']);

  const mediaSchema = operation.responses?.['200']?.content?.['application/octet-stream']?.schema;
  if (mediaSchema?.$ref !== '#/components/schemas/MessageMediaBinary') {
    addIssue(`${key}: response must use the MessageMediaBinary schema`);
  }

  const documentedHeaders = operation.responses?.['200']?.headers ?? {};
  for (const header of [
    'Cache-Control',
    'Pragma',
    'X-Content-Type-Options',
    'Content-Security-Policy',
    'Cross-Origin-Resource-Policy',
  ]) {
    if (!documentedHeaders[header]) {
      addIssue(`${key}: private-media response header ${header} is not documented`);
    }
  }
}

{
  const method = 'post';
  const apiPath = '/api/v2/messages/voice';
  const key = operationKey(method, apiPath);
  const expectedAction = 'App\\Http\\Controllers\\Api\\MessagesController@sendVoice';
  const operation = getOperation(spec, method, apiPath);

  if (sourceRoutes.get(key) !== expectedAction) {
    addIssue(`${key}: the one-step voice-send route must map to ${expectedAction}`);
  }
  if (!operation) {
    addIssue(`${key}: missing from openapi.json`);
  } else {
    if (operation.operationId !== 'Messages_sendVoice') {
      addIssue(`${key}: operationId must be Messages_sendVoice`);
    }
    if (!hasBearerSecurity(operation)) {
      addIssue(`${key}: voice send must explicitly require bearerAuth`);
    }
    checkStatusResponses(key, operation, ['201', '400', '401', '403', '404', '422', '429']);
    const fields = operation.requestBody?.content?.['multipart/form-data']?.schema?.properties ?? {};
    if (!fields.recipient_id || fields.voice_message?.format !== 'binary') {
      addIssue(`${key}: multipart recipient_id and binary voice_message fields are required`);
    }
  }
}

{
  const method = 'post';
  const apiPath = '/api/csp-report';
  const key = operationKey(method, apiPath);
  const expectedAction = 'App\\Http\\Controllers\\Api\\SecurityReportController@csp';
  const operation = getOperation(spec, method, apiPath);

  if (sourceRoutes.get(key) !== expectedAction) {
    addIssue(`${key}: routes/api.php must map to ${expectedAction}`);
  }
  if (!operation) {
    addIssue(`${key}: missing from openapi.json`);
  } else {
    if (operation.operationId !== 'SecurityReport_collectCspViolation') {
      addIssue(`${key}: operationId must be SecurityReport_collectCspViolation`);
    }
    if (!Array.isArray(operation.security) || operation.security.length !== 0) {
      addIssue(`${key}: browser CSP reporting must explicitly override global authentication with security: []`);
    }
    checkStatusResponses(key, operation, ['204', '413']);
    if (!operation.requestBody?.content?.['application/csp-report']
      || !operation.requestBody?.content?.['application/reports+json']) {
      addIssue(`${key}: legacy and Reporting API CSP media types must both be documented`);
    }
  }
}

{
  const method = 'post';
  const apiPath = '/api/v2/messages/upload-voice';
  const key = operationKey(method, apiPath);
  if (sourceRoutes.has(key)) {
    addIssue(`${key}: obsolete two-step voice upload route must remain unrouted`);
  }
  if (getOperation(spec, method, apiPath)) {
    addIssue(`${key}: obsolete two-step voice upload route must not be published in openapi.json`);
  }
}

const operationIds = new Map();
for (const [apiPath, pathItem] of Object.entries(spec.paths ?? {})) {
  for (const method of ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace']) {
    const operationId = pathItem?.[method]?.operationId;
    if (!operationId) continue;
    const locations = operationIds.get(operationId) ?? [];
    locations.push(operationKey(method, apiPath));
    operationIds.set(operationId, locations);
  }
}
for (const [operationId, locations] of operationIds) {
  if (locations.length > 1) {
    addIssue(`operationId ${operationId} is duplicated by ${locations.join(', ')}`);
  }
}

if (issues.length > 0) {
  console.error('OpenAPI route contract check failed:');
  for (const issue of issues.sort()) {
    console.error(`- ${issue}`);
  }
  process.exit(1);
}

console.log('OpenAPI route contract OK (private message media, one-step voice send, and CSP reporting verified).');
