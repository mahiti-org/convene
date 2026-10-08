<?php

namespace App\Enums;

// No "delete" action by design; use SoftDelete with a reason.
enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case SoftDelete = 'soft_delete';
    case Export = 'export';
    case Search = 'search';
    case Approve = 'approve';
    case Reject = 'reject';
    case Verify = 'verify';
    case Assign = 'assign';
    case Import = 'import';
}
