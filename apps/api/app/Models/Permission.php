<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property ResourceType $resource_type
 * @property PermissionAction $action
 * @property Channel $channel
 */
class Permission extends Model
{
    protected $fillable = ['resource_type', 'action', 'channel'];

    protected function casts(): array
    {
        return [
            'resource_type' => ResourceType::class,
            'action' => PermissionAction::class,
            'channel' => Channel::class,
        ];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
