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
 * @property int $program_id
 * @property string $name
 * @property string|null $description
 * @property int|null $project_manager_user_id
 * @property-read Program $program
 * @property-read User|null $projectManager
 * @property-read Collection<int, SubProject> $subProjects
 * @property-read Collection<int, Activity> $activities
 */
class Project extends Model
{
    use Deactivatable {
        deactivate as deactivateWithoutGuard;
    }

    protected $fillable = ['program_id', 'name', 'description', 'project_manager_user_id'];

    protected static function booted(): void
    {
        static::creating(function (self $project): void {
            $project->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_user_id');
    }

    /** @return HasMany<SubProject, $this> */
    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return HasMany<ProjectLocation, $this> */
    public function projectLocations(): HasMany
    {
        return $this->hasMany(ProjectLocation::class);
    }

    /** Referential-integrity guard: cannot deactivate while active Activities exist under it. */
    public function deactivate(string $reason, ?User $actor = null): bool
    {
        $activeActivities = $this->activities()->get(['id', 'name']);

        if ($activeActivities->isNotEmpty()) {
            throw new CannotDeactivateException(
                "Cannot deactivate '{$this->name}': it has {$activeActivities->count()} active activity/activities. ".
                'Deactivate or reassign them first.',
                $activeActivities->map(fn (Activity $activity) => ['id' => $activity->id, 'label' => $activity->name])->all(),
            );
        }

        return $this->deactivateWithoutGuard($reason, $actor);
    }
}
