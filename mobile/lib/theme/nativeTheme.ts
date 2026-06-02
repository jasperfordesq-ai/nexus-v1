// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Appearance } from 'react-native';
import { Uniwind } from 'uniwind';

export function configureNativeTheme() {
  Appearance.setColorScheme('dark');
  Uniwind.setTheme('dark');
}
