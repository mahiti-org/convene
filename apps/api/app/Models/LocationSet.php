<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $name
 * @property string $owner_type
 * @property int $owner_id
 * @property-read Collection<int, GeographyNode> $geographyNodes
 */
class LocationSet extends Model
{
    protected $fillable = ['name', 'owner_type', 'owner_id'];

    protected static function booted(): void
    {
        static::creating(function (self $set): void {
            $set->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsToMany<GeographyNode, $this> */
    public function geographyNodes(): BelongsToMany
    {
        return $this->belongsToMany(GeographyNode::class, 'location_set_members');
    }

    /** Replace membership with the given node IDs (already expanded to include descendants). */
    public function replaceMembers(array $geographyNodeIds): void
    {
        $this->geographyNodes()->sync($geographyNodeIds);
    }
}
