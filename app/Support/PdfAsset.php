<?php

namespace App\Support;

class PdfAsset
{
    public static function fromPublic(string $relativePath): string
    {
        return self::fromAbsolute(public_path($relativePath));
    }

    public static function fromAbsolute(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            return '';
        }

        if (app()->environment('local')) {
            $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';
            $contents = @file_get_contents($absolutePath);

            if ($contents !== false) {
                return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
            }
        }

        $normalizedPath = str_replace('\\', '/', $absolutePath);

        return 'file:///' . ltrim($normalizedPath, '/');
    }
}
