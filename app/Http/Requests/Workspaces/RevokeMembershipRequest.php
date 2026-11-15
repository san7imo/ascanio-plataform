<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Support\Facades\Gate;

class RevokeMembershipRequest extends SensitiveGovernanceRequest
{
    public function authorize(): bool
    {
        return Gate::allows('revokeMember', $this->route('workspace'));
    }
}
