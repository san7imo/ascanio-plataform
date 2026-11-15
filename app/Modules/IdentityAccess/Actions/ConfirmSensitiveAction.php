<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ConfirmSensitiveAction
{
    public function __construct(private Google2FA $totp) {}

    // The enclosing mutation holds the account row lock. A successful commit
    // consumes this TOTP step for the account, across sessions and workspaces.
    public function execute(User $user, #[\SensitiveParameter] SensitiveProof $proof): void
    {
        if (! Hash::check($proof->password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
            throw ValidationException::withMessages(['code' => 'Activa y confirma MFA en Seguridad antes de realizar este cambio.']);
        }

        $step = $this->totp->verifyKeyNewer(decrypt($user->two_factor_secret), $proof->code, $user->last_sensitive_totp_step ?? 0, 1);

        if ($step === false) {
            throw ValidationException::withMessages(['code' => 'Usa un código MFA válido y nuevo para cada cambio.']);
        }

        $user->forceFill(['last_sensitive_totp_step' => $step])->save();
    }
}
