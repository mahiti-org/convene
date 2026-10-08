<?php

namespace App\Models;

use App\Models\Concerns\Deactivatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $category
 * @property string $code
 * @property string $label
 * @property int $sort_order
 * @property bool $is_system
 */
class MasterDefinition extends Model
{
    use Deactivatable;

    protected $fillable = ['category', 'code', 'label', 'sort_order', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $master): void {
            $master->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @param  Builder<MasterDefinition>  $query
     * @return Builder<MasterDefinition>
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
