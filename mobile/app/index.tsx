// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View, ActivityIndicator } from 'react-native';

/**
 * Entry point — renders a loading spinner while the root layout's
 * auth check determines where to redirect.
 */
export default function Index() {
  return (
    <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
      <ActivityIndicator size="large" color="#006FEE" />
    </View>
  );
}
