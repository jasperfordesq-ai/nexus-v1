// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenantPath: (path: string) => `/test${path}`,
  })),
}));

import { PartnerTimebankGuidance } from '../PartnerTimebankGuidance';

describe('PartnerTimebankGuidance', () => {
  it('explains where the protocol API guide fits and links to related setup pages', () => {
    render(
      <MemoryRouter>
        <PartnerTimebankGuidance page="apiDocs" />
      </MemoryRouter>,
    );

    expect(screen.getByText('Use this guide to explain the protocol stack')).toBeInTheDocument();
    expect(screen.getByText('Where this page fits')).toBeInTheDocument();
    expect(screen.getByText('Recommended order')).toBeInTheDocument();
    expect(screen.getByText('Partner timebank admin')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Related pages' }));

    expect(screen.getByRole('link', { name: 'External Protocol Partners' })).toHaveAttribute(
      'href',
      '/test/admin/federation/external-partners',
    );
    expect(screen.getByRole('link', { name: 'Inbound API Partners' })).toHaveAttribute(
      'href',
      '/test/admin/api-partners',
    );
  });
});
