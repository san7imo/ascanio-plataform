<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Support\Facades\Gate;

class ManageInvitationRequest extends SensitiveGovernanceRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inviteMembers', $this->route('workspace'));
    }
}
