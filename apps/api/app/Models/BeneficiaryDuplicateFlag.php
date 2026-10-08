<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $beneficiary_id
 * @property int $matched_beneficiary_id
 * @property string $match_type
 * @property float|null $match_score
 * @property string $status
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property-read Beneficiary $beneficiary
 * @property-read Beneficiary $matchedBeneficiary
 */
class BeneficiaryDuplicateFlag extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_MERGED = 'merged';

    protected $fillable = [
        'beneficiary_id', 'matched_beneficiary_id', 'match_type', 'match_score', 'status',
    ];

    protected function casts(): array
    {
        return ['match_score' => 'float', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Beneficiary, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    /** @return BelongsTo<Beneficiary, $this> */
    public function matchedBeneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class, 'matched_beneficiary_id');
    }

    public function dismiss(?User $actor = null): bool
    {
        $this->status = self::STATUS_DISMISSED;
        $this->resolved_by = $actor?->id;
        $this->resolved_at = now();

        return $this->save();
    }
}
