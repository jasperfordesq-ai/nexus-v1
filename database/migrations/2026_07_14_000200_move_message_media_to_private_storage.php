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
                $legacy = (string) ($row->file_path ?: $row->file_url);
                $prefix = "/uploads/{$row->tenant_id}/message_attachments/";
                if (! str_starts_with($legacy, $prefix)) return;
                $filename = basename($legacy);
                if ($filename === '' || $legacy !== $prefix . $filename) return;
                $relative = "message-media/{$row->tenant_id}/attachments/{$filename}";
                if ($this->move(base_path('httpdocs/' . ltrim($legacy, '/')), storage_path('app/private/' . $relative))) {
                    DB::table('message_attachments')->where('id', $row->id)->update([
                        'file_path' => $relative,
                        'file_url' => $relative,
                    ]);
                }
            });
        }

        if (Schema::hasTable('messages')) {
            DB::table('messages')->whereNotNull('audio_url')->orderBy('id')->each(function (object $row): void {
                $legacy = (string) $row->audio_url;
                $prefix = "/uploads/{$row->tenant_id}/voice_messages/";
                if (! str_starts_with($legacy, $prefix)) return;
                $filename = basename($legacy);
                if ($filename === '' || $legacy !== $prefix . $filename) return;
                $relative = "message-media/{$row->tenant_id}/voice/{$filename}";
                if ($this->move(base_path('httpdocs/' . ltrim($legacy, '/')), storage_path('app/private/' . $relative))) {
                    DB::table('messages')->where('id', $row->id)->update(['audio_url' => $relative]);
                }
            });
        }
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
