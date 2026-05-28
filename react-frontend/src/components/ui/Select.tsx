// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Fragment, isValidElement, type ComponentProps, type ReactNode } from 'react';
import {
  Description,
  FieldError,
  Header,
  Label,
  ListBox,
  Select as HeroSelect,
  Separator,
  type ListBox as HeroListBoxTypes,
  type Select as HeroSelectTypes,
} from '@heroui/react';
import { cn } from '@/lib/helpers';

type HeroSelectProps = HeroSelectTypes['Props'];
type HeroSelectPopoverProps = HeroSelectTypes['PopoverProps'];
type HeroListBoxProps = HeroListBoxTypes['Props'];
type HeroListBoxItemProps = ComponentProps<typeof ListBox.Item>;
type HeroListBoxSectionProps = ComponentProps<typeof ListBox.Section>;
type SelectKey = string | number;
type SelectionLike = 'all' | Iterable<SelectKey>;
const HeroSelectCompat = HeroSelect as any;

interface LegacySelectClassNames {
  base?: string;
  description?: string;
  errorMessage?: string;
  label?: string;
  listbox?: string;
  listboxWrapper?: string;
  mainWrapper?: string;
  popoverContent?: string;
  selectorIcon?: string;
  trigger?: string;
  value?: string;
}

export interface SelectProps<T extends object = object>
  extends Omit<
    HeroSelectProps,
    | 'children'
    | 'className'
    | 'defaultValue'
    | 'onChange'
    | 'onSelectionChange'
    | 'selectionMode'
    | 'size'
    | 'value'
    | 'variant'
  > {
  children?: ReactNode | ((item: T) => ReactNode);
  className?: string;
  classNames?: LegacySelectClassNames;
  color?: string;
  defaultSelectedKeys?: SelectionLike;
  defaultValue?: HeroSelectProps['defaultValue'];
  description?: ReactNode;
  disallowEmptySelection?: boolean;
  endContent?: ReactNode;
  errorMessage?: ReactNode;
  isClearable?: boolean;
  isLoading?: boolean;
  items?: Iterable<T>;
  label?: ReactNode;
  labelPlacement?: string;
  listboxProps?: Partial<HeroListBoxProps> & Record<string, unknown>;
  onChange?: (event: { target: { value: string }; currentTarget: { value: string } }) => void;
  onSelectionChange?: (keys: any) => void;
  onValueChange?: (value: string) => void;
  popoverProps?: Partial<HeroSelectPopoverProps> & Record<string, unknown>;
  renderValue?: (items: Array<{ key: SelectKey | null; textValue?: string }>) => ReactNode;
  scrollShadowProps?: unknown;
  selectedKeys?: SelectionLike;
  selectorIcon?: ReactNode;
  selectionMode?: 'single' | 'multiple';
  size?: string;
  startContent?: ReactNode;
  value?: HeroSelectProps['value'];
  variant?: HeroSelectProps['variant'] | 'flat' | 'faded' | 'bordered' | 'underlined';
}

