// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for logger utility
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const queueSentryExceptionMock = vi.fn();
const queueSentryMessageMock = vi.fn();

vi.mock('@/lib/telemetryQueue', () => ({
  queueSentryException: queueSentryExceptionMock,
  queueSentryMessage: queueSentryMessageMock,
}));

// We need to control import.meta.env.DEV, so we test the functions indirectly
describe('logger', () => {
  let consoleErrorSpy: ReturnType<typeof vi.spyOn>;
  let consoleWarnSpy: ReturnType<typeof vi.spyOn>;
  let consoleInfoSpy: ReturnType<typeof vi.spyOn>;
  let consoleLogSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    vi.resetModules();
    vi.stubEnv('DEV', true);
    consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => {});
    consoleLogSpy = vi.spyOn(console, 'log').mockImplementation(() => {});
    queueSentryExceptionMock.mockClear();
    queueSentryMessageMock.mockClear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllEnvs();
    vi.resetModules();
  });

  describe('logError', () => {
    it('logs error with prefix in dev mode', async () => {
      // Vitest runs in DEV mode by default
      const { logError } = await import('./logger');
      logError('test error', new Error('fail'));
      expect(consoleErrorSpy).toHaveBeenCalledWith('[Error] test error', expect.any(Error));
    });

    it('logs error with empty string when no error arg passed', async () => {
      const { logError } = await import('./logger');
      logError('test message');
      expect(consoleErrorSpy).toHaveBeenCalledWith('[Error] test message', '');
    });

    it('queues Error instances in production without console logging', async () => {
      vi.resetModules();
      vi.stubEnv('DEV', false);
      const error = new Error('fail');
      const { logError } = await import('./logger');

      logError('production error', error);

      expect(consoleErrorSpy).not.toHaveBeenCalled();
      expect(queueSentryExceptionMock).toHaveBeenCalledWith(error, {
        source: 'logger',
        message: 'production error',
      });
      expect(queueSentryMessageMock).not.toHaveBeenCalled();
    });

    it('queues non-Error production failures as messages', async () => {
      vi.resetModules();
      vi.stubEnv('DEV', false);
      const { logError } = await import('./logger');

      logError('production message', { code: 'NOPE' });

      expect(consoleErrorSpy).not.toHaveBeenCalled();
      expect(queueSentryExceptionMock).not.toHaveBeenCalled();
      expect(queueSentryMessageMock).toHaveBeenCalledWith('production message', 'error', {
        detail: { code: 'NOPE' },
      });
    });
  });

  describe('logWarn', () => {
    it('logs warning with prefix', async () => {
      const { logWarn } = await import('./logger');
      logWarn('test warn', { foo: 'bar' });
      expect(consoleWarnSpy).toHaveBeenCalledWith('[Warn] test warn', { foo: 'bar' });
    });

    it('logs warning with empty string when no data arg', async () => {
      const { logWarn } = await import('./logger');
      logWarn('warn message');
      expect(consoleWarnSpy).toHaveBeenCalledWith('[Warn] warn message', '');
    });
  });

  describe('logInfo', () => {
    it('logs info with prefix', async () => {
      const { logInfo } = await import('./logger');
      logInfo('info message', 42);
      expect(consoleInfoSpy).toHaveBeenCalledWith('[Info] info message', 42);
    });
  });

  describe('logDebug', () => {
    it('logs debug with prefix', async () => {
      const { logDebug } = await import('./logger');
      logDebug('debug message', [1, 2, 3]);
      expect(consoleLogSpy).toHaveBeenCalledWith('[Debug] debug message', [1, 2, 3]);
    });
  });

  describe('logger object', () => {
    it('exports logger object with all methods', async () => {
      const { logger } = await import('./logger');
      expect(logger.error).toBeDefined();
      expect(logger.warn).toBeDefined();
      expect(logger.info).toBeDefined();
      expect(logger.debug).toBeDefined();
    });
  });
});
