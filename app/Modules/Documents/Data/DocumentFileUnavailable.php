<?php

namespace App\Modules\Documents\Data;

class DocumentFileUnavailable extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('El archivo no está disponible o su integridad cambió.');
    }
}
