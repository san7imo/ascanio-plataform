<?php

namespace App\Modules\IdentityAccess;

final class CapabilityCatalog
{
    public const OWNER = ['tenant.view', 'tenant.settings.update', 'tenant.members.view', 'tenant.members.revoke', 'tenant.roles.manage', 'tenant.members.assign_roles', 'tenant.ownership.transfer', 'tenant.members.invite', 'parties.view', 'parties.view_private', 'parties.create', 'parties.update'];

    public const DELEGABLE = ['tenant.view', 'tenant.settings.update', 'tenant.members.view', 'tenant.members.revoke', 'tenant.roles.manage', 'tenant.members.assign_roles', 'tenant.members.invite', 'parties.view', 'parties.view_private', 'parties.create', 'parties.update'];

    public const LABELS = [
        'parties.view' => 'Consultar fichas de contrapartes', 'parties.view_private' => 'Consultar datos privados e historial de contrapartes',
        'parties.create' => 'Crear fichas de contrapartes', 'parties.update' => 'Editar fichas de contrapartes',
        'tenant.members.invite' => 'Invitar al equipo',
        'tenant.view' => 'Consultar el espacio', 'tenant.settings.update' => 'Editar ajustes',
        'tenant.members.view' => 'Consultar membresías', 'tenant.members.revoke' => 'Gestionar estados de membresía',
        'tenant.roles.manage' => 'Administrar roles', 'tenant.members.assign_roles' => 'Asignar roles',
        'tenant.ownership.transfer' => 'Transferir el gobierno del espacio',
    ];
}
