// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ReactNode } from 'react';
import { Label } from '@heroui/react/label';
import { Slider as HeroUISlider } from '@heroui/react/slider';

type HeroUISliderProps = ComponentProps<typeof HeroUISlider>;
type SliderValue = number | number[];

interface SliderClassNames {
  base?: string;
  filler?: string;
  label?: string;
  mark?: string;
  output?: string;
  step?: string;
  thumb?: string;
  track?: string;
  value?: string;
}

type SliderMark = {
  value: number;
  label?: ReactNode;
};

export interface SliderProps extends Omit<HeroUISliderProps, 'children' | 'className'> {
  children?: ReactNode;
  className?: string;
  classNames?: SliderClassNames;
  color?: string;
  disableAnimation?: boolean;
  disableThumbScale?: boolean;
  fillOffset?: number;
  getTooltipValue?: (value: SliderValue) => ReactNode;
  getValue?: (value: SliderValue) => ReactNode;
  hideThumb?: boolean;
  hideValue?: boolean;
  label?: ReactNode;
  marks?: SliderMark[];
  radius?: string;
  renderLabel?: (props: { children?: ReactNode }) => ReactNode;
  renderThumb?: (props: { index: number }) => ReactNode;
  renderValue?: (props: { children?: ReactNode }) => ReactNode;
  showOutline?: boolean;
  showSteps?: boolean;
  showTooltip?: boolean;
  size?: 'sm' | 'md' | 'lg';
}

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function mapColor(color?: SliderProps['color']): string | undefined {
  switch (color) {
    case 'warning':
      return 'bg-warning';
    case 'danger':
      return 'bg-danger';
    case 'success':
      return 'bg-success';
    case 'primary':
      return 'bg-accent';
    case 'secondary':
      return 'bg-accent-soft';
    default:
      return undefined;
  }
}

export function Slider({
  children,
  className,
  classNames,
  color,
  disableAnimation: _disableAnimation,
  disableThumbScale: _disableThumbScale,
  fillOffset: _fillOffset,
  getTooltipValue,
  getValue,
  hideThumb,
  hideValue,
  label,
  marks,
  radius: _radius,
  renderLabel,
  renderThumb,
  renderValue,
  showOutline: _showOutline,
  showSteps = false,
  showTooltip = false,
  size = 'md',
  ...props
}: SliderProps) {
  const labelNode = renderLabel ? renderLabel({ children: label }) : label;
  const orientation = props.orientation ?? 'horizontal';
  const sizeClasses = SLIDER_SIZE_CLASSES[size];
  const stepValues = showSteps
    ? buildStepValues(props.minValue ?? 0, props.maxValue ?? 100, props.step ?? 1)
    : [];

  return (
    <HeroUISlider
      {...props}
      className={combineClasses(sizeClasses.base, classNames?.base, className)}
      data-size={size}
    >
      {children ?? (
        <>
          {labelNode ? <Label className={combineClasses(sizeClasses.label, classNames?.label)}>{labelNode}</Label> : null}
          {!hideValue ? (
            <HeroUISlider.Output className={combineClasses(sizeClasses.output, classNames?.output, classNames?.value)}>
              {renderValue
                ? ({ state }) => renderValue({ children: state.values.map((_, index) => state.getThumbValueLabel(index)).join(' - ') })
                : getValue
                  ? ({ state }) => getValue(state.values.length > 1 ? state.values : (state.values[0] ?? 0))
                  : undefined}
            </HeroUISlider.Output>
          ) : null}
          <HeroUISlider.Track
            className={combineClasses(
              orientation === 'vertical' ? sizeClasses.verticalTrack : sizeClasses.horizontalTrack,
              classNames?.track,
            )}
          >
            {({ state }) => (
              <>
                <HeroUISlider.Fill className={combineClasses(mapColor(color), classNames?.filler)} />
                {stepValues.length ? (
                  <span
                    aria-hidden="true"
                    className={combineClasses(
                      'pointer-events-none absolute flex justify-between',
                      orientation === 'vertical'
                        ? 'inset-y-0 left-1/2 -translate-x-1/2 flex-col-reverse'
                        : 'inset-x-0 top-1/2 -translate-y-1/2',
                    )}
                    data-slot="slider-steps"
                  >
                    {stepValues.map((stepValue) => (
                      <span
                        key={stepValue}
                        className={combineClasses(
                          'block shrink-0 rounded-full bg-surface ring-1 ring-border',
                          sizeClasses.step,
                          classNames?.step,
                        )}
                        data-slot="slider-step"
                        data-value={stepValue}
                      />
                    ))}
                  </span>
                ) : null}
                {hideThumb ? null : state.values.map((_, index) => (
                  <HeroUISlider.Thumb
                    key={index}
                    index={index}
                    className={combineClasses(
                      showTooltip && 'group/slider-thumb',
                      orientation === 'vertical' ? sizeClasses.verticalThumb : sizeClasses.horizontalThumb,
                      classNames?.thumb,
                    )}
                  >
                    {renderThumb?.({ index })}
                    {showTooltip ? (
                      <span
                        className={combineClasses(
                          'pointer-events-none invisible absolute z-20 whitespace-nowrap rounded-lg bg-overlay px-2 py-1 text-xs text-overlay-foreground opacity-0 shadow-lg transition-opacity',
                          'group-hover/slider-thumb:visible group-focus-within/slider-thumb:visible group-data-[dragging=true]/slider-thumb:visible',
                          'group-hover/slider-thumb:opacity-100 group-focus-within/slider-thumb:opacity-100 group-data-[dragging=true]/slider-thumb:opacity-100',
                          orientation === 'vertical'
                            ? 'left-full top-1/2 ml-2 -translate-y-1/2'
                            : 'bottom-full left-1/2 mb-2 -translate-x-1/2',
                        )}
                        data-slot="slider-tooltip"
                        role="tooltip"
                      >
                        {getTooltipValue
                          ? getTooltipValue(toSliderValue(state.values))
                          : state.getThumbValueLabel(index)}
                      </span>
                    ) : null}
                  </HeroUISlider.Thumb>
                ))}
              </>
            )}
          </HeroUISlider.Track>
          {marks?.length ? (
            <HeroUISlider.Marks className={combineClasses('flex justify-between', sizeClasses.marks, classNames?.mark)}>
              {marks.map((mark) => (
                <span key={mark.value}>{mark.label ?? mark.value}</span>
              ))}
            </HeroUISlider.Marks>
          ) : null}
        </>
      )}
    </HeroUISlider>
  );
}

