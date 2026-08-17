<?php

namespace Modules\Garage\Enums;

enum PermissionEnum: string
{
    case VIEW_ANY_GARAGE_VEHICLE = 'VIEW_ANY_GARAGE_VEHICLE';
    case VIEW_GARAGE_VEHICLE = 'VIEW_GARAGE_VEHICLE';
    case CREATE_GARAGE_VEHICLE = 'CREATE_GARAGE_VEHICLE';
    case UPDATE_GARAGE_VEHICLE = 'UPDATE_GARAGE_VEHICLE';
    case DELETE_GARAGE_VEHICLE = 'DELETE_GARAGE_VEHICLE';
    case RESTORE_GARAGE_VEHICLE = 'RESTORE_GARAGE_VEHICLE';
    case FORCE_DELETE_GARAGE_VEHICLE = 'FORCE_DELETE_GARAGE_VEHICLE';

    // Service jobs are captured by the Garage app; the dashboard is read-only
    // (owners review the job + photo proof + settlement status).
    case VIEW_ANY_GARAGE_SERVICE_JOB = 'VIEW_ANY_GARAGE_SERVICE_JOB';
    case VIEW_GARAGE_SERVICE_JOB = 'VIEW_GARAGE_SERVICE_JOB';

    /*
     * LARA-216 item 5 — replaces the allowlisted-role check in
     * Garage/LoginController::guardGarageAccess(), which was the ONLY
     * authorization on the entire Garage capture surface.
     *
     * Owner-only. Garage is an OWNER module, so this reaches the owner and no
     * staff role — the vertical stays gated exactly as it was, but by a grant
     * that can be handed to a bay technician without editing code.
     */
    case ACCESS_GARAGE_APP = 'ACCESS_GARAGE_APP';
}
