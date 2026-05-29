// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('./endorsements', () => {
  const { Text } = require('react-native');
  return function MockEndorsementsScreen() {
    return <Text>Skills & Endorsements</Text>;
  };
});

import SkillsScreen from './skills';

describe('SkillsScreen', () => {
  it('renders the native skills management surface', () => {
    const { getByText } = render(<SkillsScreen />);

    expect(getByText('Skills & Endorsements')).toBeTruthy();
  });
});
