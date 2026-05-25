// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
//
// Self-contained Jest mock for react-native-worklets.
// Prevents NativeWorklets native-module initialisation from running in Jest.

'use strict';

const NOOP = () => {};
const ID = (v) => v;

const RuntimeKind = {
  ReactNative: 'ReactNative',
  UI: 'UI',
  Default: 'Default',
};

const WorkletAPI = {
  isShareableRef: () => true,
  makeShareable: ID,
  makeShareableCloneOnUIRecursive: ID,
  makeShareableCloneRecursive: ID,
  shareableMappingCache: new Map(),
  getStaticFeatureFlag: () => false,
  setDynamicFeatureFlag: NOOP,
  isSynchronizable: () => false,
  getRuntimeKind: () => RuntimeKind.ReactNative,
};

// Globals expected by worklets (set on globalThis during real init)
if (typeof globalThis._WORKLET === 'undefined') globalThis._WORKLET = false;
if (typeof globalThis.__RUNTIME_KIND === 'undefined') globalThis.__RUNTIME_KIND = RuntimeKind.ReactNative;

module.exports = {
  RuntimeKind,
  runOnUI: (fn) => fn,
  runOnJS: (fn) => fn,
  executeOnUIRuntimeSync: (fn) => fn,
  createSerializable: ID,
  isWorkletFunction: () => false,
  makeShareable: ID,
  makeShareableCloneOnUIRecursive: ID,
  makeShareableCloneRecursive: ID,
  isShareableRef: () => true,
  callMicrotasks: NOOP,
  WorkletAPI,
  WorkletRuntime: class WorkletRuntime {
    constructor() {}
  },
  createWorkletRuntime: () => ({}),
  runOnRuntime: NOOP,
  createWorklet: (fn) => fn,
  'default': WorkletAPI,
};
