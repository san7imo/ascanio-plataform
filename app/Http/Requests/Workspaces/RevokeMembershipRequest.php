<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RevokeMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('revokeMember', $this->route('workspace'));
    }

    public function rules(): array
    {
        return ['password' => ['required', 'string', 'current_password']];
    }

    public function messages(): array
    {
        return ['password.required' => 'Confirma tu contraseña.', 'password.current_password' => 'La contraseña no es correcta.'];
    }
}
