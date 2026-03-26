// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('expo-device', () => ({ isDevice: true }));

import * as Device from 'expo-device';
import {
  isEmulator,
  isProductionBuild,
  checkDeviceIntegrity,
  logIntegrityWarnings,
} from './integrity';

describe('integrity', () => {
  describe('isEmulator', () => {
    it('returns false when Device.isDevice is true', () => {
      expect(Device.isDevice).toBe(true);
      expect(isEmulator()).toBe(false);
    });

    it('returns true when Device.isDevice is false', () => {
      const original = Device.isDevice;
      Object.defineProperty(Device, 'isDevice', { value: false, writable: true });
      try {
        expect(isEmulator()).toBe(true);
      } finally {
        Object.defineProperty(Device, 'isDevice', { value: original, writable: true });
      }
    });
  });

  describe('isProductionBuild', () => {
    it('returns false in test environment', () => {
      expect(isProductionBuild()).toBe(false);
    });
  });

  describe('checkDeviceIntegrity', () => {
    it('returns safe=true on real device', () => {
      // Default mock has isDevice=true and NODE_ENV=test, so no warnings
      const result = checkDeviceIntegrity();
      expect(result.safe).toBe(true);
      expect(result.warnings).toHaveLength(0);
    });

    it('returns safe=false for emulator in production', () => {
      const originalDevice = Device.isDevice;
      const originalEnv = process.env.NODE_ENV;
      Object.defineProperty(Device, 'isDevice', { value: false, writable: true });
      // @ts-expect-error — overriding readonly for test
      process.env.NODE_ENV = 'production';
      try {
        const result = checkDeviceIntegrity();
        expect(result.safe).toBe(false);
        expect(result.warnings).toContain('Running on emulator in production');
      } finally {
        Object.defineProperty(Device, 'isDevice', { value: originalDevice, writable: true });
        // @ts-expect-error — restoring readonly for test
        process.env.NODE_ENV = originalEnv;
      }
    });
  });

  describe('logIntegrityWarnings', () => {
    it('does nothing when no warnings', () => {
      const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
      logIntegrityWarnings();
      expect(warnSpy).not.toHaveBeenCalled();
      warnSpy.mockRestore();
    });
  });
});
