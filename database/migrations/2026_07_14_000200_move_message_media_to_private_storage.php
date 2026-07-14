<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('message_attachments')) {
            DB::table('message_attachments')->orderBy('id')->each(function (object $row): void {
                $source = $this->legacyAttachmentSource($row);
                if ($source === null) return;
                [$filename, $sourcePath] = $source;
                $relative = "message-media/{$row->tenant_id}/attachments/{$filename}";
                if ($this->move($sourcePath, storage_path('app/private/' . $relative))) {
                    DB::table('message_attachments')->where('id', $row->id)->update([
                        'file_path' => $relative,
                        'file_url' => $relative,
                    ]);
                }
            });
        }

        if (Schema::hasTable('messages')) {
            DB::table('messages')->whereNotNull('audio_url')->orderBy('id')->each(function (object $row): void {
                $source = $this->legacyVoiceSource($row);
                if ($source === null) return;
                [$filename, $sourcePath] = $source;
                $relative = "message-media/{$row->tenant_id}/voice/{$filename}";
                if ($this->move($sourcePath, storage_path('app/private/' . $relative))) {
                    DB::table('messages')->where('id', $row->id)->update(['audio_url' => $relative]);
                }
            });
        }
    }

    /** @return array{string, string}|null */
    private function legacyAttachmentSource(object $row): ?array
    {
        $legacy = str_replace('\\', '/', trim((string) ($row->file_path ?: $row->file_url)));
        $tenantPrefix = "/uploads/{$row->tenant_id}/message_attachments/";
        if (str_starts_with($legacy, $tenantPrefix)) {
            $filename = substr($legacy, strlen($tenantPrefix));
            return $this->safeLegacySource($filename, base_path('httpdocs/' . ltrim($legacy, '/')));
        }

        // Early production stored absolute paths such as
        // /var/www/html/src/Services/../../httpdocs/uploads/messages/msg_*.pdf.
        // Never trust or normalize the prefix: extract a bounded basename and
        // rebuild it under the one approved current public directory.
        if (preg_match('~(?:^|/)httpdocs/uploads/messages/([^/]+)\z~D', $legacy, $matches) === 1
            || preg_match('~\A/uploads/messages/([^/]+)\z~D', $legacy, $matches) === 1) {
            $filename = (string) $matches[1];
            return $this->safeLegacySource(
                $filename,
                base_path('httpdocs/uploads/messages/' . $filename),
            );
        }

        return null;
    }

    /** @return array{string, string}|null */
    private function legacyVoiceSource(object $row): ?array
    {
        $legacy = str_replace('\\', '/', trim((string) $row->audio_url));
        $tenantPrefix = "/uploads/{$row->tenant_id}/voice_messages/";
        if (str_starts_with($legacy, $tenantPrefix)) {
            $filename = substr($legacy, strlen($tenantPrefix));
            return $this->safeLegacySource($filename, base_path('httpdocs/' . ltrim($legacy, '/')));
        }

        $slug = (string) DB::table('tenants')->where('id', $row->tenant_id)->value('slug');
        if ($slug === '' || preg_match('/\A[a-z0-9][a-z0-9-]{0,99}\z/D', $slug) !== 1) return null;
        $slugPrefix = "/uploads/tenants/{$slug}/voice_messages/";
        if (! str_starts_with($legacy, $slugPrefix)) return null;
        $filename = substr($legacy, strlen($slugPrefix));
        return $this->safeLegacySource($filename, base_path('httpdocs/' . ltrim($legacy, '/')));
    }

    /** @return array{string, string}|null */
    private function safeLegacySource(string $filename, string $source): ?array
    {
        if ($filename === ''
            || basename($filename) !== $filename
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,191}\z/D', $filename) !== 1) {
            return null;
        }

        return [$filename, $source];
    }

    private function move(string $source, string $target): bool
    {
        if (is_file($target)) {
            if (is_file($source) && ! @unlink($source)) {
                throw new RuntimeException("Public message media could not be erased: {$source}");
            }
            return true;
        }
        if (! is_file($source)) return false;
        File::ensureDirectoryExists(dirname($target), 0700, true);
        if (! @rename($source, $target)) {
            throw new RuntimeException("Message media could not be moved into private storage: {$source}");
        }
        @chmod($target, 0600);
        return true;
    }

    public function down(): void
    {
        // Moving private media back into the public web root is prohibited.
    }
};
