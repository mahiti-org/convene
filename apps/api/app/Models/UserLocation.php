<?php

namespace App\Models;

use App\Jobs\RecomputeDerivedLocationSets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLocation extends Model
{
    protected $fillable = ['user_id', 'geography_node_id'];

    protected static function booted(): void
    {
        $recompute = fn (self $ul) => RecomputeDerivedLocationSets::dispatch(userId: $ul->user_id);

        static::created($recompute);
        static::deleted($recompute);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function geographyNode(): BelongsTo
    {
        return $this->belongsTo(GeographyNode::class);
    }
}
