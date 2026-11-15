<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Support\Facades\Gate;

class TransferOwnershipRequest extends SensitiveGovernanceRequest
{
    public function authorize(): bool
    {
        return Gate::allows('transferOwnership', $this->route('workspace'));
    }

    public function rules(): array
    {
        return parent::rules() + [
            'membership_id' => ['required', 'uuid'],
            'confirmation' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'membership_id.*' => 'Selecciona un miembro de este espacio.',
            'confirmation.accepted' => 'Confirma expresamente la transferencia a esta persona.',
        ];
    }
}
