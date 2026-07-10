// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readdirSync, readFileSync } from "node:fs";
import { join, relative } from "node:path";
import ts from "typescript";
import { describe, expect, it } from "vitest";

const SOURCE_ROOT = join(process.cwd(), "src");

const UNSAFE_HEADING_DESCENDANTS = new Set([
  "a",
  "button",
  "Button",
  "Checkbox",
  "div",
  "Dropdown",
  "form",
  "h1",
  "h2",
  "h3",
  "h4",
  "h5",
  "h6",
  "Input",
  "input",
  "Link",
  "nav",
  "ol",
  "p",
  "Radio",
  "Select",
  "select",
  "Switch",
  "table",
  "Tabs",
  "TextArea",
  "Textarea",
  "textarea",
  "ul",
]);

const AUDIT_NAMED_DIALOGS = [
  "broker/components/BrokerCommandPalette.tsx",
  "components/feed/ImageLightbox.tsx",
  "components/layout/QuickCreateMenu.tsx",
  "components/stories/StoryCreator.tsx",
  "components/stories/StoryViewer.tsx",
  "pages/groups/tabs/GroupMediaTab.tsx",
] as const;

function productionTsxFiles(directory: string): string[] {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) return productionTsxFiles(path);
    if (!entry.name.endsWith(".tsx") || entry.name.includes(".test."))
      return [];
    return [path];
  });
}

function sourceFile(path: string): ts.SourceFile {
  return ts.createSourceFile(
    path,
    readFileSync(path, "utf8"),
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX,
  );
}

const PRODUCTION_SOURCE_FILES = productionTsxFiles(SOURCE_ROOT).map((path) => ({
  file: sourceFile(path),
  path,
}));

function jsxTagName(
  node: ts.JsxElement | ts.JsxSelfClosingElement,
  file: ts.SourceFile,
): string {
  return ts.isJsxElement(node)
    ? node.openingElement.tagName.getText(file)
    : node.tagName.getText(file);
}

function topLevelExpressionTags(node: ts.Node, file: ts.SourceFile): string[] {
  if (ts.isJsxElement(node) || ts.isJsxSelfClosingElement(node)) {
    return [jsxTagName(node, file)];
  }

  const tags: string[] = [];
  ts.forEachChild(node, (child) =>
    tags.push(...topLevelExpressionTags(child, file)),
  );
  return tags;
}

function directChildTags(header: ts.JsxElement, file: ts.SourceFile): string[] {
  return header.children.flatMap((child) => {
    if (ts.isJsxElement(child) || ts.isJsxSelfClosingElement(child)) {
      return [jsxTagName(child, file)];
    }
    if (ts.isJsxExpression(child) && child.expression) {
      return topLevelExpressionTags(child.expression, file);
    }
    return [];
  });
}

function descendantTags(node: ts.Node, file: ts.SourceFile): string[] {
  const tags: string[] = [];
  ts.forEachChild(node, (child) => {
    if (ts.isJsxElement(child) || ts.isJsxSelfClosingElement(child)) {
      tags.push(jsxTagName(child, file));
    }
    tags.push(...descendantTags(child, file));
  });
  return tags;
}

describe("Modal production contracts", () => {
  it("requires explicit ModalHeading composition for complex headers", () => {
    const failures: string[] = [];

    for (const { file, path } of PRODUCTION_SOURCE_FILES) {
      const visit = (node: ts.Node) => {
        if (
          ts.isJsxElement(node) &&
          node.openingElement.tagName.getText(file) === "ModalHeader"
        ) {
          const tags = directChildTags(node, file);
          const hasComplexContent = tags.some((tag) =>
            UNSAFE_HEADING_DESCENDANTS.has(tag),
          );
          if (hasComplexContent && !tags.includes("ModalHeading")) {
            const { line } = file.getLineAndCharacterOfPosition(
              node.getStart(file),
            );
            failures.push(
              `${relative(SOURCE_ROOT, path)}:${line + 1} (${tags.join(", ")})`,
            );
          }
        }
        ts.forEachChild(node, visit);
      };
      visit(file);
    }

    expect(failures).toEqual([]);
  });

  it("keeps block and interactive content out of ModalHeading", () => {
    const failures: string[] = [];

    for (const { file, path } of PRODUCTION_SOURCE_FILES) {
      const visit = (node: ts.Node) => {
        if (
          ts.isJsxElement(node) &&
          node.openingElement.tagName.getText(file) === "ModalHeading"
        ) {
          const unsafeTags = descendantTags(node, file).filter((tag) =>
            UNSAFE_HEADING_DESCENDANTS.has(tag),
          );
          if (unsafeTags.length > 0) {
            const { line } = file.getLineAndCharacterOfPosition(
              node.getStart(file),
            );
            failures.push(
              `${relative(SOURCE_ROOT, path)}:${line + 1} (${unsafeTags.join(", ")})`,
            );
          }
        }
        ts.forEachChild(node, visit);
      };
      visit(file);
    }

    expect(failures).toEqual([]);
  });

  it.each(AUDIT_NAMED_DIALOGS)(
    "%s supplies a translated dialog label",
    (relativePath) => {
      const path = join(SOURCE_ROOT, relativePath);
      const file = sourceFile(path);
      const labels: boolean[] = [];
      const visit = (node: ts.Node) => {
        const opening = ts.isJsxElement(node)
          ? node.openingElement
          : ts.isJsxSelfClosingElement(node)
            ? node
            : null;
        if (opening?.tagName.getText(file) === "ModalContent") {
          labels.push(
            opening.attributes.properties.some(
              (attribute) =>
                ts.isJsxAttribute(attribute) &&
                ["aria-label", "aria-labelledby"].includes(
                  attribute.name.getText(file),
                ),
            ),
          );
        }
        ts.forEachChild(node, visit);
      };
      visit(file);

      expect(labels).not.toHaveLength(0);
      expect(labels.every(Boolean)).toBe(true);
    },
  );
});
