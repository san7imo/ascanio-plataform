<?php

namespace App\Http\Requests\Workspaces;

use App\Modules\IdentityAccess\Data\SensitiveProof;
use Illuminate\Foundation\Http\FormRequest;

class SensitiveGovernanceRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'password' => ['required', 'string', 'current_password'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
        foreach (['tenant_id', 'owner_id', 'user_id', 'is_owner', 'system_kind', 'operator_party_id', 'actor_id'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function proof(): SensitiveProof
    {
        return new SensitiveProof($this->validated('password'), $this->validated('code'));
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Confirma tu contraseña.', 'password.current_password' => 'La contraseña no es correcta.',
            'code.required' => 'Confirma con un código MFA nuevo.', 'code.regex' => 'Escribe los seis dígitos del código MFA.',
            '*.prohibited' => 'Este campo no se puede asignar desde el formulario.',
        ];
    }
}
