<?php

namespace App\Modules\CrossBorderIOR\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductMediaService
{
    /**
     * Download and store a product image locally to avoid hotlinking.
     * 
     * @param string $url
     * @param string $disk
     * @return string|null The stored path or URL
     */
    public function rehostImage(string $url, string $disk = 'public'): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);
            
            if (!$response->successful()) {
                Log::warning("[IOR Media] Failed to download image: $url");
                return $url; // Fallback to original if download fails
            }

            $extension = $this->guessExtension($url, $response->header('Content-Type'));
            $filename  = 'ior/products/' . Str::random(20) . '.' . $extension;

            Storage::disk($disk)->put($filename, $response->body());

            return Storage::disk($disk)->url($filename);
        } catch (\Exception $e) {
            Log::error("[IOR Media] Exception rehosting image: " . $e->getMessage());
            return $url;
        }
    }

    /**
     * Rehost a gallery of images.
     */
    public function rehostGallery(array $urls, string $disk = 'public'): array
    {
        $rehosted = [];
        foreach ($urls as $url) {
            $rehosted[] = $this->rehostImage($url, $disk);
        }
        return array_filter($rehosted);
    }

    private function guessExtension(string $url, ?string $contentType): string
    {
        if (str_contains($contentType, 'jpeg')) return 'jpg';
        if (str_contains($contentType, 'png'))  return 'png';
        if (str_contains($contentType, 'webp')) return 'webp';
        
        $path = parse_url($url, PHP_URL_PATH);
        return pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
    }
}
