// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Shared Accordion — the public-shared port of the SPA's @/components/ui/Accordion
 * wrapper, so the FAQ (and other public pages) render the SAME accordion markup in
 * both the Vite SPA and the Next SSR app. Identical to the SPA wrapper except it
 * uses a named lucide import (resolves in both bundlers) and the dependency-free
 * shared cn(). Adapts HeroUI v3's compound Accordion API to the legacy props the
 * public pages pass (selectionMode, variant, itemClasses, title/subtitle).
 */

import { isValidElement, type ComponentProps, type ReactNode } from 'react';
import { Accordion as HeroAccordion, type Accordion as HeroAccordionTypes } from '@heroui/react';
import { ChevronDown } from 'lucide-react';

import { cn } from '../cn';

type HeroAccordionProps = HeroAccordionTypes['Props'];
type HeroAccordionItemProps = HeroAccordionTypes['ItemProps'];
type HeroAccordionBodyProps = ComponentProps<typeof HeroAccordion.Body>;

type LegacyAccordionVariant = 'light' | 'shadow' | 'bordered' | 'splitted' | 'default' | 'surface';

interface LegacyAccordionItemClasses {
  base?: string;
  content?: string;
  heading?: string;
  indicator?: string;
  subtitle?: string;
  title?: string;
  trigger?: string;
}

export interface AccordionProps
  extends Omit<
    HeroAccordionProps,
    'allowsMultipleExpanded' | 'defaultExpandedKeys' | 'expandedKeys' | 'hideSeparator' | 'onExpandedChange' | 'variant'
  > {
  allowsMultipleExpanded?: boolean;
  defaultExpandedKeys?: HeroAccordionProps['defaultExpandedKeys'];
  defaultSelectedKeys?: HeroAccordionProps['defaultExpandedKeys'];
  expandedKeys?: HeroAccordionProps['expandedKeys'];
  hideSeparator?: boolean;
  itemClasses?: LegacyAccordionItemClasses;
  onExpandedChange?: HeroAccordionProps['onExpandedChange'];
  onSelectionChange?: HeroAccordionProps['onExpandedChange'];
  selectedKeys?: HeroAccordionProps['expandedKeys'];
  selectionMode?: 'single' | 'multiple';
  showDivider?: boolean;
  variant?: LegacyAccordionVariant;
}

export function Accordion({
  className,
  defaultExpandedKeys,
  defaultSelectedKeys,
  expandedKeys,
  itemClasses: _itemClasses,
  onExpandedChange,
  onSelectionChange,
  selectedKeys,
  selectionMode,
  showDivider,
  variant,
  ...props
}: AccordionProps) {
  return (
    <HeroAccordion
      {...props}
      allowsMultipleExpanded={selectionMode === 'multiple' || props.allowsMultipleExpanded}
      className={cn(variant === 'splitted' && 'space-y-2', className as string | undefined)}
      defaultExpandedKeys={defaultExpandedKeys ?? defaultSelectedKeys}
      expandedKeys={expandedKeys ?? selectedKeys}
      hideSeparator={showDivider === false ? true : props.hideSeparator}
      onExpandedChange={onExpandedChange ?? onSelectionChange}
      variant={mapVariant(variant)}
    />
  );
}

export interface AccordionItemProps extends Omit<HeroAccordionItemProps, 'children' | 'id'> {
  children?: ReactNode;
  classNames?: LegacyAccordionItemClasses;
  hideIndicator?: boolean;
  id?: HeroAccordionItemProps['id'];
  indicator?: ReactNode | ((props: { isOpen?: boolean; isDisabled?: boolean }) => ReactNode);
  startContent?: ReactNode;
  subtitle?: ReactNode;
  title?: ReactNode;
}

export function AccordionItem({
  children,
  className,
  classNames,
  hideIndicator,
  id,
  indicator,
  startContent,
  subtitle,
  title,
  ...props
}: AccordionItemProps) {
  return (
    <HeroAccordion.Item {...props} id={id} className={cn(classNames?.base, className as string | undefined)}>
      <HeroAccordion.Heading className={classNames?.heading}>
        <HeroAccordion.Trigger className={cn('gap-3', classNames?.trigger)}>
          {startContent}
          <span className={cn('flex min-w-0 flex-1 flex-col text-left', classNames?.title)}>
            {renderTitle(title)}
            {subtitle && <span className={cn('text-sm text-theme-subtle', classNames?.subtitle)}>{subtitle}</span>}
          </span>
          {!hideIndicator && (
            <HeroAccordion.Indicator className={classNames?.indicator}>
              {renderIndicator(indicator)}
            </HeroAccordion.Indicator>
          )}
        </HeroAccordion.Trigger>
      </HeroAccordion.Heading>
      <HeroAccordion.Panel>
        <HeroAccordion.Body className={classNames?.content as HeroAccordionBodyProps['className']}>
          {children}
        </HeroAccordion.Body>
      </HeroAccordion.Panel>
    </HeroAccordion.Item>
  );
}

function mapVariant(variant: LegacyAccordionVariant | undefined): HeroAccordionProps['variant'] {
  if (variant === 'shadow' || variant === 'surface') {
    return 'surface';
  }
  return 'default';
}

function renderTitle(title: ReactNode) {
  if (title === undefined || title === null) {
    return null;
  }
  if (typeof title === 'string' || typeof title === 'number' || isValidElement(title)) {
    return title;
  }
  return <span>{title}</span>;
}

function renderIndicator(indicator: AccordionItemProps['indicator']) {
  if (typeof indicator === 'function') {
    return indicator({});
  }
  return indicator ?? <ChevronDown className="size-4" aria-hidden="true" />;
}
