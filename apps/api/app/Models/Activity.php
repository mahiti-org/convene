<?php

namespace App\Models;

use App\Exceptions\CannotDeactivateException;
use App\Models\Concerns\Deactivatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $project_id
 * @property int|null $parent_activity_id
 * @property int|null $beneficiary_type_id
 * @property string $name
 * @property string|null $description
 * @property-read Project $project
 * @property-read Activity|null $parentActivity
 * @property-read Collection<int, Activity> $subActivities
 * @property-read BeneficiaryType|null $beneficiaryType
 */
class Activity extends Model
{
    use Deactivatable {
        deactivate as deactivateWithoutGuard;
    }

    protected $fillable = ['project_id', 'parent_activity_id', 'beneficiary_type_id', 'name', 'description'];

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function parentActivity(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_activity_id');
    }

    /** @return HasMany<Activity, $this> */
    public function subActivities(): HasMany
    {
        return $this->hasMany(self::class, 'parent_activity_id');
    }

    /** @return BelongsTo<BeneficiaryType, $this> */
    public function beneficiaryType(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryType::class);
    }

    /** Same children-guard pattern as GeographyNode: cannot deactivate with active sub-activities. */
    public function deactivate(string $reason, ?User $actor = null): bool
    {
        $activeSubActivities = $this->subActivities()->get(['id', 'name']);

        if ($activeSubActivities->isNotEmpty()) {
            throw new CannotDeactivateException(
                "Cannot deactivate '{$this->name}': it has {$activeSubActivities->count()} active sub-activity/activities. ".
                'Deactivate or reassign them first.',
                $activeSubActivities->map(fn (self $sub) => ['id' => $sub->id, 'label' => $sub->name])->all(),
            );
        }

        return $this->deactivateWithoutGuard($reason, $actor);
    }
}
