// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { Table, TableBody, TableCell, TableColumn, TableHeader, TableRow } from './Table';

describe('Table', () => {
  it('marks the first static column as a row header when none is provided', () => {
    render(
      <Table aria-label="Example table">
        <TableHeader>
          <TableColumn>Name</TableColumn>
          <TableColumn>Status</TableColumn>
        </TableHeader>
        <TableBody>
          <TableRow>
            <TableCell>Alice</TableCell>
            <TableCell>Active</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    );

    expect(screen.getByRole('grid')).toBeTruthy();
    expect(screen.getByRole('rowheader', { name: 'Alice' })).toBeTruthy();
  });
});
