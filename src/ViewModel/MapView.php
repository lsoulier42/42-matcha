<?php

declare(strict_types=1);

namespace App\ViewModel;

/**
 * Données de la page carte : ma position et les marqueurs suggérés.
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
