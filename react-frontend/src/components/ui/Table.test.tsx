// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

import {
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  type TableProps,
} from './Table';

function ExampleTable(props: TableProps) {
  return (
    <Table aria-label="Example table" {...props}>
      <TableHeader>
        <TableColumn id="name">Name</TableColumn>
        <TableColumn id="status">Status</TableColumn>
      </TableHeader>
      <TableBody>
        <TableRow id="alice">
          <TableCell>Alice</TableCell>
          <TableCell>Active</TableCell>
        </TableRow>
        <TableRow id="bob">
          <TableCell>Bob</TableCell>
          <TableCell>Inactive</TableCell>
        </TableRow>
      </TableBody>
    </Table>
  );
}

describe('Table', () => {
  it('marks the first static column as a row header when none is provided', () => {
    render(<ExampleTable />);

    expect(screen.getByRole('grid')).toBeTruthy();
    expect(screen.getByRole('rowheader', { name: 'Alice' })).toBeTruthy();
  });

  it('removes the scroll wrapper and its v3 wrapper surface when requested', () => {
    const { rerender } = render(
      <ExampleTable classNames={{ wrapper: 'custom-wrapper' }} />
    );

    expect(document.querySelector('[data-slot="table-scroll-container"]')).toHaveClass(
      'custom-wrapper'
    );

    rerender(
      <ExampleTable
        classNames={{ wrapper: 'custom-wrapper' }}
        radius="lg"
        removeWrapper
        shadow="md"
      />
    );

    expect(document.querySelector('[data-slot="table-scroll-container"]')).toBeNull();
    expect(screen.getByRole('grid').closest('[data-slot="table"]')).toHaveClass(
      'overflow-x-auto',
      '!rounded-none',
      '!bg-transparent',
      '!p-0',
      '!shadow-none'
    );
    expect(screen.getByRole('grid').closest('[data-slot="table"]')).not.toHaveClass(
      '!rounded-lg',
      '!shadow-md'
    );
  });

  it('maps visual compatibility props and every classNames slot', () => {
    render(
      <ExampleTable
        className="custom-root"
        classNames={{
          base: 'custom-base',
          wrapper: 'custom-wrapper',
          table: 'custom-table',
          thead: 'custom-thead',
          tbody: 'custom-tbody',
          tr: 'custom-tr',
          th: 'custom-th',
          td: 'custom-td',
        }}
        color="warning"
        fullWidth={false}
        layout="fixed"
        radius="lg"
        shadow="md"
      />
    );

    const grid = screen.getByRole('grid');
    const root = grid.closest('[data-slot="table"]');
    const header = document.querySelector('[data-slot="table-header"]');
    const body = document.querySelector('[data-slot="table-body"]');
    const column = screen.getByRole('columnheader', { name: 'Name' });
    const cell = screen.getByRole('gridcell', { name: 'Active' });

    expect(root).toHaveClass(
      'custom-root',
      'custom-base',
      '!w-fit',
      'max-w-full',
      '!rounded-lg',
      '!shadow-md',
      '[&_[data-selected=true]_[data-slot=table-cell]]:!bg-warning-soft'
    );
    expect(root).toHaveAttribute('data-color', 'warning');
    expect(document.querySelector('[data-slot="table-scroll-container"]')).toHaveClass(
      'custom-wrapper'
    );
    expect(grid).toHaveClass('!w-auto', 'table-fixed', 'custom-table');
    expect(header).toHaveClass('custom-thead');
    expect(body).toHaveClass('custom-tbody');
    expect(screen.getByRole('row', { name: 'Alice' })).toHaveClass('custom-tr');
    expect(column).toHaveClass('custom-th');
    expect(cell).toHaveClass('custom-td');
  });

  it('applies striping to alternate body rows', () => {
    render(<ExampleTable isStriped />);

    const firstRow = screen.getByRole('row', { name: 'Alice' });
    const secondRow = screen.getByRole('row', { name: 'Bob' });

    expect(firstRow).toHaveClass(
      'even:[&:not(:hover):not([data-hovered=true]):not([data-selected=true])_[data-slot=table-cell]]:bg-surface-secondary'
    );
    expect(secondRow).toHaveClass(
      'even:[&:not(:hover):not([data-hovered=true]):not([data-selected=true])_[data-slot=table-cell]]:bg-surface-secondary'
    );
    expect(firstRow.matches(':nth-child(even)')).toBe(false);
    expect(secondRow.matches(':nth-child(even)')).toBe(true);
  });

  it('uses the retained compact density for body cells', () => {
    render(<ExampleTable isCompact />);

    expect(screen.getByRole('rowheader', { name: 'Alice' })).toHaveClass('!py-1');
    expect(screen.getByRole('gridcell', { name: 'Active' })).toHaveClass('!py-1');
    expect(screen.getByRole('columnheader', { name: 'Name' })).not.toHaveClass('!py-1');
  });

  it('keeps hidden and sticky headers accessible while applying v3 positioning', () => {
    render(<ExampleTable hideHeader isHeaderSticky />);

    expect(document.querySelector('[data-slot="table-header"]')).toHaveClass(
      'sr-only',
      'sticky',
      'top-0',
      'z-20',
      '[&>tr]:shadow-sm'
    );
    expect(screen.getByRole('columnheader', { name: 'Name' })).toBeTruthy();
  });

  it('maps explicit full-width and automatic-layout behavior to every v3 container', () => {
    render(<ExampleTable fullWidth layout="auto" />);

    expect(screen.getByRole('grid').closest('[data-slot="table"]')).toHaveClass('!w-full');
    expect(document.querySelector('[data-slot="table-scroll-container"]')).toHaveClass(
      '!w-full'
    );
    expect(screen.getByRole('grid')).toHaveClass('!w-full', 'table-auto');
  });

  it('allows an explicitly intrinsic-width table without the v3 full-width defaults', () => {
    render(<ExampleTable fullWidth={false} />);

    expect(screen.getByRole('grid').closest('[data-slot="table"]')).toHaveClass(
      '!w-fit',
      'max-w-full'
    );
    expect(document.querySelector('[data-slot="table-scroll-container"]')).toHaveClass(
      '!w-auto',
      'max-w-full'
    );
    expect(screen.getByRole('grid')).toHaveClass('!w-auto');
  });

  it('places top and bottom content inside the scroll wrapper by default', () => {
    render(
      <ExampleTable
        bottomContent={<div data-testid="bottom-content">Bottom</div>}
        topContent={<div data-testid="top-content">Top</div>}
      />
    );

    const wrapper = document.querySelector('[data-slot="table-scroll-container"]');

    expect(wrapper?.contains(screen.getByTestId('top-content'))).toBe(true);
    expect(wrapper?.contains(screen.getByTestId('bottom-content'))).toBe(true);
    expect(screen.getByTestId('bottom-content').closest('[data-slot="table-footer"]')).toBeTruthy();
  });

  it('places top and bottom content outside the scroll wrapper when requested', () => {
    render(
      <ExampleTable
        bottomContent={<div data-testid="bottom-content">Bottom</div>}
        bottomContentPlacement="outside"
        topContent={<div data-testid="top-content">Top</div>}
        topContentPlacement="outside"
      />
    );

    const root = screen.getByRole('grid').closest('[data-slot="table"]');
    const wrapper = document.querySelector('[data-slot="table-scroll-container"]');

    expect(root?.contains(screen.getByTestId('top-content'))).toBe(true);
    expect(root?.contains(screen.getByTestId('bottom-content'))).toBe(true);
    expect(wrapper?.contains(screen.getByTestId('top-content'))).toBe(false);
    expect(wrapper?.contains(screen.getByTestId('bottom-content'))).toBe(false);
    expect(screen.getByTestId('bottom-content').closest('[data-slot="table-footer"]')).toBeTruthy();
  });

  it('retains numeric zero as valid bottom content', () => {
    render(<ExampleTable bottomContent={0} />);

    expect(screen.getByText('0').closest('[data-slot="table-footer"]')).toBeTruthy();
  });

  it('suppresses table navigation keys when keyboard navigation is disabled', () => {
    const onKeyDown = vi.fn();

    render(
      <div onKeyDown={onKeyDown}>
        <ExampleTable isKeyboardNavigationDisabled />
      </div>
    );

    const grid = screen.getByRole('grid');

    expect(grid).toHaveAttribute('data-keyboard-navigation-disabled', 'true');
    fireEvent.keyDown(grid, { key: 'ArrowDown' });
    expect(onKeyDown).not.toHaveBeenCalled();

    fireEvent.keyDown(grid, { key: 'Enter' });
    expect(onKeyDown).toHaveBeenCalledTimes(1);
  });
});