export function Select<T extends object = object>({
  children,
  className,
  classNames,
  color: _color,
  defaultSelectedKeys,
  defaultValue,
  description,
  disallowEmptySelection: _disallowEmptySelection,
  endContent,
  errorMessage,
  isClearable: _isClearable,
  isLoading: _isLoading,
  items,
  label,
  labelPlacement: _labelPlacement,
  listboxProps,
  onChange,
  onSelectionChange,
  onValueChange,
  popoverProps,
  renderValue,
  scrollShadowProps: _scrollShadowProps,
  selectedKeys,
  selectorIcon,
  size: _size,
  startContent,
  value,
  variant,
  ...props
}: SelectProps<T>) {
  const selectionMode = props.selectionMode;
  const resolvedValue = value ?? selectionToValue(selectedKeys, selectionMode);
  const resolvedDefaultValue = defaultValue ?? selectionToValue(defaultSelectedKeys, selectionMode);

  return (
    <HeroSelectCompat
      {...props}
      className={cn(classNames?.base, classNames?.mainWrapper, className)}
      defaultValue={resolvedDefaultValue}
      items={items}
      onChange={(nextValue: SelectKey | readonly SelectKey[] | null) => {
        const values = valueToArray(nextValue);
        const firstValue = values[0] == null ? '' : String(values[0]);

        onSelectionChange?.(new Set(values));
        onValueChange?.(firstValue);
        onChange?.({ target: { value: firstValue }, currentTarget: { value: firstValue } });
      }}
      value={resolvedValue}
      variant={variant === 'bordered' || variant === 'underlined' ? 'secondary' : mapVariant(variant)}
    >
      {label && <Label className={classNames?.label}>{label}</Label>}
      <HeroSelect.Trigger className={classNames?.trigger}>
        {startContent}
        <HeroSelect.Value className={classNames?.value}>
          {renderValue
            ? ({ selectedText, state }) => {
                const selectedItems = state.selectedItems?.map((item) => ({
                  key: item?.key ?? null,
                  textValue: item?.textValue,
                })) ?? [];

                return renderValue(selectedItems.length ? selectedItems : [{ key: null, textValue: selectedText }]);
              }
            : undefined}
        </HeroSelect.Value>
        {endContent}
        <HeroSelect.Indicator className={classNames?.selectorIcon}>{selectorIcon}</HeroSelect.Indicator>
      </HeroSelect.Trigger>
      {description && <Description className={classNames?.description}>{description}</Description>}
      <HeroSelect.Popover
        {...popoverProps}
        className={cn(classNames?.popoverContent, popoverProps?.className as string | undefined)}
      >
        <ListBox
          {...listboxProps}
          className={cn(classNames?.listboxWrapper, classNames?.listbox, listboxProps?.className)}
          items={items}
          selectionMode={selectionMode}
        >
          {children as HeroListBoxProps['children']}
        </ListBox>
      </HeroSelect.Popover>
      {errorMessage && <FieldError className={classNames?.errorMessage}>{errorMessage}</FieldError>}
    </HeroSelectCompat>
  );
}

export interface SelectItemProps
  extends Omit<HeroListBoxItemProps, 'children' | 'id' | 'textValue' | 'variant'> {
  children?: ReactNode;
  classNames?: { base?: string; description?: string; title?: string };
  color?: string;
  description?: ReactNode;
  endContent?: ReactNode;
  id?: HeroListBoxItemProps['id'];
  startContent?: ReactNode;
  textValue?: string;
  variant?: HeroListBoxItemProps['variant'] | 'flat' | 'faded' | 'bordered' | 'light' | 'shadow';
}

export function SelectItem({
  children,
  className,
  classNames,
  color,
  description,
  endContent,
  id,
  startContent,
  textValue,
  variant,
  ...props
}: SelectItemProps) {
  const danger = color === 'danger' || variant === 'danger';

  return (
    <ListBox.Item
      {...props}
      className={cn(classNames?.base, className as string | undefined)}
      id={id}
      textValue={textValue ?? inferTextValue(children)}
      variant={danger ? 'danger' : 'default'}
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
      <ListBox.ItemIndicator />
    </ListBox.Item>
  );
}

export interface SelectSectionProps extends Omit<HeroListBoxSectionProps, 'children'> {
  children?: ReactNode;
  showDivider?: boolean;
  title?: ReactNode;
}

export function SelectSection({ children, showDivider, title, ...props }: SelectSectionProps) {
  return (
    <Fragment>
      <ListBox.Section {...props}>
        {title && <Header>{title}</Header>}
        {children}
      </ListBox.Section>
      {showDivider && <Separator />}
    </Fragment>
  );
}

function mapVariant(variant: SelectProps['variant']): HeroSelectProps['variant'] {
  if (variant === 'secondary') {
    return 'secondary';
  }

  return 'primary';
}

function selectionToValue(selection: SelectionLike | undefined, selectionMode: SelectProps['selectionMode']) {
  if (!selection || selection === 'all') {
    return undefined;
  }

  const values = Array.from(selection);

  if (selectionMode === 'multiple') {
    return values;
  }

  return values[0] ?? null;
}

function valueToArray(value: SelectKey | readonly SelectKey[] | null) {
  if (Array.isArray(value)) {
    return value;
  }

  return value == null ? [] : [value];
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
