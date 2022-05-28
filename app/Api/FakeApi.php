<?php

namespace App\Api;

use Illuminate\Support\Collection;

class FakeApi
{
    public static function getSiteNames(): Collection
    {
        // ID => Name mapping
        // In our production case, it comes from a WordPress website API,
        // but the returned format is the same.
        return collect([
            1 => 'The Dummy News',
            3 => 'The Fake Buggle',
            7 => 'The Random Press',
        ]);
    }

    public static function getCategoryNames(int|string|null $siteId): Collection
    {
        return collect(
            match ($siteId) {
                1, '1' => [
                    // ID => Name mapping
                    1 => 'News',
                    4 => 'Sports',
                    6 => 'Politics',
                ],
                3, '3' => [
                    // ID => Name mapping
                    1 => 'General',
                    3 => 'Art',
                    4 => 'Local',
                ],
                7, '7' => [
                    // ID => Name mapping
                    1 => 'Culture',
                    2 => 'Outdoors',
                    3 => 'Community',
                ],
                default => [],
            }
        );
    }
}