// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import grapesAr from 'grapesjs/locale/ar.js';
import grapesDe from 'grapesjs/locale/de.js';
import grapesEn from 'grapesjs/locale/en.js';
import grapesEs from 'grapesjs/locale/es.js';
import grapesFr from 'grapesjs/locale/fr.js';
import grapesIt from 'grapesjs/locale/it.js';
import grapesNl from 'grapesjs/locale/nl.js';
import grapesPl from 'grapesjs/locale/pl.js';
import grapesPt from 'grapesjs/locale/pt.js';
import mjmlDe from 'grapesjs-mjml/locale/de.js';
import mjmlEn from 'grapesjs-mjml/locale/en.js';
import mjmlEs from 'grapesjs-mjml/locale/es.js';
import mjmlFr from 'grapesjs-mjml/locale/fr.js';
import mjmlNl from 'grapesjs-mjml/locale/nl.js';
import mjmlPl from 'grapesjs-mjml/locale/pl.js';
import mjmlPt from 'grapesjs-mjml/locale/pt.js';

export type GrapesJsMessages = Record<string, unknown>;
export type GrapesJsTranslate = (key: string) => string;

function isMessageObject(value: unknown): value is GrapesJsMessages {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

/** Vite and CommonJS can each add a `default` wrapper around package locales. */
function unwrapMessages(value: unknown): GrapesJsMessages {
  let candidate = value;
  for (let depth = 0; depth < 3; depth += 1) {
    if (!isMessageObject(candidate) || !isMessageObject(candidate.default)) break;
    candidate = candidate.default;
  }
  return isMessageObject(candidate) ? candidate : {};
}

const CORE_LOCALES: Record<string, GrapesJsMessages> = {
  ar: unwrapMessages(grapesAr),
  de: unwrapMessages(grapesDe),
  en: unwrapMessages(grapesEn),
  es: unwrapMessages(grapesEs),
  fr: unwrapMessages(grapesFr),
  it: unwrapMessages(grapesIt),
  nl: unwrapMessages(grapesNl),
  pl: unwrapMessages(grapesPl),
  pt: unwrapMessages(grapesPt),
};

const MJML_LOCALES: Record<string, GrapesJsMessages> = {
  de: unwrapMessages(mjmlDe),
  en: unwrapMessages(mjmlEn),
  es: unwrapMessages(mjmlEs),
  fr: unwrapMessages(mjmlFr),
  nl: unwrapMessages(mjmlNl),
  pl: unwrapMessages(mjmlPl),
  pt: unwrapMessages(mjmlPt),
};

export function normalizeGrapesJsLocale(language?: string): string {
  return language?.trim().replace('_', '-').split('-')[0]?.toLowerCase() || 'en';
}

function projectCoreMessages(t: GrapesJsTranslate): GrapesJsMessages {
  const value = (key: string) => t(`grapesjs_core.${key}`);

  return {
    assetManager: {
      addButton: value('asset_manager.add_image'),
      inputPlh: value('asset_manager.image_url_placeholder'),
      modalTitle: value('asset_manager.select_image'),
      uploadTitle: value('asset_manager.upload_prompt'),
    },
    blockManager: { labels: {}, categories: {} },
    domComponents: {
      names: {
        '': value('components.box'),
        wrapper: value('components.body'),
        text: value('components.text'),
        comment: value('components.comment'),
        image: value('components.image'),
        video: value('components.video'),
        label: value('components.label'),
        link: value('components.link'),
        map: value('components.map'),
        tfoot: value('components.table_foot'),
        tbody: value('components.table_body'),
        thead: value('components.table_head'),
        table: value('components.table'),
        row: value('components.table_row'),
        cell: value('components.table_cell'),
      },
    },
    deviceManager: {
      device: value('devices.device'),
      devices: {
        desktop: value('devices.desktop'),
        tablet: value('devices.tablet'),
        mobileLandscape: value('devices.mobile_landscape'),
        mobilePortrait: value('devices.mobile_portrait'),
      },
    },
    panels: {
      buttons: {
        titles: {
          preview: value('panels.preview'),
          fullscreen: value('panels.fullscreen'),
          'sw-visibility': value('panels.view_components'),
          'export-template': value('panels.view_code'),
          'open-sm': value('panels.open_style_manager'),
          'open-tm': value('panels.settings'),
          'open-layers': value('panels.open_layer_manager'),
          'open-blocks': value('panels.open_blocks'),
        },
      },
    },
    selectorManager: {
      label: value('selectors.classes'),
      selected: value('selectors.selected'),
      emptyState: value('selectors.empty_state'),
      states: {
        hover: value('selectors.hover'),
        active: value('selectors.click'),
        'nth-of-type(2n)': value('selectors.even_odd'),
      },
    },
    styleManager: {
      empty: value('styles.empty'),
      layer: value('styles.layer'),
      fileButton: value('styles.images'),
      sectors: {
        general: value('styles.sectors.general'),
        layout: value('styles.sectors.layout'),
        typography: value('styles.sectors.typography'),
        decorations: value('styles.sectors.decorations'),
        extra: value('styles.sectors.extra'),
        flex: value('styles.sectors.flex'),
        dimension: value('styles.sectors.dimension'),
      },
      properties: {
        'text-shadow-h': 'X',
        'text-shadow-v': 'Y',
        'text-shadow-blur': value('styles.properties.blur'),
        'text-shadow-color': value('styles.properties.color'),
        'box-shadow-h': 'X',
        'box-shadow-v': 'Y',
        'box-shadow-blur': value('styles.properties.blur'),
        'box-shadow-spread': value('styles.properties.spread'),
        'box-shadow-color': value('styles.properties.color'),
        'box-shadow-type': value('styles.properties.type'),
        'margin-top-sub': value('styles.properties.top'),
        'margin-right-sub': value('styles.properties.right'),
        'margin-bottom-sub': value('styles.properties.bottom'),
        'margin-left-sub': value('styles.properties.left'),
        'padding-top-sub': value('styles.properties.top'),
        'padding-right-sub': value('styles.properties.right'),
        'padding-bottom-sub': value('styles.properties.bottom'),
        'padding-left-sub': value('styles.properties.left'),
        'border-width-sub': value('styles.properties.width'),
        'border-style-sub': value('styles.properties.style'),
        'border-color-sub': value('styles.properties.color'),
        'border-top-left-radius-sub': value('styles.properties.top_left'),
        'border-top-right-radius-sub': value('styles.properties.top_right'),
        'border-bottom-right-radius-sub': value('styles.properties.bottom_right'),
        'border-bottom-left-radius-sub': value('styles.properties.bottom_left'),
        'transform-rotate-x': value('styles.properties.rotate_x'),
        'transform-rotate-y': value('styles.properties.rotate_y'),
        'transform-rotate-z': value('styles.properties.rotate_z'),
        'transform-scale-x': value('styles.properties.scale_x'),
        'transform-scale-y': value('styles.properties.scale_y'),
        'transform-scale-z': value('styles.properties.scale_z'),
        'transition-property-sub': value('styles.properties.property'),
        'transition-duration-sub': value('styles.properties.duration'),
        'transition-timing-function-sub': value('styles.properties.timing'),
        'background-image-sub': value('styles.properties.image'),
        'background-repeat-sub': value('styles.properties.repeat'),
        'background-position-sub': value('styles.properties.position'),
        'background-attachment-sub': value('styles.properties.attachment'),
        'background-size-sub': value('styles.properties.size'),
      },
    },
    traitManager: {
      empty: value('traits.empty'),
      label: value('traits.component_settings'),
      categories: {},
      traits: {
        labels: {},
        attributes: {
          id: { placeholder: value('traits.text_placeholder') },
          alt: { placeholder: value('traits.text_placeholder') },
          title: { placeholder: value('traits.text_placeholder') },
          href: { placeholder: value('traits.url_placeholder') },
        },
        options: {
          target: {
            false: value('traits.this_window'),
            _blank: value('traits.new_window'),
          },
        },
      },
    },
    storageManager: { recover: value('storage.recover_unsaved') },
  };
}

function projectMjmlMessages(t: GrapesJsTranslate): GrapesJsMessages {
  const value = (key: string) => t(`grapesjs_mjml.${key}`);

  return {
    'grapesjs-mjml': {
      category: '',
      panels: {
        buttons: {
          undo: value('panels.undo'),
          redo: value('panels.redo'),
          desktop: value('panels.desktop'),
          tablet: value('panels.tablet'),
          mobile: value('panels.mobile'),
          import: value('panels.import_mjml'),
        },
        import: {
          title: value('panels.import_mjml'),
          button: value('panels.import'),
          label: '',
        },
        export: { title: value('panels.export_mjml') },
      },
      components: {
        names: {
          body: value('components.body'),
          button: value('components.button'),
          column: value('components.column'),
          oneColumn: value('components.one_column'),
          twoColumn: value('components.two_columns'),
          threeColumn: value('components.three_columns'),
          divider: value('components.divider'),
          group: value('components.group'),
          hero: value('components.hero'),
          image: value('components.image'),
          navBar: value('components.navbar'),
          navLink: value('components.navbar_link'),
          section: value('components.section'),
          socialGroup: value('components.social_group'),
          socialElement: value('components.social_element'),
          spacer: value('components.spacer'),
          text: value('components.text'),
          wrapper: value('components.wrapper'),
          raw: value('components.raw'),
        },
      },
    },
  };
}

export function getGrapesJsCoreMessages(
  language?: string,
  t?: GrapesJsTranslate,
): GrapesJsMessages {
  const locale = normalizeGrapesJsLocale(language);
  const nativeMessages = CORE_LOCALES[locale];
  if (!t) return nativeMessages ?? {};
  return mergeGrapesJsMessages(projectCoreMessages(t), nativeMessages ?? {});
}

export function getMjmlMessages(language?: string, t?: GrapesJsTranslate): GrapesJsMessages {
  const locale = normalizeGrapesJsLocale(language);
  const nativeMessages = MJML_LOCALES[locale];
  if (!t) return nativeMessages ?? {};
  return mergeGrapesJsMessages(projectMjmlMessages(t), nativeMessages ?? {});
}

/** Recursively merge locale trees without mutating package-owned dictionaries. */
export function mergeGrapesJsMessages(...sources: GrapesJsMessages[]): GrapesJsMessages {
  const result: GrapesJsMessages = {};

  for (const source of sources) {
    for (const [key, value] of Object.entries(source)) {
      const existing = result[key];
      result[key] = isMessageObject(existing) && isMessageObject(value)
        ? mergeGrapesJsMessages(existing, value)
        : value;
    }
  }

  return result;
}

/** Translation text is inserted into builder-owned HTML/MJML templates. */
export function escapeBuilderText(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
