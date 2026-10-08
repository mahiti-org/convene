<?php

namespace App\Models;

use App\Models\Concerns\Deactivatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $project_id
 * @property string $name
 * @property-read Project $project
 */
class SubProject extends Model
{
    use Deactivatable;

    protected $fillable = ['project_id', 'name'];

    protected static function booted(): void
    {
        static::creating(function (self $subProject): void {
            $subProject->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