const SLIDER_SIZE_CLASSES: Record<NonNullable<SliderProps['size']>, {
  base?: string;
  horizontalThumb?: string;
  horizontalTrack?: string;
  label?: string;
  marks?: string;
  output?: string;
  step?: string;
  verticalThumb?: string;
  verticalTrack?: string;
}> = {
  sm: {
    base: 'gap-0.5',
    horizontalThumb: 'w-6 [&::after]:h-3 [&::after]:w-5',
    horizontalTrack: 'h-4 border-x-[0.75rem]',
    label: 'text-xs',
    marks: 'text-xs',
    output: 'text-xs',
    step: 'size-1',
    verticalThumb: 'h-6 [&::after]:h-5 [&::after]:w-3',
    verticalTrack: 'w-4 border-y-[0.75rem]',
  },
  md: {
    marks: 'text-xs',
    step: 'size-1.5',
  },
  lg: {
    base: 'gap-2',
    horizontalThumb: 'w-8 [&::after]:h-5 [&::after]:w-7',
    horizontalTrack: 'h-6 border-x-[1rem]',
    label: 'text-base',
    marks: 'text-sm',
    output: 'text-base',
    step: 'size-2',
    verticalThumb: 'h-8 [&::after]:h-7 [&::after]:w-5',
    verticalTrack: 'w-6 border-y-[1rem]',
  },
};

function buildStepValues(minValue: number, maxValue: number, step: number): number[] {
  if (!Number.isFinite(minValue) || !Number.isFinite(maxValue) || !Number.isFinite(step) || step <= 0 || maxValue < minValue) {
    return [];
  }

  const stepCount = Math.floor((maxValue - minValue) / step + Number.EPSILON) + 1;

  return Array.from({ length: stepCount }, (_, index) => Number((minValue + index * step).toPrecision(12)));
}

function toSliderValue(values: readonly number[]): SliderValue {
  return values.length > 1 ? [...values] : (values[0] ?? 0);
}
