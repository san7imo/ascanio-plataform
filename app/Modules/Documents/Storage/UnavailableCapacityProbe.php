<?php

namespace App\Modules\Documents\Storage;

use App\Modules\Documents\Data\CapacitySnapshot;

class UnavailableCapacityProbe implements CapacityProbe
{
    public function snapshot(): CapacitySnapshot
    {
        throw new \RuntimeException('La comprobación de capacidad todavía no está configurada.');
    }
}
