// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';
import * as Linking from 'expo-linking';

const mockUseApi = jest.fn();
const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  router: { push: (...args: unknown[]) => mockPush(...args) },
}));

jest.mock('expo-linking', () => ({
  openURL: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    text: '#111827',
    textSecondary: '#4b5563',
    textMuted: '#6b7280',
    info: '#2563eb',
    success: '#22c55e',
    error: '#ef4444',
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);
jest.mock('@/components/ui/AppTopBar', () => {
  const { Text } = require('react-native');
  return function MockAppTopBar({ title }: { title: string }) {
    return <Text>{title}</Text>;
  };
});
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'common:buttons.retry': 'Retry',
        'resources:title': 'Resources',
        'resources:subtitle': 'Browse shared files and knowledge base articles.',
        'resources:tabs.resources': 'Files',
        'resources:tabs.kb': 'Knowledge',
        'resources:searchPlaceholder': 'Search resources',
        'resources:allCategories': 'All',
        'resources:download': 'Open resource',
        'resources:readArticle': 'Read article',
        'resources:downloads': opts ? `${String(opts.count)} downloads` : 'downloads',
        'resources:emptyTitle': 'Nothing found',
        'resources:emptySubtitle': 'Try another search or category.',
        'resources:errorTitle': 'Could not load resources',
        'resources:categoryCount': opts ? `${String(opts.count)} items` : 'items',
      };
      return map[key] ?? key;
    },
  }),
}));

import ResourcesScreen from './resources';

describe('ResourcesScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    let call = 0;
    const resourcesState = {
      data: {
        items: [
          {
            id: 1,
            title: 'Member handbook',
            description: 'A useful PDF.',
            file_url: 'https://example.test/handbook.pdf',
            file_path: 'handbook.pdf',
            downloads: 4,
            category: { id: 10, name: 'Guides' },
          },
        ],
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const categoriesState = {
      data: [{ id: 10, name: 'Guides', resource_count: 1 }],
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const kbState = {
      data: {
        items: [{ id: 7, title: 'Using time credits', content_preview: 'How credits work.', category_name: 'Basics' }],
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    mockUseApi.mockImplementation(() => {
      call += 1;
      const index = ((call - 1) % 3) + 1;
      if (index === 1) return resourcesState;
      if (index === 2) return categoriesState;
      return kbState;
    });
  });

  it('renders resources and opens resource downloads', () => {
    const { getAllByText, getByText } = render(<ResourcesScreen />);

    expect(getAllByText('Resources').length).toBeGreaterThan(0);
    expect(getByText('Member handbook')).toBeTruthy();
    expect(getAllByText('Guides').length).toBeGreaterThan(0);

    fireEvent.press(getByText('Open resource'));
    expect(Linking.openURL).toHaveBeenCalledWith('https://example.test/handbook.pdf');
  });

  it('renders knowledge base articles and routes to detail', () => {
    const { getByText } = render(<ResourcesScreen />);

    fireEvent.press(getByText('Knowledge'));
    expect(getByText('Using time credits')).toBeTruthy();

    fireEvent.press(getByText('Read article'));
    expect(mockPush).toHaveBeenCalledWith({ pathname: '/(modals)/kb-article', params: { id: '7' } });
  });
});
