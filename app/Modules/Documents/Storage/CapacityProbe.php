<?php

namespace App\Modules\Documents\Storage;

use App\Modules\Documents\Data\CapacitySnapshot;

interface CapacityProbe
{
    // Account quota/inodes, not filesystem df/free space alone.
    public function snapshot(): CapacitySnapshot;
}
