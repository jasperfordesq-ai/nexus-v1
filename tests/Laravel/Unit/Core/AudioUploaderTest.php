<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\AudioUploader;
use PHPUnit\Framework\TestCase;

class AudioUploaderTest extends TestCase
{
    /** Sandbox DOCUMENT_ROOT so uploadFromBase64() writes land in a temp dir.
     *  In CLI/CI $_SERVER['DOCUMENT_ROOT'] is empty, so the uploader resolved
     *  its target to /uploads/... at the filesystem root — mkdir/file_put_contents
     *  emitted PHP warnings that failed the suite (failOnWarning). */
    private string $docRoot;
    private ?string $originalDocRoot = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docRoot = sys_get_temp_dir() . '/audio-uploader-test-' . uniqid();
        mkdir($this->docRoot, 0755, true);
        $this->originalDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $this->docRoot;
    }

    protected function tearDown(): void
    {
        if ($this->originalDocRoot === null) {
            unset($_SERVER['DOCUMENT_ROOT']);
        } else {
            $_SERVER['DOCUMENT_ROOT'] = $this->originalDocRoot;
        }
        if (is_dir($this->docRoot)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->docRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->docRoot);
        }
        parent::tearDown();
    }

    // -------------------------------------------------------
    // upload() — validation
    // -------------------------------------------------------

    public function test_upload_throws_when_no_file_provided(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No audio file provided');
        AudioUploader::upload(['tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 0]);
    }

    public function test_upload_throws_on_upload_error(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upload error code');
        AudioUploader::upload([
            'tmp_name' => '/tmp/test.webm',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 100,
        ]);
    }

    public function test_upload_throws_when_file_too_large(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('too large');
        AudioUploader::upload([
            'tmp_name' => '/tmp/test.webm',
            'error' => UPLOAD_ERR_OK,
            'size' => 11 * 1024 * 1024, // 11MB > 10MB limit
        ]);
    }

    public function test_upload_throws_when_duration_too_long(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('too long');
        AudioUploader::upload([
            'tmp_name' => '/tmp/test.webm',
            'error' => UPLOAD_ERR_OK,
            'size' => 1000,
        ], 301); // 301s > 300s limit
    }

    // -------------------------------------------------------
    // uploadFromBase64() — validation
    // -------------------------------------------------------

    public function test_uploadFromBase64_throws_with_invalid_mime(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid audio format');
        AudioUploader::uploadFromBase64(
            base64_encode('fake-data'),
            'application/pdf'
        );
    }

    public function test_uploadFromBase64_throws_with_invalid_base64(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid base64');
        AudioUploader::uploadFromBase64('!!!invalid!!!', 'audio/webm');
    }

    public function test_uploadFromBase64_throws_when_too_large(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('too large');
        // Create a string > 10MB
        $large = str_repeat('A', 11 * 1024 * 1024);
        AudioUploader::uploadFromBase64(base64_encode($large), 'audio/webm');
    }

    public function test_uploadFromBase64_throws_when_duration_too_long(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('too long');

        AudioUploader::uploadFromBase64(
            base64_encode(self::mp4Bytes('M4A ', 'M4A mp42isom')),
            'audio/mp4',
            301,
        );
    }

    public function test_uploadFromBase64_strips_codec_from_mime(): void
    {
        // audio/webm;codecs=opus should be treated as audio/webm (valid)
        // This test verifies the codec stripping works; it will still fail
        // at file_put_contents due to missing dir, but won't throw "Invalid audio format"
        try {
            AudioUploader::uploadFromBase64(
                base64_encode('test-audio-data'),
                'audio/webm;codecs=opus',
                5
            );
        } catch (\Exception $e) {
            // The error should NOT be about invalid format — it should be about saving
            $this->assertStringNotContainsString('Invalid audio format', $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // uploadFromBase64() — mobile MPEG-4 recordings (regression)
    // -------------------------------------------------------
    //
    // The Expo mobile app records voice messages as MPEG-4/AAC (.m4a) on both
    // Android and iOS. PHP's finfo content-sniffing reports those containers as
    // audio/x-m4a (M4A brand), video/mp4 (Android MediaRecorder's isom/mp42
    // brands — even for audio-only files), or audio/x-hx-aac-adts (raw AAC).
    // None of these were in the allowlist, so every mobile voice message was
    // rejected with "Invalid audio format" (prod errors 2026-06-03/2026-06-11).

    /** Minimal MPEG-4 'ftyp' box with the given brand, padded so finfo can sniff it */
    private static function mp4Bytes(string $brand, string $compat): string
    {
        return "\x00\x00\x00\x18ftyp{$brand}\x00\x00\x00\x00{$compat}" . str_repeat("\x00", 256);
    }

    /** @return array<string, array{string, string}> claimed MIME + raw content */
    public static function mobileAudioProvider(): array
    {
        return [
            'M4A-branded MPEG-4 (audio/x-m4a)' => ['audio/mp4', self::mp4Bytes('M4A ', 'M4A mp42isom')],
            'Android MediaRecorder mp42 (video/mp4)' => ['audio/mp4', self::mp4Bytes('mp42', 'mp42isom')],
            'Android MediaRecorder isom (video/mp4)' => ['audio/mp4', self::mp4Bytes('isom', 'isomiso2mp41')],
            'raw AAC ADTS stream' => ['audio/aac', "\xFF\xF1\x50\x80\x00\x1F\xFC" . str_repeat("\x00", 256)],
        ];
    }

    /** @dataProvider mobileAudioProvider */
    public function test_uploadFromBase64_accepts_mobile_mpeg4_recordings(string $claimedMime, string $content): void
    {
        // DOCUMENT_ROOT is sandboxed in setUp, so the full pipeline — format
        // validation, content sniffing AND persistence — must succeed.
        $result = AudioUploader::uploadFromBase64(base64_encode($content), $claimedMime, 5);

        $this->assertStringStartsWith('/uploads/', $result['url']);
        $this->assertSame($this->docRoot . $result['url'], $result['local_path']);
        $this->assertFileExists($result['local_path']);
        $this->assertSame($content, file_get_contents($result['local_path']));
    }

    public function test_resolveTenantVoiceFilePath_accepts_only_canonical_server_issued_file(): void
    {
        $voiceDir = $this->docRoot . '/uploads/42/voice_messages';
        mkdir($voiceDir, 0755, true);
        $filename = 'voice_' . str_repeat('a', 32) . '.webm';
        $path = $voiceDir . '/' . $filename;
        file_put_contents($path, 'audio');

        $this->assertSame(
            realpath($path),
            AudioUploader::resolveTenantVoiceFilePath('/uploads/42/voice_messages/' . $filename, 42),
        );
        $this->assertTrue(AudioUploader::isTenantVoiceFile('/uploads/42/voice_messages/' . $filename, 42));
        $this->assertNull(AudioUploader::resolveTenantVoiceFilePath('/uploads/42/voice_messages/' . $filename, 43));
    }

    public function test_resolveTenantVoiceFilePath_rejects_traversal_and_free_form_names(): void
    {
        $voiceDir = $this->docRoot . '/uploads/42/voice_messages';
        mkdir($voiceDir, 0755, true);
        file_put_contents($voiceDir . '/recording.webm', 'audio');

        $this->assertNull(AudioUploader::resolveTenantVoiceFilePath(
            '/uploads/42/voice_messages/%2e%2e%2foutside.webm',
            42,
        ));
        $this->assertNull(AudioUploader::resolveTenantVoiceFilePath(
            '/uploads/42/voice_messages/recording.webm',
            42,
        ));
        $this->assertNull(AudioUploader::resolveTenantVoiceFilePath(
            'https://attacker.example/uploads/42/voice_messages/voice_' . str_repeat('a', 32) . '.webm',
            42,
        ));
    }

    public function test_resolveTenantVoiceFilePath_rejects_symlink_escape(): void
    {
        $voiceDir = $this->docRoot . '/uploads/42/voice_messages';
        mkdir($voiceDir, 0755, true);
        $outside = $this->docRoot . '/outside.webm';
        file_put_contents($outside, 'audio');
        $link = $voiceDir . '/voice_' . str_repeat('b', 32) . '.webm';
        $this->assertTrue(symlink($outside, $link));

        $this->assertNull(AudioUploader::resolveTenantVoiceFilePath(
            '/uploads/42/voice_messages/' . basename($link),
            42,
        ));
    }

    // -------------------------------------------------------
    // delete()
    // -------------------------------------------------------

    public function test_delete_returns_false_for_empty_url(): void
    {
        $this->assertFalse(AudioUploader::delete(''));
    }

    public function test_delete_returns_false_for_non_upload_url(): void
    {
        $this->assertFalse(AudioUploader::delete('/some/other/path'));
    }

    public function test_delete_returns_false_for_nonexistent_file(): void
    {
        $this->assertFalse(AudioUploader::delete('/uploads/nonexistent/voice_messages/test.webm'));
    }
}
