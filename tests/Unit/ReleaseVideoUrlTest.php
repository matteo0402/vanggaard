<?php

use App\ReleaseVideoUrl;

test('supported YouTube URLs resolve to a privacy-enhanced embed URL', function (string $url) {
    expect(app(ReleaseVideoUrl::class)->embedUrl($url))
        ->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
})->with([
    'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'mobile watch' => 'http://m.youtube.com/watch?feature=share&v=dQw4w9WgXcQ',
    'short link' => 'https://youtu.be/dQw4w9WgXcQ?t=30',
    'short' => 'https://youtube.com/shorts/dQw4w9WgXcQ',
    'live' => 'https://www.youtube.com/live/dQw4w9WgXcQ',
    'embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
]);

test('unsupported and malformed URLs cannot become iframe sources', function (string $url) {
    expect(app(ReleaseVideoUrl::class)->embedUrl($url))->toBeNull();
})->with([
    'unsupported provider' => 'https://vimeo.com/123456',
    'confusing host' => 'https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    'missing video id' => 'https://www.youtube.com/watch?feature=share',
    'invalid video id' => 'https://www.youtube.com/watch?v=not-valid',
    'unsafe scheme' => 'javascript:alert(1)',
    'malformed' => 'not a url',
]);

test('valid unsupported providers remain external links while unsafe URLs are discarded', function () {
    $videoUrl = app(ReleaseVideoUrl::class);

    expect($videoUrl->externalUrl(' https://vimeo.com/123456 '))->toBe('https://vimeo.com/123456')
        ->and($videoUrl->externalUrl('javascript:alert(1)'))->toBeNull()
        ->and($videoUrl->externalUrl('ftp://example.com/video'))->toBeNull();
});
