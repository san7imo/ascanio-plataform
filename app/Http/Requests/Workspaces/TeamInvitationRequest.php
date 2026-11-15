<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Support\Facades\Gate;

class TeamInvitationRequest extends MemberRolesRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inviteMembers', $this->route('workspace'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['assignments'] = ['required', 'array', 'min:1', 'max:20'];

        return $rules + [
            'recipient_email' => ['required', 'string', 'email:rfc', 'max:254'],
            'days' => ['required', 'integer', 'between:1,14'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'assignments.required' => 'Selecciona al menos un rol.', 'assignments.min' => 'Selecciona al menos un rol.',
            'recipient_email.*' => 'Escribe un correo válido de hasta 254 caracteres.', 'days.*' => 'Selecciona una vigencia de uno a catorce días.',
        ];
    }
}
