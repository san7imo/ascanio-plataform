<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Validation\Rule;

class MemberStatusRequest extends RevokeMembershipRequest
{
    public function rules(): array
    {
        return parent::rules() + ['status' => ['required', Rule::in(['active', 'suspended', 'revoked'])]];
    }

    public function messages(): array
    {
        return parent::messages() + ['status.*' => 'Selecciona un estado disponible.'];
    }
}
