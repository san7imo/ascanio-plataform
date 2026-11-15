<?php

namespace App\Http\Requests\Workspaces;

use App\Modules\IdentityAccess\Models\Tenant;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? Gate::allows('create', Tenant::class)
            : Gate::allows('update', $this->route('workspace'));
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'locale' => ['required', Rule::in(['es', 'en', 'pt'])],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
        ];

        foreach (['tenant_id', 'owner_id', 'operator_party_id', 'user_id', 'status', 'roles', 'is_owner', 'person_id'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        if ($this->isMethod('post')) {
            $rules['creation_token'] = ['required', 'uuid', Rule::in($this->session()->get('workspace_creation_tokens', []))];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del espacio.', 'name.max' => 'Usa un máximo de 120 caracteres.',
            'locale.in' => 'Selecciona un idioma disponible.', 'timezone.in' => 'Selecciona una zona horaria válida.',
            'creation_token.*' => 'Abre un formulario nuevo para crear este espacio.',
            '*.prohibited' => 'Este campo no se puede asignar desde el formulario.',
        ];
    }
}
