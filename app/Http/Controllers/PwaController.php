<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PwaController extends Controller
{
    /** Manifest dinámico: nombre, colores e íconos salen de la configuración. */
    public function manifest(): JsonResponse
    {
        $name = setting('site.name', config('app.name'));

        return response()->json([
            'name' => $name,
            'short_name' => setting('site.short_name') ?: mb_substr($name, 0, 12),
            'description' => setting('seo.meta_description'),
            'lang' => 'es',
            'start_url' => '/portal?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => setting('pwa.theme_color', '#0f5132'),
            'background_color' => setting('pwa.background_color', '#ffffff'),
            'icons' => [
                ['src' => route('pwa.icon', 192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Mi carnet', 'url' => '/portal/carnet'],
                ['name' => 'Mi cuenta', 'url' => '/portal/cuenta'],
                ['name' => 'Reservas', 'url' => '/portal/reservas'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * Ícono de la app: usa el configurado (pwa.icon o logo); si no hay, genera uno con las iniciales y el color de marca.
     */
    public function icon(string $size): Response
    {
        $size = (int) $size;
        $source = setting('pwa.icon') ?: setting('site.logo');
        $cacheKey = 'pwa.icon.'.$size.'.'.md5($source.setting('site.primary_color').setting('site.short_name'));

        $png = Cache::rememberForever($cacheKey, fn () => base64_encode($this->renderIcon($size, $source)));

        return response(base64_decode($png), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function renderIcon(int $size, ?string $source): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = sscanf(setting('site.primary_color', '#0f5132'), '#%02x%02x%02x');
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));

        $logo = null;
        if ($source && Storage::disk('public')->exists($source)) {
            $logo = @imagecreatefromstring(Storage::disk('public')->get($source)) ?: null;
        }

        if ($logo) {
            // Logo centrado con margen (zona segura para íconos "maskable").
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            $inner = (int) ($size * 0.7);
            $ratio = min($inner / imagesx($logo), $inner / imagesy($logo));
            $w = (int) (imagesx($logo) * $ratio);
            $h = (int) (imagesy($logo) * $ratio);
            imagecopyresampled($canvas, $logo, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h, imagesx($logo), imagesy($logo));
        } else {
            $text = mb_strtoupper(mb_substr(setting('site.short_name') ?: setting('site.name', 'Club'), 0, 3));
            $white = imagecolorallocate($canvas, 255, 255, 255);
            $font = 5;
            $scale = max(1, (int) floor($size / 40));
            $tw = imagefontwidth($font) * strlen($text);
            $th = imagefontheight($font);
            $small = imagecreatetruecolor($tw, $th);
            imagefill($small, 0, 0, imagecolorallocate($small, $r, $g, $b));
            imagestring($small, $font, 0, 0, $text, imagecolorallocate($small, 255, 255, 255));
            $dw = $tw * $scale;
            $dh = $th * $scale;
            imagecopyresized($canvas, $small, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $tw, $th);
        }

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }
}
