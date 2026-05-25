// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  useState,
  type ComponentPropsWithoutRef,
  type ReactNode,
} from 'react';
import { Pagination as HeroUIPagination } from '@heroui-v3/react';

type HeroUIPaginationProps = ComponentPropsWithoutRef<typeof HeroUIPagination>;
type PaginationItem = number | 'ellipsis';

export type PaginationProps = Omit<HeroUIPaginationProps, 'children' | 'className'> & {
  boundaries?: number;
  children?: ReactNode;
  className?: string;
  classNames?: {
    base?: string;
    wrapper?: string;
    prev?: string;
    next?: string;
    item?: string;
    cursor?: string;
    ellipsis?: string;
  };
  color?: string;
  disableAnimation?: boolean;
  disableCursorAnimation?: boolean;
  dotsJump?: number;
  getItemAriaLabel?: (page: number) => string;
  initialPage?: number;
  isCompact?: boolean;
  isDisabled?: boolean;
  loop?: boolean;
  onChange?: (page: number) => void;
  page?: number;
  radius?: string;
  showControls?: boolean;
  showShadow?: boolean;
  siblings?: number;
  total?: number;
  variant?: string;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function getPageItems(page: number, total: number, siblings: number, boundaries: number): PaginationItem[] {
  if (total <= 0) {
    return [];
  }

  const maxVisible = boundaries * 2 + siblings * 2 + 3;

  if (total <= maxVisible) {
    return Array.from({ length: total }, (_, index) => index + 1);
  }

  const pages = new Set<number>();

  for (let index = 1; index <= Math.min(boundaries, total); index += 1) {
    pages.add(index);
  }

  for (let index = Math.max(1, total - boundaries + 1); index <= total; index += 1) {
    pages.add(index);
  }

  for (let index = Math.max(1, page - siblings); index <= Math.min(total, page + siblings); index += 1) {
    pages.add(index);
  }

  const sortedPages = Array.from(pages).sort((a, b) => a - b);
  const items: PaginationItem[] = [];

  sortedPages.forEach((item, index) => {
    const previous = sortedPages[index - 1];

    if (previous && item - previous > 1) {
      items.push('ellipsis');
    }

    items.push(item);
  });

  return items;
}

export function Pagination({
  boundaries = 1,
  children,
  className,
  classNames,
  color: _color,
  disableAnimation: _disableAnimation,
  disableCursorAnimation: _disableCursorAnimation,
  dotsJump: _dotsJump,
  getItemAriaLabel,
  initialPage = 1,
  isCompact: _isCompact,
  isDisabled,
  loop,
  onChange,
  page,
  radius: _radius,
  showControls,
  showShadow: _showShadow,
  siblings = 1,
  total = 1,
  variant: _variant,
  ...props
}: PaginationProps) {
  const [uncontrolledPage, setUncontrolledPage] = useState(initialPage);
  const currentPage = Math.min(Math.max(page ?? uncontrolledPage, 1), Math.max(total, 1));

  const setPage = (nextPage: number) => {
    const normalizedPage = loop
      ? ((nextPage - 1 + total) % total) + 1
      : Math.min(Math.max(nextPage, 1), total);

    if (page == null) {
      setUncontrolledPage(normalizedPage);
    }

    onChange?.(normalizedPage);
  };

  if (children) {
    return (
      <HeroUIPagination className={combineClasses(classNames?.base, className)} {...props}>
        {children}
      </HeroUIPagination>
    );
  }

  return (
    <HeroUIPagination className={combineClasses(classNames?.base, className)} {...props}>
      <HeroUIPagination.Content className={classNames?.wrapper}>
        {showControls ? (
          <HeroUIPagination.Item className={classNames?.item}>
            <HeroUIPagination.Previous
              className={classNames?.prev}
              isDisabled={isDisabled || (!loop && currentPage <= 1)}
              onPress={() => setPage(currentPage - 1)}
            >
              <HeroUIPagination.PreviousIcon />
            </HeroUIPagination.Previous>
          </HeroUIPagination.Item>
        ) : null}
        {getPageItems(currentPage, total, siblings, boundaries).map((item, index) => (
          <HeroUIPagination.Item key={`${item}-${index}`} className={classNames?.item}>
            {item === 'ellipsis' ? (
              <HeroUIPagination.Ellipsis className={classNames?.ellipsis} />
            ) : (
              <HeroUIPagination.Link
                aria-label={getItemAriaLabel?.(item)}
                className={currentPage === item ? classNames?.cursor : undefined}
                isActive={currentPage === item}
                isDisabled={isDisabled}
                onPress={() => setPage(item)}
              >
                {item}
              </HeroUIPagination.Link>
            )}
          </HeroUIPagination.Item>
        ))}
        {showControls ? (
          <HeroUIPagination.Item className={classNames?.item}>
            <HeroUIPagination.Next
              className={classNames?.next}
              isDisabled={isDisabled || (!loop && currentPage >= total)}
              onPress={() => setPage(currentPage + 1)}
            >
              <HeroUIPagination.NextIcon />
            </HeroUIPagination.Next>
          </HeroUIPagination.Item>
        ) : null}
      </HeroUIPagination.Content>
    </HeroUIPagination>
  );
}
