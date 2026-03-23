// Infrastructure: reads untranslated.json, applies translation maps, writes files
const fs = require('fs');
const path = require('path');
const localesDir = path.join(__dirname, '..');

function setNestedValue(obj, flatKey, value) {
  const parts = flatKey.split('.');
  let current = obj;
  for (let i = 0; i < parts.length - 1; i++) {
    if (!current[parts[i]] || typeof current[parts[i]] !== 'object') {
      current[parts[i]] = {};
    }
    current = current[parts[i]];
  }
  current[parts[parts.length - 1]] = value;
}

function applyTranslations(lang, translationMap) {
  const untranslated = JSON.parse(fs.readFileSync(path.join(__dirname, 'untranslated.json'), 'utf8'));
  const langData = untranslated[lang];
  if (!langData) { console.log(`No untranslated data for ${lang}`); return; }

  let applied = 0, skipped = 0, missing = 0;

  for (const [file, keys] of Object.entries(langData)) {
    const filePath = path.join(localesDir, lang, file);
    const json = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    let fileApplied = 0;

    for (const [flatKey, enValue] of Object.entries(keys)) {
      const translation = translationMap[enValue];
      if (translation && translation !== enValue) {
        setNestedValue(json, flatKey, translation);
        fileApplied++;
        applied++;
      } else if (!translation) {
        missing++;
      } else {
        skipped++; // same as English, intentionally kept
      }
    }

    if (fileApplied > 0) {
      fs.writeFileSync(filePath, JSON.stringify(json, null, 2) + '\n');
    }
  }

  console.log(`${lang}: applied=${applied}, skipped=${skipped}, missing=${missing}`);
}

module.exports = { applyTranslations };
