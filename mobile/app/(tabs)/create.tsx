// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { router, type Href } from 'expo-router';

export default function CreateTabFallback() {
  useEffect(() => {
    router.replace('/(modals)/quick-create' as Href);
  }, []);

  return null;
}
