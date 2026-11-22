<?php

namespace App\Modules\Documents\Data;

use Carbon\CarbonImmutable;

final readonly class CapacitySnapshot
{
    public function __construct(public int $availableBytes, public int $availableInodes, public CarbonImmutable $measuredAt) {}
}
