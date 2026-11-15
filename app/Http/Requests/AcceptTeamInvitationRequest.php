<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['accepted'], 'tenant_id' => ['prohibited'], 'user_id' => ['prohibited'],
            'roles' => ['prohibited'], 'is_owner' => ['prohibited'], 'inviter_id' => ['prohibited'],
            'token' => ['prohibited'], 'invitation_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['confirmation.accepted' => 'Confirma que quieres incorporarte al equipo con estos permisos.', '*.prohibited' => 'El acceso se obtiene solo mediante la invitación verificada.'];
    }
}
