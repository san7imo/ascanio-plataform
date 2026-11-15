<?php

namespace App\Modules\IdentityAccess;

final class CapabilityCatalog
{
    public const OWNER = ['tenant.view', 'tenant.settings.update', 'tenant.members.view', 'tenant.members.revoke', 'tenant.roles.manage', 'tenant.members.assign_roles', 'tenant.ownership.transfer', 'tenant.members.invite'];

    public const DELEGABLE = ['tenant.view', 'tenant.settings.update', 'tenant.members.view', 'tenant.members.revoke', 'tenant.roles.manage', 'tenant.members.assign_roles', 'tenant.members.invite'];

    public const LABELS = [
        'tenant.members.invite' => 'Invitar al equipo',
        'tenant.view' => 'Consultar el espacio', 'tenant.settings.update' => 'Editar ajustes',
        'tenant.members.view' => 'Consultar membresías', 'tenant.members.revoke' => 'Gestionar estados de membresía',
        'tenant.roles.manage' => 'Administrar roles', 'tenant.members.assign_roles' => 'Asignar roles',
        'tenant.ownership.transfer' => 'Transferir el gobierno del espacio',
    ];
}
