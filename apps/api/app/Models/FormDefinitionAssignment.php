<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Assigns a form by its stable `code`, not a version, to a user or a role.
 *
 * @property int $id
 * @property string $form_code
 * @property string $assignable_type
 * @property int $assignable_id
 */
class FormDefinitionAssignment extends Model
{
    public const TYPE_USER = 'user';

    public const TYPE_ROLE = 'role';

    protected $fillable = ['form_code', 'assignable_type', 'assignable_id'];
}
