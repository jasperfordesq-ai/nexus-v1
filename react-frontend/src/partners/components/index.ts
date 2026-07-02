// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks panel shared primitives.
 *
 * The panel deliberately reuses the broker panel's presentational
 * primitives (they are pure — all strings arrive via props, no broker
 * i18n or state coupling) so both panels share one design language.
 * If a third panel ever appears, extract these to a shared location.
 */

export {
  BrokerPageShell as PartnersPageShell,
  BrokerStatCard,
  type BrokerStatColor,
  BrokerEmptyState,
  BrokerSkeleton,
  BrokerStatusChip,
  brokerStatusColor,
  BrokerSparkline,
  useCountUp,
} from '@/broker/components';
