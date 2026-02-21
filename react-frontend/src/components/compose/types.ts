// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Types for the Universal Compose Hub
 */

import type { LucideIcon } from 'lucide-react';
import type { TenantFeatures, TenantModules } from '@/types';

export type ComposeTab = 'post' | 'poll' | 'listing' | 'event' | 'goal';

export type ComposeGate =
  | { type: 'feature'; key: keyof TenantFeatures }
  | { type: 'module'; key: keyof TenantModules };

export interface ComposeTabConfig {
  key: ComposeTab;
  label: string;
  icon: LucideIcon;
  gate?: ComposeGate;
}

export interface ComposeHubProps {
  isOpen: boolean;
  onClose: () => void;
  defaultTab?: ComposeTab;
  onSuccess?: (type: ComposeTab, id?: number) => void;
  groupId?: number;
}

export interface TabSubmitProps {
  onSuccess: (type: ComposeTab, id?: number) => void;
  onClose: () => void;
  groupId: number | null;
}
