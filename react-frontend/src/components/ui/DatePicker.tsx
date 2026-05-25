// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ReactNode } from 'react';
import type { DateValue } from '@internationalized/date';
import {
  Calendar,
  DateField,
  DatePicker as HeroUIDatePicker,
  Description,
  FieldError,
  Label,
} from '@heroui/react';

type HeroUIDatePickerProps = ComponentProps<typeof HeroUIDatePicker>;
type HeroUIDatePickerPopoverProps = ComponentProps<typeof HeroUIDatePicker.Popover>;
type HeroUIDatePickerTriggerProps = ComponentProps<typeof HeroUIDatePicker.Trigger>;
type HeroUICalendarProps = ComponentProps<typeof Calendar>;
type HeroUIDateFieldGroupProps = ComponentProps<typeof DateField.Group>;

export type DateInputValue = DateValue;

interface DatePickerClassNames {
  base?: string;
  calendar?: string;
  description?: string;
  errorMessage?: string;
  input?: string;
  inputWrapper?: string;
  label?: string;
  popoverContent?: string;
  selectorButton?: string;
  selectorIcon?: string;
}

export interface DatePickerProps extends Omit<HeroUIDatePickerProps, 'children' | 'className' | 'validate'> {
  CalendarBottomContent?: ReactNode;
  calendarProps?: Omit<Partial<HeroUICalendarProps>, 'className'> & { className?: string };
  children?: ReactNode;
  className?: string;
  classNames?: DatePickerClassNames;
  color?: string;
  description?: ReactNode;
  disableAnimation?: boolean;
  endContent?: ReactNode;
  errorMessage?: ReactNode;
  fullWidth?: boolean;
  label?: ReactNode;
  labelPlacement?: 'inside' | 'outside' | 'outside-left';
  popoverProps?: Omit<Partial<HeroUIDatePickerPopoverProps>, 'className'> & { className?: string };
  radius?: string;
  selectorButtonProps?: Partial<HeroUIDatePickerTriggerProps>;
  selectorIcon?: ReactNode;
  showMonthAndYearPickers?: boolean;
  size?: 'sm' | 'md' | 'lg';
  startContent?: ReactNode;
  validate?: unknown;
  variant?: 'flat' | 'bordered' | 'faded' | 'underlined' | 'primary' | 'secondary';
  visibleMonths?: number;
}

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function mapVariant(variant: DatePickerProps['variant']): HeroUIDateFieldGroupProps['variant'] {
  return variant === 'faded' || variant === 'underlined' || variant === 'secondary'
    ? 'secondary'
    : 'primary';
}

export function DatePicker({
  CalendarBottomContent,
  calendarProps,
  children,
  className,
  classNames,
  color: _color,
  description,
  disableAnimation: _disableAnimation,
  endContent,
  errorMessage,
  fullWidth,
  isInvalid,
  label,
  labelPlacement: _labelPlacement,
  popoverProps,
  radius: _radius,
  selectorButtonProps,
  selectorIcon,
  showMonthAndYearPickers,
  size: _size,
  startContent,
  validate: _validate,
  variant,
  visibleMonths,
  ...props
}: DatePickerProps) {
  const calendarAriaLabel = calendarProps?.['aria-label']
    ?? (typeof label === 'string' ? label : props['aria-label'] ?? props.name);

  return (
    <HeroUIDatePicker
      {...props}
      className={combineClasses(fullWidth && 'w-full', classNames?.base, className)}
      isInvalid={isInvalid}
    >
      {children ?? (
        <>
          {label && <Label className={classNames?.label}>{label}</Label>}
          <DateField.Group
            className={combineClasses(fullWidth && 'w-full', classNames?.inputWrapper)}
            fullWidth={fullWidth}
            variant={mapVariant(variant)}
          >
            {startContent && <DateField.Prefix>{startContent}</DateField.Prefix>}
            <DateField.Input className={classNames?.input}>
              {(segment) => <DateField.Segment segment={segment} />}
            </DateField.Input>
            <DateField.Suffix>
              {endContent}
              <HeroUIDatePicker.Trigger
                className={classNames?.selectorButton}
                {...selectorButtonProps}
              >
                <HeroUIDatePicker.TriggerIndicator className={classNames?.selectorIcon}>
                  {selectorIcon}
                </HeroUIDatePicker.TriggerIndicator>
              </HeroUIDatePicker.Trigger>
            </DateField.Suffix>
          </DateField.Group>
          {isInvalid && errorMessage ? (
            <FieldError className={classNames?.errorMessage}>{errorMessage}</FieldError>
          ) : (
            description && <Description className={classNames?.description}>{description}</Description>
          )}
          <HeroUIDatePicker.Popover
            className={combineClasses(classNames?.popoverContent, popoverProps?.className)}
            {...popoverProps}
          >
            <Calendar
              {...calendarProps}
              aria-label={calendarAriaLabel}
              className={combineClasses(classNames?.calendar, calendarProps?.className)}
              visibleDuration={visibleMonths ? { months: visibleMonths } : calendarProps?.visibleDuration}
            >
              <Calendar.Header>
                {showMonthAndYearPickers !== false ? (
                  <Calendar.YearPickerTrigger>
                    <Calendar.YearPickerTriggerHeading />
                    <Calendar.YearPickerTriggerIndicator />
                  </Calendar.YearPickerTrigger>
                ) : (
                  <Calendar.Heading />
                )}
                <Calendar.NavButton slot="previous" />
                <Calendar.NavButton slot="next" />
              </Calendar.Header>
              <Calendar.Grid>
                <Calendar.GridHeader>
                  {(day) => <Calendar.HeaderCell>{day}</Calendar.HeaderCell>}
                </Calendar.GridHeader>
                <Calendar.GridBody>
                  {(date) => <Calendar.Cell date={date} />}
                </Calendar.GridBody>
              </Calendar.Grid>
              {showMonthAndYearPickers !== false ? (
                <Calendar.YearPickerGrid>
                  <Calendar.YearPickerGridBody>
                    {({ year }) => <Calendar.YearPickerCell year={year} />}
                  </Calendar.YearPickerGridBody>
                </Calendar.YearPickerGrid>
              ) : null}
            </Calendar>
            {CalendarBottomContent}
          </HeroUIDatePicker.Popover>
        </>
      )}
    </HeroUIDatePicker>
  );
}
