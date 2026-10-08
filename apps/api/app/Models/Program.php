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
 * @property string $name
 * @property string|null $description
 * @property int|null $program_manager_user_id
 * @property-read User|null $programManager
 * @property-read Collection<int, Project> $projects
 */
class Program extends Model
{
    use Deactivatable {
        deactivate as deactivateWithoutGuard;
    }

    protected $fillable = ['name', 'description', 'program_manager_user_id'];

    protected static function booted(): void
    {
        static::creating(function (self $program): void {
            $program->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function programManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'program_manager_user_id');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Referential-integrity guard: cannot deactivate while active Projects exist under it. */
    public function deactivate(string $reason, ?User $actor = null): bool
    {
        $activeProjects = $this->projects()->get(['id', 'name']);

        if ($activeProjects->isNotEmpty()) {
            throw new CannotDeactivateException(
                "Cannot deactivate '{$this->name}': it has {$activeProjects->count()} active project(s). ".
                'Deactivate or reassign them first.',
                $activeProjects->map(fn (Project $project) => ['id' => $project->id, 'label' => $project->name])->all(),
            );
        }

        return $this->deactivateWithoutGuard($reason, $actor);
    }
}
