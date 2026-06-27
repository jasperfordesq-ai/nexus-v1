// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const TIMING_RE = /(\d{2}:\d{2}:\d{2},\d{3})\s+-->\s+(\d{2}:\d{2}:\d{2},\d{3})/;

export function parseSrtTimestamp(timestamp) {
  const match = /^(\d{2}):(\d{2}):(\d{2}),(\d{3})$/.exec(timestamp.trim());
  if (!match) throw new Error(`Invalid SRT timestamp: ${timestamp}`);
  const [, hours, minutes, seconds, millis] = match;
  return (
    Number(hours) * 60 * 60 * 1000 +
    Number(minutes) * 60 * 1000 +
    Number(seconds) * 1000 +
    Number(millis)
  );
}

export function formatSrtTimestamp(milliseconds) {
  const safeMs = Math.max(0, Math.round(milliseconds));
  const hours = Math.floor(safeMs / 3_600_000);
  const minutes = Math.floor((safeMs % 3_600_000) / 60_000);
  const seconds = Math.floor((safeMs % 60_000) / 1000);
  const millis = safeMs % 1000;
  return `${pad(hours, 2)}:${pad(minutes, 2)}:${pad(seconds, 2)},${pad(millis, 3)}`;
}

export function offsetSrtText(srtText, offsetMs) {
  return renderCues(parseCues(srtText).map((cue) => ({
    ...cue,
    startMs: cue.startMs + offsetMs,
    endMs: cue.endMs + offsetMs,
  })));
}

export function mergeSceneSrts(scenes) {
  const cues = scenes.flatMap(({ srt, startsAtMs }) =>
    parseCues(srt).map((cue) => ({
      ...cue,
      startMs: cue.startMs + startsAtMs,
      endMs: cue.endMs + startsAtMs,
    })),
  );
  return renderCues(cues);
}

export function singleCueSrt(text, durationMs) {
  return renderCues([{
    startMs: 0,
    endMs: Math.max(1000, Math.round(durationMs)),
    text: String(text).trim(),
  }]);
}

function parseCues(srtText) {
  return String(srtText)
    .replace(/\r\n/g, '\n')
    .trim()
    .split(/\n{2,}/)
    .filter(Boolean)
    .map((block) => {
      const lines = block.split('\n');
      const timingIndex = lines.findIndex((line) => TIMING_RE.test(line));
      if (timingIndex === -1) throw new Error(`Invalid SRT cue: ${block}`);
      const [, start, end] = TIMING_RE.exec(lines[timingIndex]) ?? [];
      return {
        startMs: parseSrtTimestamp(start),
        endMs: parseSrtTimestamp(end),
        text: lines.slice(timingIndex + 1).join('\n').trim(),
      };
    });
}

function renderCues(cues) {
  return cues
    .map((cue, index) => [
      String(index + 1),
      `${formatSrtTimestamp(cue.startMs)} --> ${formatSrtTimestamp(cue.endMs)}`,
      cue.text,
    ].join('\n'))
    .join('\n\n') + '\n';
}

function pad(value, length) {
  return String(value).padStart(length, '0');
}
