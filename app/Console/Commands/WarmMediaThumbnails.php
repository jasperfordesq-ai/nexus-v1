<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\MediaThumbnailService;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class WarmMediaThumbnails extends Command
{
    protected $signature = 'media:warm-thumbnails
        {--root=all : uploads, storage, or all}
        {--limit=0 : Maximum source images to process (0 = all)}
        {--dry-run : List candidate images without writing thumbnails}';

    protected $description = 'Warm cached WebP/JPEG derivatives for existing uploaded media';

    /**
     * @var array<int, array{name:string,width:int,height:int,fit:string}>
     */
    private array $variants = [
        ['name' => 'avatar', 'width' => 96, 'height' => 96, 'fit' => 'cover'],
        ['name' => 'card', 'width' => 640, 'height' => 360, 'fit' => 'cover'],
        ['name' => 'detail', 'width' => 1200, 'height' => 675, 'fit' => 'contain'],
        ['name' => 'logo', 'width' => 384, 'height' => 160, 'fit' => 'contain'],
    ];

    public function handle(MediaThumbnailService $thumbnails): int
    {
        $rootOption = strtolower((string) $this->option('root'));
        if (!in_array($rootOption, ['uploads', 'storage', 'all'], true)) {
            $this->error('--root must be one of: uploads, storage, all');

            return Command::FAILURE;
        }

        $roots = [];
        if ($rootOption === 'uploads' || $rootOption === 'all') {
            $roots[] = base_path('httpdocs/uploads');
        }
        if ($rootOption === 'storage' || $rootOption === 'all') {
            $roots[] = storage_path('app/public');
        }

        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $format = $thumbnails->format();
        $processed = 0;
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->imageFiles($roots, $limit) as $file) {
            $processed++;
            if ($dryRun) {
                $this->line(sprintf('[dry-run] %s', $file->getPathname()));
                continue;
            }

            foreach ($this->variants as $variant) {
                try {
                    $thumbPath = $thumbnails->thumbnailPath(
                        $file->getPathname(),
                        $variant['width'],
                        $variant['height'],
                        $variant['fit'],
                        $format
                    );
                    if (is_file($thumbPath)) {
                        $skipped++;
                        continue;
                    }

                    $thumbnails->createThumbnail(
                        $file->getPathname(),
                        $thumbPath,
                        $variant['width'],
                        $variant['height'],
                        $variant['fit'],
                        $format
                    );
                    $generated++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn(sprintf('%s failed for %s: %s', $variant['name'], $file->getPathname(), $e->getMessage()));
                }
            }
        }

        if ($dryRun) {
            $this->info(sprintf('%d image source(s) would be warmed.', $processed));

            return Command::SUCCESS;
        }

        $this->info(sprintf(
            'Processed %d image source(s); generated %d derivative(s), skipped %d existing derivative(s), failed %d.',
            $processed,
            $generated,
            $skipped,
            $failed
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<int, string> $roots
     * @return \Generator<int, SplFileInfo>
     */
    private function imageFiles(array $roots, int $limit): \Generator
    {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $count = 0;

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }
                if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                if (!in_array(strtolower($file->getExtension()), $allowed, true)) {
                    continue;
                }

                yield $file;
                $count++;
                if ($limit > 0 && $count >= $limit) {
                    return;
                }
            }
        }
    }
}
