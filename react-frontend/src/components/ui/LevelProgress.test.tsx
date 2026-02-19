// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { LevelProgress } from './LevelProgress';

describe('LevelProgress', () => {
  it('renders level and XP info in default mode', () => {
    render(<LevelProgress currentXP={150} requiredXP={300} level={5} />);
    expect(screen.getByText('Level 5')).toBeInTheDocument();
    expect(screen.getByText('150 / 300 XP')).toBeInTheDocument();
  });

  it('hides level text in compact mode', () => {
    render(<LevelProgress currentXP={150} requiredXP={300} level={5} compact />);
    expect(screen.queryByText('Level 5')).not.toBeInTheDocument();
  });

  it('shows XP and percentage in compact mode', () => {
    render(<LevelProgress currentXP={150} requiredXP={300} level={5} compact />);
    expect(screen.getByText('150 / 300 XP')).toBeInTheDocument();
    expect(screen.getByText('50%')).toBeInTheDocument();
  });

  it('calculates percentage correctly', () => {
    render(<LevelProgress currentXP={75} requiredXP={100} level={3} compact />);
    expect(screen.getByText('75%')).toBeInTheDocument();
  });

  it('caps percentage at 100%', () => {
    render(<LevelProgress currentXP={500} requiredXP={100} level={10} compact />);
    expect(screen.getByText('100%')).toBeInTheDocument();
  });

  it('handles zero requiredXP gracefully', () => {
    render(<LevelProgress currentXP={0} requiredXP={0} level={1} compact />);
    expect(screen.getByText('0%')).toBeInTheDocument();
  });
});
