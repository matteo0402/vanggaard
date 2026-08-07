<?php

namespace App;

use Illuminate\Support\Str;
use Illuminate\Support\Uri;

class ReleaseVideoUrl
{
    /** @var list<string> */
    private const YOUTUBE_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    public function externalUrl(string $url): ?string
    {
        $url = trim($url);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $uri = Uri::of($url);

        if (! in_array(Str::lower($uri->scheme() ?? ''), ['http', 'https'], true) || $uri->host() === null) {
            return null;
        }

        return $url;
    }

    public function embedUrl(string $url): ?string
    {
        $url = $this->externalUrl($url);

        if ($url === null) {
            return null;
        }

        $uri = Uri::of($url);
        $host = Str::lower($uri->host());
        $segments = $uri->pathSegments();
        $videoId = match (true) {
            $host === 'youtu.be' => $segments->first(),
            in_array($host, self::YOUTUBE_HOSTS, true) && $uri->path() === 'watch' => $uri->query()->get('v'),
            in_array($host, self::YOUTUBE_HOSTS, true)
                && in_array($segments->first(), ['embed', 'live', 'shorts'], true) => $segments->get(1),
            default => null,
        };

        if (! is_string($videoId) || preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) !== 1) {
            return null;
        }

        return "https://www.youtube-nocookie.com/embed/{$videoId}";
    }
}
