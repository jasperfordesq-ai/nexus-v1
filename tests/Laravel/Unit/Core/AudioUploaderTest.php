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
            base64_encode('small-data'),
            'audio/webm',
            301
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
