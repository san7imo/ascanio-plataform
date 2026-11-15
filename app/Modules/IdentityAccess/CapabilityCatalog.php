<?php

namespace App\Modules\IdentityAccess;

final class CapabilityCatalog
{
    public const OWNER = ['tenant.view', 'tenant.settings.update', 'tenant.members.view', 'tenant.members.revoke', 'tenant.roles.manage', 'tenant.members.assign_roles', 'tenant.ownership.transfer'];

    public const DELEGABLE = ['tenant.view', 'tenant.settings.update', 'tenant.members.view', 'tenant.members.revoke', 'tenant.roles.manage', 'tenant.members.assign_roles'];

    public const LABELS = [
        'tenant.view' => 'Consultar el espacio', 'tenant.settings.update' => 'Editar ajustes',
        'tenant.members.view' => 'Consultar membresías', 'tenant.members.revoke' => 'Gestionar estados de membresía',
        'tenant.roles.manage' => 'Administrar roles', 'tenant.members.assign_roles' => 'Asignar roles',
        'tenant.ownership.transfer' => 'Transferir el gobierno del espacio',
    ];
}
