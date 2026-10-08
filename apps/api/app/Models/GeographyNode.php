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
 * @property int|null $parent_id
 * @property int $level
 * @property string $label
 * @property string|null $code
 * @property-read GeographyNode|null $parent
 * @property-read Collection<int, GeographyNode> $children
 */
class GeographyNode extends Model
{
    use Deactivatable {
        deactivate as deactivateWithoutGuard;
    }

    protected $fillable = ['parent_id', 'level', 'label', 'code'];

    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            $node->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<GeographyNode, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<GeographyNode, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Blocks deactivation while active children exist.
     *
     * TODO: also block on active beneficiaries/households located at this node.
     */
    public function deactivate(string $reason, ?User $actor = null): bool
    {
        $activeChildren = $this->children()->get(['id', 'label']);

        if ($activeChildren->isNotEmpty()) {
            throw new CannotDeactivateException(
                "Cannot deactivate '{$this->label}': it has {$activeChildren->count()} active child location(s). ".
                'Deactivate or reassign them first.',
                $activeChildren->map(fn (self $child) => ['id' => $child->id, 'label' => $child->label])->all(),
            );
        }

        return $this->deactivateWithoutGuard($reason, $actor);
    }

    /**
     * Descendant IDs (excluding self) via per-level BFS; avoids recursive CTEs, which
     * shared-hosting MySQL may lack.
     */
    public function descendantIds(): array
    {
        $all = [];
        $frontier = [$this->id];

        while (true) {
            $children = static::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            if (empty($children)) {
                break;
            }

            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return $all;
    }

    /** Self plus all descendants: the set that a grant on this node covers. */
    public function selfAndDescendantIds(): array
    {
        return array_merge([$this->id], $this->descendantIds());
    }
}
