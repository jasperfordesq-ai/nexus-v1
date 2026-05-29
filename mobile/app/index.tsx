// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View } from 'react-native';
import { Spinner } from 'heroui-native';
import { useTheme } from '@/lib/hooks/useTheme';

/**
 * Entry point — renders a loading spinner while the root layout's
 * auth check determines where to redirect.
 */
export default function Index() {
  const theme = useTheme();

  return (
    <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: theme.bg }}>
      <Spinner size="lg" />
    </View>
  );
}
