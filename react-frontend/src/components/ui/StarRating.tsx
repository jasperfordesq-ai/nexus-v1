// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, type ReactNode } from 'react';

import Star from 'lucide-react/icons/star';
import { Label } from '@heroui/react/label';
import { Radio } from '@heroui/react/radio';
import { RadioGroup } from '@heroui/react/radio-group';

const DEFAULT_MAX_RATING = 5;

function combineClasses(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ');
}

export interface StarRatingProps {
  /** The selected rating. Use zero when no rating has been selected. */
  value: number;
  onChange: (value: number) => void;
  /** Translated accessible label for the whole radio group. */
  label: ReactNode;
  /** Returns the translated accessible name for an individual rating option. */
  getOptionLabel: (value: number) => string;
  /** Optionally returns translated feedback for the hovered or selected value. */
  getValueDescription?: (value: number) => ReactNode;
  max?: number;
  name?: string;
  isDisabled?: boolean;
  isRequired?: boolean;
  className?: string;
  labelClassName?: string;
  descriptionClassName?: string;
}

/**
 * Accessible star-rating input built on HeroUI v3's React Aria RadioGroup.
 *
 * The selected (or first unselected) radio is the sole tab stop. Arrow keys
 * move and select within the group using React Aria's native radio behaviour.
 */
export function StarRating({
  value,
  onChange,
  label,
  getOptionLabel,
  getValueDescription,
  max = DEFAULT_MAX_RATING,
  name,
  isDisabled = false,
  isRequired = false,
  className,
  labelClassName,
  descriptionClassName,
}: StarRatingProps) {
  const [hoveredValue, setHoveredValue] = useState(0);
  const displayValue = hoveredValue || value;
  const ratings = Array.from({ length: max }, (_, index) => index + 1);

  return (
    <RadioGroup
      value={value > 0 ? String(value) : null}
      onChange={(nextValue) => onChange(Number(nextValue))}
      orientation="horizontal"
      name={name}
      isDisabled={isDisabled}
      isRequired={isRequired}
      className={combineClasses('w-full items-center gap-1', className)}
    >
      <Label className={combineClasses('basis-full', labelClassName ?? 'sr-only')}>
        {label}
      </Label>

      {ratings.map((rating) => {
        const isFilled = rating <= displayValue;
        const optionLabel = getOptionLabel(rating);

        return (
          <Radio
            key={rating}
            value={String(rating)}
            aria-label={optionLabel}
            onHoverChange={(isHovered) => setHoveredValue(isHovered ? rating : 0)}
            className={combineClasses(
              'group m-0 inline-flex size-11 min-h-11 min-w-11 cursor-pointer items-center justify-center',
              'rounded-full outline-solid outline-transparent transition-transform motion-reduce:transition-none',
              'hover:scale-110 data-[selected=true]:bg-warning/10 [&:has(input:checked)]:bg-warning/10',
              'data-[focus-visible=true]:outline-2 data-[focus-visible=true]:outline-focus',
              'data-[focus-visible=true]:outline-offset-2 [&:has(input:focus-visible)]:outline-2',
              '[&:has(input:focus-visible)]:outline-focus [&:has(input:focus-visible)]:outline-offset-2',
              'data-[disabled=true]:cursor-not-allowed data-[disabled=true]:opacity-50',
            )}
          >
            <Radio.Content className="flex size-full items-center justify-center">
              <Star
                aria-hidden="true"
                data-filled={isFilled ? 'true' : 'false'}
                className={combineClasses(
                  'size-8 transition-colors motion-reduce:transition-none',
                  isFilled ? 'fill-warning text-warning' : 'fill-transparent text-theme-subtle',
                )}
              />
            </Radio.Content>
          </Radio>
        );
      })}

      {displayValue > 0 && getValueDescription ? (
        <span
          aria-live="polite"
          className={combineClasses('basis-full text-sm text-theme-muted', descriptionClassName)}
        >
          {getValueDescription(displayValue)}
        </span>
      ) : null}
    </RadioGroup>
  );
}
