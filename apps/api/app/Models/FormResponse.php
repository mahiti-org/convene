<?php

namespace App\Models;

use App\Models\Concerns\LocationScoped;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $form_definition_id
 * @property int|null $beneficiary_id
 * @property int|null $activity_id
 * @property int $geography_node_id
 * @property int $submitted_by_user_id
 * @property string $channel
 * @property Carbon $submitted_at
 * @property Carbon|null $client_created_at
 * @property string $sync_status
 * @property array<string, mixed> $values_json
 * @property-read FormDefinition $formDefinition
 * @property-read Beneficiary|null $beneficiary
 * @property-read Activity|null $activity
 * @property-read GeographyNode $geographyNode
 * @property-read User $submittedBy
 * @property-read Collection<int, FormResponseHistory> $history
 */
class FormResponse extends Model
{
    use LocationScoped;

    protected $fillable = [
        'form_definition_id', 'beneficiary_id', 'activity_id', 'geography_node_id',
        'submitted_by_user_id', 'channel', 'submitted_at', 'client_created_at',
        'sync_status', 'values_json',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'client_created_at' => 'datetime',
            'values_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $response): void {
            $response->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<FormDefinition, $this> */
    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class);
    }

    /** @return BelongsTo<Beneficiary, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<GeographyNode, $this> */
    public function geographyNode(): BelongsTo
    {
        return $this->belongsTo(GeographyNode::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** @return HasMany<FormResponseHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(FormResponseHistory::class);
    }

    /**
     * Appends a history row, then merges $newValues (partial or full) into values_json.
     */
    public function recordEdit(array $newValues, ?User $actor, ?string $reason = null): void
    {
        $before = $this->values_json;
        $after = array_merge($before, $newValues);

        $this->history()->create([
            'changed_by' => $actor?->id,
            'changed_at' => now(),
            'diff_json' => ['before' => $before, 'after' => $after],
            'reason' => $reason,
        ]);

        $this->values_json = $after;
        $this->save();
    }
}
