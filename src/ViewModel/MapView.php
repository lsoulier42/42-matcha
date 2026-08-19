<?php

declare(strict_types=1);

namespace App\ViewModel;

/**
 * Map page data: my position and the suggested markers.
 */
final readonly class MapView
{
    /**
     * @param MapMarker[] $markers
     */
    public function __construct(
        public ?MapMarker $me,
        public array $markers,
    ) {
    }
}
