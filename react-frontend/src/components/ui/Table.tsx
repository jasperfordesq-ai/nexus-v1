// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Children,
  cloneElement,
  Fragment,
  isValidElement,
  type ComponentPropsWithoutRef,
  type ReactNode,
} from 'react';
import { Table as HeroUITable } from '@heroui/react/table';

type HeroUITableProps = ComponentPropsWithoutRef<typeof HeroUITable>;
type HeroUITableContentProps = ComponentPropsWithoutRef<typeof HeroUITable.Content>;
type HeroUITableHeaderProps = ComponentPropsWithoutRef<typeof HeroUITable.Header>;
type HeroUITableColumnProps = ComponentPropsWithoutRef<typeof HeroUITable.Column>;
type HeroUITableBodyProps = ComponentPropsWithoutRef<typeof HeroUITable.Body>;
type HeroUITablerowProps = ComponentPropsWithoutRef<typeof HeroUITable.Row>;
type HeroUITableCellProps = ComponentPropsWithoutRef<typeof HeroUITable.Cell>;

export type TableProps = Omit<HeroUITableProps, 'children' | 'className' | 'variant'> & {
  'aria-label'?: string;
  bottomContent?: ReactNode;
  bottomContentPlacement?: 'inside' | 'outside';
  children?: ReactNode;
  className?: string;
  classNames?: {
    base?: string;
    wrapper?: string;
    table?: string;
    thead?: string;
    tbody?: string;
    tr?: string;
    th?: string;
    td?: string;
  };
  color?: string;
  defaultSelectedKeys?: HeroUITableContentProps['defaultSelectedKeys'];
  disabledBehavior?: HeroUITableContentProps['disabledBehavior'];
  disabledKeys?: HeroUITableContentProps['disabledKeys'];
  disallowEmptySelection?: HeroUITableContentProps['disallowEmptySelection'];
  fullWidth?: boolean;
  hideHeader?: boolean;
  isCompact?: boolean;
  isHeaderSticky?: boolean;
  isKeyboardNavigationDisabled?: boolean;
  isStriped?: boolean;
  layout?: string;
  onCellAction?: unknown;
  onRowAction?: HeroUITableContentProps['onRowAction'];
  onSelectionChange?: HeroUITableContentProps['onSelectionChange'];
  onSortChange?: HeroUITableContentProps['onSortChange'];
  radius?: string;
  removeWrapper?: boolean;
  selectedKeys?: HeroUITableContentProps['selectedKeys'];
  selectionBehavior?: HeroUITableContentProps['selectionBehavior'];
  selectionMode?: HeroUITableContentProps['selectionMode'];
  shadow?: string;
  sortDescriptor?: HeroUITableContentProps['sortDescriptor'];
  topContent?: ReactNode;
  topContentPlacement?: 'inside' | 'outside';
  variant?: 'default' | 'primary' | 'secondary' | 'flat' | string;
};

export type TableHeaderProps = HeroUITableHeaderProps;
export type TableColumnProps = Omit<HeroUITableColumnProps, 'align' | 'className' | 'scope' | 'title'> & {
  align?: 'center' | 'end' | 'left' | 'right' | 'start' | string;
  className?: string;
  scope?: string;
  title?: string;
};
export type TableBodyProps<T extends object = object> = Omit<HeroUITableBodyProps, 'children' | 'isLoading' | 'items' | 'renderEmptyState'> & {
  children?: ReactNode | ((item: T) => ReactNode);
  emptyContent?: ReactNode;
  isLoading?: boolean;
  items?: Iterable<T>;
  loadingContent?: ReactNode;
  loadingState?: 'idle' | 'loading' | 'loadingMore' | 'sorting' | 'error' | 'filtering';
  renderEmptyState?: HeroUITableBodyProps['renderEmptyState'];
};
export type TableRowProps = HeroUITablerowProps;
export type TableCellProps = Omit<HeroUITableCellProps, 'className' | 'title'> & {
  className?: string;
  title?: string;
};
export type Selection = HeroUITableContentProps['selectedKeys'];
export type SortDescriptor = HeroUITableContentProps['sortDescriptor'];

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function mapVariant(variant?: TableProps['variant']): HeroUITableProps['variant'] {
  return variant === 'flat' || variant === 'secondary' ? 'secondary' : 'primary';
}

