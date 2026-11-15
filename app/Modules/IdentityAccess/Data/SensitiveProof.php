<?php

namespace App\Modules\IdentityAccess\Data;

final readonly class SensitiveProof
{
    public function __construct(#[\SensitiveParameter] public string $password, #[\SensitiveParameter] public string $code) {}
}
