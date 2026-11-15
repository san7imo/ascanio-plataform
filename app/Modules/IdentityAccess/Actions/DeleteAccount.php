<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->status === 'active' && $user->hasVerifiedEmail(), 403);

            if (Membership::query()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['password' => 'Tu cuenta tiene historial en espacios de trabajo. Debes resolver su continuidad antes de eliminarla.']);
            }

            $user->delete();
        });
    }
}
