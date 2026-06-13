<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FilmsRepositoryValueNormalizationTest extends TestCase
{
    public function testRuntimeZeroOrMissingIsStoredAsNull(): void
    {
        self::assertNull(FilmsRepository::normalizeRuntimeMinutes(0));
        self::assertNull(FilmsRepository::normalizeRuntimeMinutes(null));
        self::assertSame(95, FilmsRepository::normalizeRuntimeMinutes('95'));
    }

    public function testReleaseYearMustMatchDatabaseConstraintRange(): void
    {
        self::assertSame(1999, FilmsRepository::normalizeReleaseYear('1999-05-10'));
        self::assertNull(FilmsRepository::normalizeReleaseYear('1887-01-01'));
        self::assertNull(FilmsRepository::normalizeReleaseYear('2101-01-01'));
        self::assertNull(FilmsRepository::normalizeReleaseYear(null));
    }
}
