// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const fs = require('fs');
const path = require('path');
const { withAndroidManifest, withDangerousMod } = require('@expo/config-plugins');

module.exports = function withAndroidNetworkSecurity(config) {
  config = withAndroidManifest(config, (modConfig) => {
    const application = modConfig.modResults.manifest.application?.[0];
    if (!application) throw new Error('Android application manifest node is missing');
    application.$ = application.$ || {};
    application.$['android:networkSecurityConfig'] = '@xml/network_security_config';
    application.$['android:usesCleartextTraffic'] = 'false';
    return modConfig;
  });

  return withDangerousMod(config, ['android', (modConfig) => {
    const source = path.join(modConfig.modRequest.projectRoot, 'android-network-security-config.xml');
    const targetDirectory = path.join(modConfig.modRequest.platformProjectRoot, 'app', 'src', 'main', 'res', 'xml');
    if (!fs.existsSync(source)) throw new Error(`Missing network security config: ${source}`);
    fs.mkdirSync(targetDirectory, { recursive: true });
    fs.copyFileSync(source, path.join(targetDirectory, 'network_security_config.xml'));
    return modConfig;
  }]);
};
