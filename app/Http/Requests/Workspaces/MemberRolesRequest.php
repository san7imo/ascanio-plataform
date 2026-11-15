<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MemberRolesRequest extends SensitiveGovernanceRequest
{
    public function authorize(): bool
    {
        return Gate::allows('assignRoles', $this->route('workspace'));
    }

    public function rules(): array
    {
        return parent::rules() + [
            'assignments' => ['present', 'array', 'max:20'],
            'assignments.*' => ['required', 'array:role_id,scope'],
            'assignments.*.role_id' => ['required', 'uuid', 'distinct'],
            'assignments.*.scope' => ['required', Rule::in(['tenant', 'assigned_resources'])],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'assignments.present' => 'Selecciona los roles; puedes retirar todos.', 'assignments.array' => 'Selecciona asignaciones válidas.',
            'assignments.max' => 'Selecciona como máximo veinte roles.', 'assignments.*.array' => 'Selecciona una asignación válida.',
            'assignments.*.role_id.*' => 'Selecciona un rol disponible y evita repetirlo.',
            'assignments.*.scope.*' => 'Selecciona un alcance válido.',
        ];
    }
}
