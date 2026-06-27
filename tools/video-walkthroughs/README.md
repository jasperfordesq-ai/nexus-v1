# Nexus Video Walkthroughs

Rerunnable pipeline for producing short member walkthrough videos from a JSON scene script.

## Prerequisites

- Root repo dependencies installed (`npm install` from the repository root).
- Local React frontend running at `http://127.0.0.1:5173`.
- Local API running at `http://127.0.0.1:8088`.
- `ffmpeg` and `ffprobe` on `PATH`.
- Edge TTS installed: `python -m pip install edge-tts`.
- A local member account for `hour-timebank`.

Credentials are read from `NEXUS_VIDEO_USER_EMAIL` / `NEXUS_VIDEO_USER_PASSWORD`, then the existing E2E variables (`E2E_USER_EMAIL` / `E2E_USER_PASSWORD`, `E2E_TEST_USER_EMAIL` / `E2E_TEST_USER_PASSWORD`). The final fallback is the repository's local seeded test member.

The recorder defaults to `NEXUS_VIDEO_BASE_URL` / `NEXUS_VIDEO_API_URL` when set, then falls back to the E2E URLs and finally `http://127.0.0.1:5173` / `http://127.0.0.1:8088`. It waits 30 seconds after the first feed render before trimming the start of the recording; override that with `NEXUS_VIDEO_START_SETTLE_MS` if your local browser captures faster or slower.

## Run

From the repository root:

```bash
node tools/video-walkthroughs/scripts/run.mjs content/video-01-getting-started.json
```

Outputs are written to `tools/video-walkthroughs/output/`:

- `video-01-getting-started.mp4`
- `video-01-getting-started.captioned.mp4`
- `video-01-getting-started.srt`
- stage manifests and intermediate files

The output folder is gitignored; do not commit generated media.

Generated scene audio is reused when the narration and voice settings still match, so a failed voiceover run can be restarted without redoing finished scenes. Use `--no-reuse` to force fresh audio.

## Voice Settings

Default voice engine is free Edge TTS:

```bash
node tools/video-walkthroughs/scripts/01-generate-voiceover.mjs content/video-01-getting-started.json --voice en-GB-SoniaNeural --rate -8%
```

Useful alternatives:

- `en-GB-RyanNeural`
- `en-GB-LibbyNeural`

OpenAI TTS is available only when explicitly selected and bills the OpenAI API:

```bash
OPENAI_API_KEY=... node tools/video-walkthroughs/scripts/run.mjs content/video-01-getting-started.json --engine openai
```

The OpenAI fallback uses `gpt-4o-mini-tts`, voice `fable`, and a British-accent instruction string by default.

If Edge TTS drops the connection, increase the bounded retry count:

```bash
node tools/video-walkthroughs/scripts/run.mjs content/video-01-getting-started.json --tts-retries 6
```

## Add Another Video

Create a new JSON file under `content/` with:

- `id`: output filename stem
- `title`
- `tenantSlug`
- `scenes[]`: `id`, `title`, `actions[]`, `narration`

Then run:

```bash
node tools/video-walkthroughs/scripts/run.mjs content/my-new-video.json
```

Add a scene handler to `scripts/02-record-walkthrough.mjs` when the new video needs browser actions not covered by the first walkthrough.
