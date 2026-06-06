// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const fs = require('fs');
const path = require('path');

const appDir = __dirname;
const projectDir = path.resolve(appDir, '..');

function toProjectPath(filePath) {
  return path.relative(projectDir, filePath).replace(/\\/g, '/');
}

function walkRoutes(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) return walkRoutes(fullPath);
    if (!entry.name.endsWith('.tsx')) return [];
    if (entry.name.endsWith('.test.tsx')) return [];
    if (entry.name === '_layout.tsx') return [];
    return [fullPath];
  });
}

function routeKind(route) {
  if (route.includes('/new-') || route.includes('/edit-')) return 'create/edit';
  if (
    route.includes('-detail')
    || route.includes('member-profile')
    || route.includes('kb-article')
    || route.includes('blog-post')
  ) {
    return 'detail';
  }
  return 'other';
}

function scopedRoutes() {
  return walkRoutes(appDir)
    .map((filePath) => {
      const source = fs.readFileSync(filePath, 'utf8');
      return {
        route: toProjectPath(filePath),
        source,
        kind: routeKind(toProjectPath(filePath)),
      };
    })
    .filter((route) => route.kind === 'create/edit' || route.kind === 'detail');
}

function usesNativeFrame(source) {
  return /SafeAreaView|KeyboardAvoidingView|ScrollView|FlatList/.test(source);
}

function hasExplicitNativeFlexFrame(source) {
  return (
    /<SafeAreaView[\s\S]{0,280}style=\{\{[^}]*flex:\s*1/.test(source)
    || /<KeyboardAvoidingView[\s\S]{0,280}style=\{\{[^}]*flex:\s*1/.test(source)
  );
}

function hasScrollableInputs(source) {
  return /ScrollView|FlatList/.test(source);
}

function usesFullScreenContentContainerClassName(source) {
  return /<ScrollView(?![^>]*horizontal)[^>]*contentContainerClassName=/.test(source);
}

describe('native create/detail route layout coverage', () => {
  it('keeps create/detail routes on explicit React Native flex frames for Android release builds', () => {
    const riskyRoutes = scopedRoutes()
      .filter((route) => usesNativeFrame(route.source))
      .filter((route) => !hasExplicitNativeFlexFrame(route.source))
      .map((route) => route.route)
      .sort();

    expect(riskyRoutes).toEqual([]);
  });

  it('keeps create/edit routes with scrollable inputs inside KeyboardAvoidingView', () => {
    const missingKeyboardFrame = scopedRoutes()
      .filter((route) => route.kind === 'create/edit')
      .filter((route) => hasScrollableInputs(route.source))
      .filter((route) => !/KeyboardAvoidingView/.test(route.source))
      .map((route) => route.route)
      .sort();

    expect(missingKeyboardFrame).toEqual([]);
  });

  it('uses native contentContainerStyle for full-screen vertical ScrollView bodies', () => {
    const riskyScrollViews = scopedRoutes()
      .filter((route) => usesFullScreenContentContainerClassName(route.source))
      .map((route) => route.route)
      .sort();

    expect(riskyScrollViews).toEqual([]);
  });

  it('keeps auxiliary hub routes on explicit native flex frames for Android release builds', () => {
    const routes = [
      path.join(projectDir, 'app/(tabs)/explore.tsx'),
      path.join(projectDir, 'app/(modals)/support.tsx'),
    ];
    const riskyRoutes = routes
      .filter((routePath) => {
        const source = fs.readFileSync(routePath, 'utf8');
        return !hasExplicitNativeFlexFrame(source) || !/<ScrollView[\s\S]{0,220}style=\{\{[^}]*flex:\s*1/.test(source);
      })
      .map((routePath) => toProjectPath(routePath))
      .sort();

    expect(riskyRoutes).toEqual([]);
  });

  it('keeps the chat modal on explicit native flex frames for Android release builds', () => {
    const source = fs.readFileSync(path.join(projectDir, 'app/(modals)/chat.tsx'), 'utf8');

    expect(/<SafeAreaView[\s\S]{0,280}style=\{\{[^}]*flex:\s*1/.test(source)).toBe(true);
    expect(/<KeyboardAvoidingView[\s\S]{0,280}style=\{\{[^}]*flex:\s*1/.test(source)).toBe(true);
  });
});
