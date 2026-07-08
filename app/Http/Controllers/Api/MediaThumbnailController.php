<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\MediaThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MediaThumbnailController extends Controller
{
    public function __construct(private readonly MediaThumbnailService $thumbnails)
    {
    }

    public function show(Request $request): BinaryFileResponse
    {
        $sourcePath = $this->thumbnails->resolveSourcePath((string) $request->query('src', ''));
        if ($sourcePath === null) {
            abort(404);
        }

        $width = $this->thumbnails->dimension($request->query('w'), MediaThumbnailService::DEFAULT_WIDTH);
        $height = $this->thumbnails->dimension($request->query('h'), MediaThumbnailService::DEFAULT_HEIGHT);
        $fit = $request->query('fit') === 'contain' ? 'contain' : 'cover';
        $format = $this->thumbnails->format($request->query('format'));
        $thumbPath = $this->thumbnails->ensureThumbnail($sourcePath, $width, $height, $fit, $format);

        $response = response()->file($thumbPath, [
            'Content-Type' => match ($format) {
                'avif' => 'image/avif',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            'Content-Disposition' => 'inline',
        ]);

        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setSharedMaxAge(31536000);
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        $response->headers->remove('Pragma');
        $response->headers->remove('Expires');
        $response->setEtag(md5_file($thumbPath) ?: null);

        return $response;
    }
}
