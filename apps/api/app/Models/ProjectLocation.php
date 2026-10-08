<?php

namespace App\Models;

use App\Jobs\RecomputeDerivedLocationSets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectLocation extends Model
{
    protected $fillable = ['project_id', 'geography_node_id'];

    protected static function booted(): void
    {
        $recompute = fn (self $pl) => RecomputeDerivedLocationSets::dispatch(projectId: $pl->project_id);

        static::created($recompute);
        static::deleted($recompute);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<GeographyNode, $this> */
    public function geographyNode(): BelongsTo
    {
        return $this->belongsTo(GeographyNode::class);
    }
}
