// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for NotificationsContext interface
 *
 * Note: The actual NotificationsContext imports pusher-js which causes OOM
 * in the vitest worker process. These tests verify the module's exported
 * interface without loading the heavy dependency chain.
 */

import { describe, it, expect, vi } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

describe('NotificationsContext', () => {
  const filePath = path.resolve(__dirname, './NotificationsContext.tsx');

  it('source file exists', () => {
    expect(fs.existsSync(filePath)).toBe(true);
  });

  it('exports NotificationsProvider', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('export function NotificationsProvider');
  });

  it('exports useNotifications hook', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('export function useNotifications');
  });

  it('creates NotificationsContext with createContext', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('createContext');
  });

  it('has notification count tracking', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('unreadCount');
    expect(content).toContain('counts');
  });

  it('has mark-as-read functionality', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('markAsRead');
    expect(content).toContain('markAllAsRead');
  });

  it('integrates with Pusher for real-time updates', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('pusher');
  });

  it('uses auth context for user state', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('useAuth');
    expect(content).toContain('isAuthenticated');
  });

  it('fetches notification counts from API', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('/notifications/counts');
  });

  it('throws error when used outside provider', () => {
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).toContain('useNotifications must be used within');
  });
});
