// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({ bg: '#ffffff' }),
}));

import Index from './index';

describe('Index', () => {
  it('renders the HeroUI Native loading spinner', () => {
    const { toJSON } = render(<Index />);
    expect(toJSON()).toBeTruthy();
  });
});
