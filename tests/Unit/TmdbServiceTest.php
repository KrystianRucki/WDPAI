<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TmdbServiceTest extends TestCase
{
    public function testImageUrlBuildsExpectedPath(): void
    {
        $service = new TmdbService();

        self::assertStringEndsWith('/w185/abc.jpg', (string) $service->imageUrl('/abc.jpg', 'w185'));
        self::assertNull($service->imageUrl(null));
        self::assertNull($service->imageUrl(''));
    }

    public function testPosterPlaceholderUsesLocalAsset(): void
    {
        $service = new TmdbService();

        self::assertSame('/public/assets/img/movie_placeholder.svg', $service->posterPlaceholder());
    }
}
