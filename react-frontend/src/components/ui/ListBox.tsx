// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Fragment, isValidElement, type ComponentProps, type ReactNode } from 'react';
import {
  Description,
  Header,
  Label,
  ListBox as HeroListBox,
  Separator,
} from '@heroui/react';
import { cn } from '@/lib/helpers';

type HeroListBoxProps = ComponentProps<typeof HeroListBox>;
type HeroListBoxItemProps = ComponentProps<typeof HeroListBox.Item>;
type HeroListBoxSectionProps = ComponentProps<typeof HeroListBox.Section>;

export type { Selection } from '@heroui/react';

export interface ListBoxProps<T extends object = object>
  extends Omit<HeroListBoxProps, 'className' | 'children'> {
  children?: ReactNode | ((item: T) => ReactNode);
  className?: string;
  items?: Iterable<T>;
}

/**
 * A list of selectable options (single/multiple) built on HeroUI v3 ListBox.
 * Use it directly for selectable panels, or compose it inside Autocomplete/ComboBox
 * via the shared `ListBoxItem` / `ListBoxSection` parts.
 */
export function ListBox<T extends object = object>({ children, className, items, ...props }: ListBoxProps<T>) {
  return (
    <HeroListBox {...props} className={cn(className)} items={items as HeroListBoxProps['items']}>
      {children as HeroListBoxProps['children']}
    </HeroListBox>
  );
}

export interface ListBoxItemProps
  extends Omit<HeroListBoxItemProps, 'children' | 'id' | 'textValue'> {
  children?: ReactNode;
  classNames?: { base?: string; description?: string };
  description?: ReactNode;
  endContent?: ReactNode;
  id?: HeroListBoxItemProps['id'];
  startContent?: ReactNode;
  textValue?: string;
}

export function ListBoxItem({
  children,
  className,
  classNames,
  description,
  endContent,
  id,
  startContent,
  textValue,
  ...props
}: ListBoxItemProps) {
  return (
    <HeroListBox.Item
      {...props}
      className={cn(classNames?.base, className as string | undefined)}
      id={id}
      textValue={textValue ?? inferTextValue(children)}
    >
      {startContent}
      {description ? (
        <div className="flex flex-col">
          {renderLabel(children)}
          <Description className={classNames?.description}>{description}</Description>
        </div>
      ) : (
        renderLabel(children)
      )}
      {endContent}
      <HeroListBox.ItemIndicator />
    </HeroListBox.Item>
  );
}

export interface ListBoxSectionProps extends Omit<HeroListBoxSectionProps, 'children'> {
  children?: ReactNode;
  showDivider?: boolean;
  title?: ReactNode;
}

export function ListBoxSection({ children, showDivider, title, ...props }: ListBoxSectionProps) {
  return (
    <Fragment>
      <HeroListBox.Section {...props}>
        {title && <Header>{title}</Header>}
        {children}
      </HeroListBox.Section>
      {showDivider && <Separator />}
    </Fragment>
  );
}

function renderLabel(children: ReactNode) {
  if (typeof children === 'string' || typeof children === 'number') {
    return <Label>{children}</Label>;
  }

  return children;
}

function inferTextValue(node: ReactNode): string | undefined {
  if (typeof node === 'string' || typeof node === 'number') {
    return String(node);
  }

  if (Array.isArray(node)) {
    const text = node.map(inferTextValue).filter(Boolean).join(' ').trim();

    return text || undefined;
  }

  if (isValidElement<{ children?: ReactNode }>(node)) {
    return inferTextValue(node.props.children);
  }

  return undefined;
}
