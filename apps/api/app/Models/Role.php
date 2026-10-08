<?php

namespace App\Models;

use App\Models\Concerns\Deactivatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property int|null $project_id
 * @property-read Collection<int, Permission> $permissions
 */
class Role extends Model
{
    use Deactivatable;

    protected $fillable = ['name', 'description', 'is_system', 'project_id'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            $role->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /** @return HasMany<Grant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