export function Table({
  'aria-label': ariaLabel,
  bottomContent,
  bottomContentPlacement: _bottomContentPlacement,
  children,
  className,
  classNames,
  color: _color,
  defaultSelectedKeys,
  disabledBehavior,
  disabledKeys,
  disallowEmptySelection,
  fullWidth: _fullWidth,
  hideHeader: _hideHeader,
  isCompact: _isCompact,
  isHeaderSticky: _isHeaderSticky,
  isKeyboardNavigationDisabled: _isKeyboardNavigationDisabled,
  isStriped: _isStriped,
  layout: _layout,
  onCellAction: _onCellAction,
  onRowAction,
  onSelectionChange,
  onSortChange,
  radius: _radius,
  removeWrapper: _removeWrapper,
  selectedKeys,
  selectionBehavior,
  selectionMode,
  shadow: _shadow,
  sortDescriptor,
  topContent,
  topContentPlacement: _topContentPlacement,
  variant,
  ...props
}: TableProps) {
  return (
    <HeroUITable
      className={combineClasses(classNames?.base, className)}
      variant={mapVariant(variant)}
      {...props}
    >
      {topContent}
      <HeroUITable.ScrollContainer className={classNames?.wrapper}>
        <HeroUITable.Content
          aria-label={ariaLabel}
          className={classNames?.table}
          defaultSelectedKeys={defaultSelectedKeys}
          disabledBehavior={disabledBehavior}
          disabledKeys={disabledKeys}
          disallowEmptySelection={disallowEmptySelection}
          onRowAction={onRowAction}
          onSelectionChange={onSelectionChange}
          onSortChange={onSortChange}
          selectedKeys={selectedKeys}
          selectionBehavior={selectionBehavior}
          selectionMode={selectionMode}
          sortDescriptor={sortDescriptor}
        >
          {children}
        </HeroUITable.Content>
      </HeroUITable.ScrollContainer>
      {bottomContent ? (
        <HeroUITable.Footer>{bottomContent}</HeroUITable.Footer>
      ) : null}
    </HeroUITable>
  );
}

function getAlignClass(align?: TableColumnProps['align']): string | undefined {
  if (align === 'center') {
    return 'text-center';
  }

  if (align === 'end' || align === 'right') {
    return 'text-right';
  }

  return undefined;
}

export function TableColumn({ align, className, scope: _scope, ...props }: TableColumnProps) {
  return <HeroUITable.Column className={combineClasses(getAlignClass(align), className)} {...props} />;
}

function withDefaultRowHeader(children: TableHeaderProps['children']) {
  if (typeof children === 'function') {
    return children;
  }

  const columns = Children.toArray(children);

  if (
    columns.some(
      (child) =>
        isValidElement<Partial<TableColumnProps>>(child) &&
        Boolean(child.props.isRowHeader)
    )
  ) {
    return children;
  }

  let didSetRowHeader = false;

  return columns.map((child) => {
    if (
      didSetRowHeader ||
      !isValidElement<Partial<TableColumnProps>>(child) ||
      child.type === Fragment
    ) {
      return child;
    }

    didSetRowHeader = true;
    return cloneElement(child, { isRowHeader: true });
  });
}

export function TableHeader({ children, className, ...props }: TableHeaderProps) {
  return (
    <HeroUITable.Header className={className} {...props}>
      {withDefaultRowHeader(children)}
    </HeroUITable.Header>
  );
}

export function TableBody<T extends object = object>({
  children,
  emptyContent,
  isLoading: isLoadingProp,
  loadingContent,
  loadingState,
  renderEmptyState,
  ...props
}: TableBodyProps<T>) {
  const isLoading = isLoadingProp || loadingState === 'loading' || loadingState === 'loadingMore';
  const resolvedRenderEmptyState =
    renderEmptyState ??
    (isLoading && loadingContent
      ? () => loadingContent
      : emptyContent
        ? () => emptyContent
        : undefined);

  return (
    <HeroUITable.Body
      renderEmptyState={resolvedRenderEmptyState}
      {...(props as HeroUITableBodyProps)}
    >
      {children as HeroUITableBodyProps['children']}
    </HeroUITable.Body>
  );
}

export function TableRow({ className, ...props }: TableRowProps) {
  return <HeroUITable.Row className={className} {...props} />;
}

export function TableCell({ className, title: _title, ...props }: TableCellProps) {
  return <HeroUITable.Cell className={className} {...props} />;
}
