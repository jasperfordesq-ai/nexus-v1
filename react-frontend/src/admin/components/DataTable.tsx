/**
 * Admin Data Table
 * Reusable table with sorting, filtering, pagination, and bulk actions.
 * Built on HeroUI Table component.
 */

import { useState, useMemo, useCallback, type ReactNode } from 'react';
import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Input,
  Button,
  Pagination,
  Spinner,
  Chip,
  type Selection,
  type SortDescriptor,
} from '@heroui/react';
import { Search, RefreshCw } from 'lucide-react';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface Column<T> {
  key: string;
  label: string;
  sortable?: boolean;
  render?: (item: T) => ReactNode;
  width?: number;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
interface DataTableProps<T extends Record<string, any>> {
  columns: Column<T>[];
  data: T[];
  keyField?: string;
  isLoading?: boolean;
  searchable?: boolean;
  searchPlaceholder?: string;
  totalItems?: number;
  page?: number;
  pageSize?: number;
  onPageChange?: (page: number) => void;
  onSearch?: (query: string) => void;
  onRefresh?: () => void;
  selectable?: boolean;
  onSelectionChange?: (selectedKeys: Set<string>) => void;
  topContent?: ReactNode;
  emptyContent?: ReactNode;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function DataTable<T extends Record<string, any>>({
  columns,
  data,
  keyField = 'id',
  isLoading = false,
  searchable = true,
  searchPlaceholder = 'Search...',
  totalItems,
  page = 1,
  pageSize = 20,
  onPageChange,
  onSearch,
  onRefresh,
  selectable = false,
  onSelectionChange,
  topContent,
  emptyContent,
}: DataTableProps<T>) {
  const [searchValue, setSearchValue] = useState('');
  const [sortDescriptor, setSortDescriptor] = useState<SortDescriptor | undefined>(undefined);

  const totalPages = totalItems ? Math.ceil(totalItems / pageSize) : 1;

  const handleSearchChange = useCallback(
    (value: string) => {
      setSearchValue(value);
      onSearch?.(value);
    },
    [onSearch]
  );

  const handleSelectionChange = useCallback(
    (keys: Selection) => {
      if (keys === 'all') {
        onSelectionChange?.(new Set(data.map((item) => String(item[keyField]))));
      } else {
        onSelectionChange?.(keys as Set<string>);
      }
    },
    [data, keyField, onSelectionChange]
  );

  // Sort data locally if no server-side sorting
  const sortedData = useMemo(() => {
    if (!sortDescriptor?.column) return data;
    const { column, direction } = sortDescriptor;
    return [...data].sort((a, b) => {
      const aVal = a[column as string];
      const bVal = b[column as string];
      if (aVal === bVal) return 0;
      if (aVal === null || aVal === undefined) return 1;
      if (bVal === null || bVal === undefined) return -1;
      const cmp = String(aVal).localeCompare(String(bVal), undefined, { numeric: true });
      return direction === 'ascending' ? cmp : -cmp;
    });
  }, [data, sortDescriptor]);

  // Top content (search + actions)
  const tableTopContent = useMemo(
    () => (
      <div className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-3 flex-1">
          {searchable && (
            <Input
              className="max-w-xs"
              placeholder={searchPlaceholder}
              startContent={<Search size={16} className="text-default-400" />}
              value={searchValue}
              onValueChange={handleSearchChange}
              size="sm"
              variant="bordered"
            />
          )}
          {onRefresh && (
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              onPress={onRefresh}
              aria-label="Refresh"
            >
              <RefreshCw size={16} />
            </Button>
          )}
        </div>
        {topContent}
      </div>
    ),
    [searchable, searchPlaceholder, searchValue, handleSearchChange, onRefresh, topContent]
  );

  // Bottom content (pagination)
  const tableBottomContent = useMemo(() => {
    if (!onPageChange || totalPages <= 1) return null;
    return (
      <div className="flex items-center justify-between px-2 py-2">
        <span className="text-sm text-default-400">
          {totalItems ? `${totalItems.toLocaleString()} total` : ''}
        </span>
        <Pagination
          total={totalPages}
          page={page}
          onChange={onPageChange}
          showControls
          size="sm"
        />
      </div>
    );
  }, [onPageChange, totalPages, page, totalItems]);

  return (
    <Table
      aria-label="Admin data table"
      selectionMode={selectable ? 'multiple' : 'none'}
      onSelectionChange={selectable ? handleSelectionChange : undefined}
      sortDescriptor={sortDescriptor}
      onSortChange={setSortDescriptor}
      topContent={tableTopContent}
      topContentPlacement="outside"
      bottomContent={tableBottomContent}
      bottomContentPlacement="outside"
      classNames={{
        wrapper: 'shadow-sm',
      }}
    >
      <TableHeader>
        {columns.map((col) => (
          <TableColumn
            key={col.key}
            allowsSorting={col.sortable}
            width={col.width}
          >
            {col.label}
          </TableColumn>
        ))}
      </TableHeader>
      <TableBody
        isLoading={isLoading}
        loadingContent={<Spinner size="lg" />}
        emptyContent={emptyContent || 'No data found'}
        items={sortedData}
      >
        {(item) => (
          <TableRow key={String(item[keyField])}>
            {columns.map((col) => (
              <TableCell key={col.key}>
                {col.render
                  ? col.render(item as T)
                  : (item[col.key] as ReactNode) ?? '—'}
              </TableCell>
            ))}
          </TableRow>
        )}
      </TableBody>
    </Table>
  );
}

export default DataTable;

// ─────────────────────────────────────────────────────────────────────────────
// Status Badge helper
// ─────────────────────────────────────────────────────────────────────────────

const statusColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'primary'> = {
  active: 'success',
  approved: 'success',
  completed: 'success',
  published: 'success',
  sent: 'success',
  pending: 'warning',
  draft: 'default',
  scheduled: 'primary',
  suspended: 'danger',
  banned: 'danger',
  rejected: 'danger',
  failed: 'danger',
  inactive: 'default',
  idle: 'default',
};

export function StatusBadge({ status }: { status: string }) {
  const safeStatus = status || 'unknown';
  const color = statusColorMap[safeStatus.toLowerCase()] || 'default';
  return (
    <Chip size="sm" variant="flat" color={color} className="capitalize">
      {safeStatus}
    </Chip>
  );
}
