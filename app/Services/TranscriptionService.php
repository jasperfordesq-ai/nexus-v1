<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TranscriptionService — Voice message transcription and translation via OpenAI.
 *
 * Uses Whisper API for audio-to-text transcription and GPT-4o-mini for translation.
 */
class TranscriptionService
{
    /**
     * Transcribe an audio file using OpenAI Whisper API.
     *
     * @param string $audioFilePath Absolute path to the audio file on disk.
     * @return array{text: string, language: string}|null Returns transcript data or null on failure.
     */
    public static function transcribe(string $audioFilePath): ?array
    {
        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            Log::warning('TranscriptionService::transcribe — OpenAI API key not configured');
            return null;
        }

        if (!file_exists($audioFilePath)) {
            Log::warning('TranscriptionService::transcribe — Audio file not found', ['path' => $audioFilePath]);
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->attach('file', file_get_contents($audioFilePath), basename($audioFilePath))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model'           => 'whisper-1',
                    'response_format' => 'verbose_json',
                ]);

            if (!$response->successful()) {
                Log::error('TranscriptionService::transcribe — API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            return [
                'text'     => $data['text'] ?? '',
                'language' => $data['language'] ?? 'en',
            ];
        } catch (\Throwable $e) {
            Log::error('TranscriptionService::transcribe failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Translate text from one language to another using OpenAI chat completions.
     *
     * @param string $text         The text to translate.
     * @param string $fromLanguage Source language (ISO 639-1 code or name).
     * @param string $toLanguage   Target language (ISO 639-1 code or name).
     * @return string|null Translated text, or null on failure.
     */
    public static function translate(string $text, string $fromLanguage, string $toLanguage): ?string
    {
        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            Log::warning('TranscriptionService::translate — OpenAI API key not configured');
            return null;
        }

        if (empty(trim($text))) {
            return '';
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'      => 'gpt-4o-mini',
                    'messages'   => [
                        [
                            'role'    => 'system',
                            'content' => "Translate the following text from {$fromLanguage} to {$toLanguage}. Return only the translation, nothing else.",
                        ],
                        [
                            'role'    => 'user',
                            'content' => $text,
                        ],
                    ],
                    'max_tokens' => 2048,
                ]);

            if (!$response->successful()) {
                Log::error('TranscriptionService::translate — API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json('choices.0.message.content', '');
        } catch (\Throwable $e) {
            Log::error('TranscriptionService::translate failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
