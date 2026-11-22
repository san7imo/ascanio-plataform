<?php

namespace App\Modules\IdentityAccess\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

final class DocumentSharingAuthority
{
    // Same current-source predicates for invitation acceptance, delivery and every
    // grant query (including counts/pagination). No cached permission snapshot.
    public function constrain(Builder $query, string $table): Builder
    {
        $query->join('tenants as custody', 'custody.id', '=', $table.'.tenant_id')
            ->join('tenant_memberships as source', function (JoinClause $join) use ($table) {
                $join->on('source.tenant_id', '=', $table.'.tenant_id')->on('source.id', '=', $table.'.source_membership_id')
                    ->on('source.user_id', '=', $table.'.inviter_id')->on('source.access_version', '=', $table.'.source_access_version');
            })
            ->join('users as author', 'author.id', '=', 'source.user_id')
            ->where('custody.status', 'active')->where('source.status', 'active')->where('author.status', 'active')->whereNotNull('author.email_verified_at');

        foreach (['tenant.view' => false, 'documents.share' => false, 'documents.view' => true, 'documents.download' => true] as $code => $delegable) {
            $predicate = function ($exists) use ($code, $delegable) {
                $exists->selectRaw('1')->from('membership_roles as mr')
                    ->join('role_permissions as rp', function (JoinClause $join) {
                        $join->on('rp.tenant_id', '=', 'mr.tenant_id')->on('rp.role_id', '=', 'mr.role_id');
                    })->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                    ->whereColumn('mr.tenant_id', 'source.tenant_id')->whereColumn('mr.membership_id', 'source.id')
                    ->where('mr.access_scope', 'tenant')->where('p.code', $code)
                    ->when($delegable, fn ($q) => $q->where('rp.is_delegable', true));
            };
            if ($code === 'documents.download') {
                $query->where(fn ($q) => $q->where($table.'.capability', 'documents.view')->orWhereExists($predicate));
            } else {
                $query->whereExists($predicate);
            }
        }

        return $query->select($table.'.*');
    }
}
