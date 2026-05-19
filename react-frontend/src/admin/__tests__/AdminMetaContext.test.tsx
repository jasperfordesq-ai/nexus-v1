// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
  AdminMetaProvider,
  AdminMetaTags,
  useAdminPageMeta,
} from '../AdminMetaContext';

vi.mock('@/components/seo', () => ({
  PageMeta: (props: {
    title?: string;
    description?: string;
    noIndex?: boolean;
  }) => (
    <output
      aria-label="admin-meta"
      data-title={props.title}
      data-description={props.description}
      data-noindex={String(props.noIndex)}
    />
  ),
}));

function ChildPage() {
  useAdminPageMeta({
    title: 'Members',
    description: 'Member administration',
  });
  return <div>Members page</div>;
}

function TitleOnlyChildPage() {
  useAdminPageMeta({ title: 'Settings' });
  return <div>Settings page</div>;
}

describe('AdminMetaContext', () => {
  it('keeps admin pages noindexed while child pages override the title', async () => {
    render(
      <AdminMetaProvider
        defaultMeta={{
          title: 'Admin',
          description: 'Private admin tools',
        }}
      >
        <AdminMetaTags />
        <ChildPage />
      </AdminMetaProvider>,
    );

    const meta = screen.getByLabelText('admin-meta');
    await waitFor(() => expect(meta).toHaveAttribute('data-title', 'Members'));
    expect(meta).toHaveAttribute('data-description', 'Member administration');
    expect(meta).toHaveAttribute('data-noindex', 'true');
  });

  it('resets to default metadata when the child page unmounts', async () => {
    const { rerender } = render(
      <AdminMetaProvider
        defaultMeta={{
          title: 'Admin',
          description: 'Private admin tools',
        }}
      >
        <AdminMetaTags />
        <ChildPage />
      </AdminMetaProvider>,
    );

    const meta = screen.getByLabelText('admin-meta');
    await waitFor(() => expect(meta).toHaveAttribute('data-title', 'Members'));

    rerender(
      <AdminMetaProvider
        defaultMeta={{
          title: 'Admin',
          description: 'Private admin tools',
        }}
      >
        <AdminMetaTags />
      </AdminMetaProvider>,
    );

    await waitFor(() => expect(meta).toHaveAttribute('data-title', 'Admin'));
    expect(meta).toHaveAttribute('data-description', 'Private admin tools');
    expect(meta).toHaveAttribute('data-noindex', 'true');
  });

  it('preserves default description when a child page only sets a title', async () => {
    render(
      <AdminMetaProvider
        defaultMeta={{
          title: 'Admin',
          description: 'Private admin tools',
        }}
      >
        <AdminMetaTags />
        <TitleOnlyChildPage />
      </AdminMetaProvider>,
    );

    const meta = screen.getByLabelText('admin-meta');
    await waitFor(() => expect(meta).toHaveAttribute('data-title', 'Settings'));
    expect(meta).toHaveAttribute('data-description', 'Private admin tools');
    expect(meta).toHaveAttribute('data-noindex', 'true');
  });
});
