// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ReactNode } from 'react';
import { Label, Slider as HeroUISlider } from '@heroui/react';

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
  getTooltipValue: _getTooltipValue,
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
  showSteps: _showSteps,
  showTooltip: _showTooltip,
  size: _size,
  ...props
}: SliderProps) {
  const labelNode = renderLabel ? renderLabel({ children: label }) : label;

  return (
    <HeroUISlider
      {...props}
      className={combineClasses(classNames?.base, className)}
    >
      {children ?? (
        <>
          {labelNode ? <Label className={classNames?.label}>{labelNode}</Label> : null}
          {!hideValue ? (
            <HeroUISlider.Output className={combineClasses(classNames?.output, classNames?.value)}>
              {renderValue
                ? ({ state }) => renderValue({ children: state.values.map((_, index) => state.getThumbValueLabel(index)).join(' - ') })
                : getValue
                  ? ({ state }) => getValue(state.values.length > 1 ? state.values : (state.values[0] ?? 0))
                  : undefined}
            </HeroUISlider.Output>
          ) : null}
          <HeroUISlider.Track className={classNames?.track}>
            {({ state }) => (
              <>
                <HeroUISlider.Fill className={combineClasses(mapColor(color), classNames?.filler)} />
                {hideThumb ? null : state.values.map((_, index) => (
                  renderThumb ? (
                    <HeroUISlider.Thumb key={index} index={index} className={classNames?.thumb}>
                      {renderThumb({ index })}
                    </HeroUISlider.Thumb>
                  ) : (
                    <HeroUISlider.Thumb key={index} index={index} className={classNames?.thumb} />
                  )
                ))}
              </>
            )}
          </HeroUISlider.Track>
          {marks?.length ? (
            <HeroUISlider.Marks className={combineClasses('flex justify-between text-xs', classNames?.mark, classNames?.step)}>
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
