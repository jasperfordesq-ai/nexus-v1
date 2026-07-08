// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Meter as HeroUIMeter, type Meter as HeroUIMeterTypes } from '@heroui/react/meter';

export type MeterProps = HeroUIMeterTypes['Props'];
export type MeterOutputProps = HeroUIMeterTypes['OutputProps'];
export type MeterTrackProps = HeroUIMeterTypes['TrackProps'];
export type MeterFillProps = HeroUIMeterTypes['FillProps'];

export const Meter = HeroUIMeter;
export const MeterOutput = HeroUIMeter.Output;
export const MeterTrack = HeroUIMeter.Track;
export const MeterFill = HeroUIMeter.Fill;

