<?php

namespace App\Http\Requests\Workspaces;

use App\Modules\IdentityAccess\CapabilityCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoleRequest extends SensitiveGovernanceRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageRoles', $this->route('workspace'));
    }

    public function rules(): array
    {
        return parent::rules() + ($this->isMethod('delete') ? [] : [
            'name' => ['required', 'string', 'max:120'],
            'permissions' => ['required', 'array', 'min:1', 'max:6'],
            'permissions.*' => ['required', 'array:code,delegable'],
            'permissions.*.code' => ['required', 'string', 'distinct', Rule::in(CapabilityCatalog::DELEGABLE)],
            'permissions.*.delegable' => ['required', 'boolean'],
        ]);
    }

    public function messages(): array
    {
        return parent::messages() + [
            'name.required' => 'Escribe el nombre del rol.', 'name.string' => 'Escribe un nombre válido.', 'name.max' => 'Usa un máximo de 120 caracteres.',
            'permissions.required' => 'Selecciona al menos una capacidad.', 'permissions.array' => 'Selecciona capacidades válidas.',
            'permissions.min' => 'Selecciona al menos una capacidad.', 'permissions.max' => 'Selecciona como máximo seis capacidades.',
            'permissions.*.array' => 'Selecciona una capacidad válida.',
            'permissions.*.code.*' => 'Selecciona una capacidad disponible y evita repetirla.',
            'permissions.*.delegable.*' => 'Indica si esta capacidad podrá delegarse.',
        ];
    }
}
