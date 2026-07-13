// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';

function read(relativePath: string): string {
  return fs.readFileSync(path.join(__dirname, relativePath), 'utf8');
}

describe('mobile user-visible infrastructure translations', () => {
  it('keeps API transport fallbacks behind i18n keys', () => {
    const source = read('api/client.ts');

    expect(source).toContain("i18n.t('common:errors.timeout')");
    expect(source).toContain("i18n.t('common:errors.network')");
    expect(source).toContain("i18n.t('common:errors.unauthorized')");
    expect(source).toContain("i18n.t('common:errors.requestFailedWithStatus'");
    expect(source).not.toMatch(/new ApiResponseError\(\s*(?:0|401),\s*['"`]/);
    expect(source).not.toMatch(/extractErrorMessage\([\s\S]*?,\s*`Request failed with status/);
  });

  it('keeps authentication display fallbacks behind i18n keys', () => {
    const source = read('api/auth.ts');

    expect(source).toContain("i18n.t('auth:errors.unableToSignIn')");
    expect(source).toContain("i18n.t('common:labels.member')");
    expect(source).not.toContain("throw new Error('Auth response did not contain a token')");
  });

  it('uses a translated Android notification-channel name', () => {
    const source = read('notifications.ts');

    expect(source).toContain("name: i18n.t('notifications:title')");
    expect(source).not.toContain("name: 'default'");
  });
});
