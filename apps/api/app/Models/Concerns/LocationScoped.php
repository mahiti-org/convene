<?php

namespace App\Models\Concerns;

use App\Scopes\LocationScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Auto-applies LocationScope to models with a `geography_node_id` column.
 */
trait LocationScoped
{
    public static function bootLocationScoped(): void
    {
        static::addGlobalScope(new LocationScope);
    }

    /** @param  Builder<static>  $query */
    public function scopeWithoutLocationScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(LocationScope::class);
    }
}
