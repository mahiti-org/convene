<?php

namespace App\Models;

use App\Enums\ScopeType;
use App\Services\Auth\DerivedScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $role_id
 * @property int|null $project_id
 * @property int|null $sub_project_id
 * @property int|null $location_set_id
 * @property ScopeType $scope_type
 * @property int|null $created_by
 * @property Carbon|null $revoked_at
 * @property-read User $user
 * @property-read Role $role
 * @property-read LocationSet|null $locationSet
 * @property-read User|null $createdBy
 */
class Grant extends Model
{
    protected $fillable = [
        'user_id', 'role_id', 'project_id', 'sub_project_id',
        'location_set_id', 'scope_type', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $grant): void {
            $grant->uuid ??= (string) Str::uuid();
        });

        // Resolve derived scope immediately: an unresolved derived Grant covers zero locations (see
        // PermissionEvaluator).
        static::created(function (self $grant): void {
            if ($grant->scope_type === ScopeType::Derived) {
                app(DerivedScopeResolver::class)->recomputeForGrant($grant);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<LocationSet, $this> */
    public function locationSet(): BelongsTo
    {
        return $this->belongsTo(LocationSet::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<SubProject, $this> */
    public function subProject(): BelongsTo
    {
        return $this->belongsTo(SubProject::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** Grants are revoked, never hard-deleted. */
    public function revoke(): bool
    {
        $this->revoked_at = now();

        return $this->save();
    }

    /**
     * @param  Builder<Grant>  $query
     * @return Builder<Grant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
