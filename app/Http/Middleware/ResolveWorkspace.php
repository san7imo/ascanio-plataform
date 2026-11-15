<?php

namespace App\Http\Middleware;

use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use App\Modules\IdentityAccess\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function __construct(private TenantContext $context, private WorkspaceAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        try {
            // The workspace selector and this resolver are deliberate account-bounded
            // reads. Every private lookup includes both workspace and current user.
            $membership = $this->access->capableMemberships($request->user(), 'tenant.view')
                ->where('tenant_memberships.tenant_id', $request->route('workspace'))
                ->select('tenant_memberships.*')
                ->with('tenant')->firstOrFail();

            $this->context->establish($membership->tenant, $membership);
            $request->route()->setParameter('workspace', $membership->tenant);

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
