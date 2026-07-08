// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ReactNode } from 'react';
import { MenuProvider } from '@/contexts/MenuContext';

export default function TenantPublicProviders({ children }: { children: ReactNode }) {
  return (
    <MenuProvider>
      {children}
    </MenuProvider>
  );
}
