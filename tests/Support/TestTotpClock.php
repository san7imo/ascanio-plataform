<?php

namespace Tests\Support;

use PragmaRX\Google2FA\Google2FA;

// Only time is controlled. Codes are generated and verified by the real library.
class TestTotpClock extends Google2FA
{
    private int $step = 70000000;

    public function getTimestamp()
    {
        return $this->step;
    }

    public function advance(int $minimum = 0): void
    {
        $this->step = max($this->step + 1, $minimum + 1);
    }
}
