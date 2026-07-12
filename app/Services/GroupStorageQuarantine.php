<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GroupStorageQuarantineException;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Coordinates database deletion with reversible filesystem quarantine.
 *
 * Bytes are moved out of their live paths inside the database transaction,
 * restored if the transaction or commit fails, and purged only after commit.
 */
final class GroupStorageQuarantine
{
    /**
     * @template TResult
     * @param Closure(Closure(list<array{disk: string, path: string}>): void): TResult $operation
     * @return TResult
     */
    public function run(Closure $operation): mixed
    {
        /** @var list<array{disk: string, original: string, quarantine: string}> $moves */
        $moves = [];

        try {
            // Filesystem side effects cannot be transparently replayed, so this
            // transaction intentionally gets one attempt rather than a deadlock retry.
            $result = DB::transaction(function () use ($operation, &$moves): mixed {
                $quarantine = function (array $assets) use (&$moves): void {
                    $this->quarantine($assets, $moves);
                };

                return $operation($quarantine);
            }, 1);
        } catch (Throwable $exception) {
            try {
                $this->restore($moves);
            } catch (Throwable $restoreException) {
                Log::critical('Group storage quarantine restoration failed', [
                    'error' => $restoreException->getMessage(),
                    'original_error' => $exception->getMessage(),
                    'moves' => $moves,
                ]);

                throw new GroupStorageQuarantineException(
                    'Unable to restore quarantined group storage after database failure.',
                    previous: $exception,
                );
            }

            throw $exception;
        }

        $this->purge($moves);

        return $result;
    }

    /**
     * @param list<array{disk: string, path: string}> $assets
     * @param list<array{disk: string, original: string, quarantine: string}> $moves
     */
    private function quarantine(array $assets, array &$moves): void
    {
        $seen = [];
        foreach ($moves as $move) {
            $seen[$move['disk'] . "\0" . $move['original']] = true;
        }

        foreach ($assets as $asset) {
            $diskName = $asset['disk'];
            $path = $asset['path'];
            $key = $diskName . "\0" . $path;
            if (isset($seen[$key])) {
                continue;
            }

            $disk = Storage::disk($diskName);
            if (! $disk->exists($path)) {
                continue;
            }

            $directory = trim(str_replace('\\', '/', dirname($path)), './');
            $quarantinePath = ($directory === '' ? '' : $directory . '/')
                . '.quarantine/' . Str::uuid()->toString() . '/' . basename($path);
            if (! $disk->move($path, $quarantinePath)) {
                throw new GroupStorageQuarantineException(
                    "Unable to quarantine group asset on disk {$diskName}.",
                );
            }

            $moves[] = [
                'disk' => $diskName,
                'original' => $path,
                'quarantine' => $quarantinePath,
            ];
            $seen[$key] = true;
        }
    }

    /** @param list<array{disk: string, original: string, quarantine: string}> $moves */
    private function restore(array $moves): void
    {
        foreach (array_reverse($moves) as $move) {
            $disk = Storage::disk($move['disk']);
            if (! $disk->exists($move['quarantine'])) {
                continue;
            }
            if ($disk->exists($move['original']) || ! $disk->move($move['quarantine'], $move['original'])) {
                throw new GroupStorageQuarantineException(
                    "Unable to restore quarantined group asset on disk {$move['disk']}.",
                );
            }
        }
    }

    /** @param list<array{disk: string, original: string, quarantine: string}> $moves */
    private function purge(array $moves): void
    {
        foreach ($moves as $move) {
            $disk = Storage::disk($move['disk']);
            if ($disk->exists($move['quarantine']) && ! $disk->delete($move['quarantine'])) {
                // The live path and database row are gone, so deletion is truthful;
                // retain an operational signal for orphan cleanup.
                Log::error('Unable to purge committed group storage quarantine', $move);
            }
        }
    }
}
