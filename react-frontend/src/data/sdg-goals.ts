// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * UN Sustainable Development Goals — static data mirroring src/Helpers/SDG.php
 *
 * The 17 SDGs are a fixed global standard and don't change per tenant.
 */

export interface SdgGoal {
  id: number;
  label: string;
  color: string;
  icon: string;
}

export const SDG_GOALS: SdgGoal[] = [
  { id: 1, label: 'No Poverty', color: '#E5243B', icon: '🏘️' },
  { id: 2, label: 'Zero Hunger', color: '#DDA63A', icon: '🍲' },
  { id: 3, label: 'Good Health', color: '#4C9F38', icon: '🩺' },
  { id: 4, label: 'Quality Education', color: '#C5192D', icon: '🎓' },
  { id: 5, label: 'Gender Equality', color: '#FF3A21', icon: '⚖️' },
  { id: 6, label: 'Clean Water', color: '#26BDE2', icon: '💧' },
  { id: 7, label: 'Clean Energy', color: '#FCC30B', icon: '⚡' },
  { id: 8, label: 'Decent Work', color: '#A21942', icon: '📈' },
  { id: 9, label: 'Innovation', color: '#FD6925', icon: '🏗️' },
  { id: 10, label: 'Reduced Inequalities', color: '#DD1367', icon: '🤝' },
  { id: 11, label: 'Sustainable Cities', color: '#FD9D24', icon: '🏙️' },
  { id: 12, label: 'Responsible Consumption', color: '#BF8B2E', icon: '♻️' },
  { id: 13, label: 'Climate Action', color: '#3F7E44', icon: '🌍' },
  { id: 14, label: 'Life Below Water', color: '#0A97D9', icon: '🐟' },
  { id: 15, label: 'Life on Land', color: '#56C02B', icon: '🌳' },
  { id: 16, label: 'Peace & Justice', color: '#00689D', icon: '🕊️' },
  { id: 17, label: 'Partnerships', color: '#19486A', icon: '🔗' },
];
