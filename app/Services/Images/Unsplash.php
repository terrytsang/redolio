<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Http;

/**
 * Unsplash photo search for the editor. The access key stays on the server; the browser only sees thumbnails and
 * credits. Only images.unsplash.com over https is ever downloaded, and every photo is re-encoded like an upload.
 */
class Unsplash
{
    private const API = 'https://api.unsplash.com';

    private const UTM = 'utm_source=redo&utm_medium=referral';

    public function configured(): bool
    {
        return (string) config('services.unsplash.access_key') !== '';
    }

    /** @return array{results: array<int, array<string, mixed>>, total_pages: int} */
    public function search(string $query, int $page = 1): array
    {
        $r = $this->api()->get('/search/photos', ['query' => $query, 'page' => $page, 'per_page' => 12, 'orientation' => 'landscape', 'content_filter' => 'high']);
        if (! $r->successful()) {
            throw new \RuntimeException($r->status() === 403 ? 'Unsplash search is busy right now. Try again in a little while.' : 'Unsplash search isn’t available right now.');
        }

        return [
            'results' => collect($r->json('results', []))->map(fn ($p) => $this->shape($p))->values()->all(),
            'total_pages' => (int) $r->json('total_pages', 1),
        ];
    }

    /** @return array<string, mixed> */
    public function photo(string $id): array
    {
        $r = $this->api()->get('/photos/'.rawurlencode($id));
        if (! $r->successful()) {
            throw new \RuntimeException('That photo couldn’t be found on Unsplash.');
        }

        return $this->shape($r->json()) + ['download_location' => (string) $r->json('links.download_location'), 'regular' => (string) $r->json('urls.regular')];
    }

    /** Download the photo to a temp file and return its path. Triggers Unsplash's download tracking, as their guidelines require. */
    public function download(array $photo): string
    {
        $url = $photo['regular'] ?? '';
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || parse_url($url, PHP_URL_SCHEME) !== 'https' || $host !== 'images.unsplash.com') {
            throw new \RuntimeException('That photo couldn’t be downloaded.');
        }
        if (($loc = $photo['download_location'] ?? '') !== '' && str_starts_with($loc, self::API.'/')) {
            try {
                $this->api()->get(substr($loc, strlen(self::API)));
            } catch (\Throwable) { // tracking must never block the user
            }
        }
        $r = Http::timeout(20)->withOptions(['allow_redirects' => false])->get($url);
        if (! $r->successful() || strlen($r->body()) > 8 * 1024 * 1024) {
            throw new \RuntimeException('That photo couldn’t be downloaded.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'unsplash');
        file_put_contents($tmp, $r->body());

        return $tmp;
    }

    private function api()
    {
        return Http::baseUrl(self::API)->timeout(12)->acceptJson()->withHeaders(['Accept-Version' => 'v1', 'Authorization' => 'Client-ID '.config('services.unsplash.access_key')]);
    }

    private function shape(array $p): array
    {
        return [
            'id' => (string) ($p['id'] ?? ''),
            'thumb' => (string) ($p['urls']['small'] ?? ''),
            'alt' => mb_substr((string) ($p['alt_description'] ?? $p['description'] ?? 'Photo from Unsplash'), 0, 200),
            'author' => (string) ($p['user']['name'] ?? 'Unsplash photographer'),
            'author_url' => ($p['user']['links']['html'] ?? 'https://unsplash.com').'?'.self::UTM,
            'link' => ($p['links']['html'] ?? 'https://unsplash.com').'?'.self::UTM,
            'width' => (int) ($p['width'] ?? 0), 'height' => (int) ($p['height'] ?? 0),
        ];
    }
}
