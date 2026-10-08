<?php

namespace App\Models;

use App\Models\Concerns\Deactivatable;
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
 * @property int $beneficiary_type_id
 * @property int|null $household_id
 * @property int $geography_node_id
 * @property string $name
 * @property Carbon|null $dob
 * @property string|null $gender
 * @property string|null $government_id_type
 * @property string|null $government_id_value
 * @property bool $government_id_checksum_valid
 * @property string|null $temporary_id
 * @property array<string, mixed>|null $attributes_json
 * @property bool $consent_given
 * @property Carbon|null $consent_captured_at
 * @property string $created_channel
 * @property-read BeneficiaryType $beneficiaryType
 * @property-read Beneficiary|null $household
 * @property-read Collection<int, Beneficiary> $members
 * @property-read GeographyNode $geographyNode
 */
class Beneficiary extends Model
{
    use Deactivatable;
    use LocationScoped;

    protected $fillable = [
        'beneficiary_type_id', 'household_id', 'geography_node_id', 'name', 'dob', 'gender',
        'government_id_type', 'government_id_value', 'government_id_checksum_valid',
        'temporary_id', 'attributes_json', 'consent_given', 'consent_captured_at',
        'created_channel', 'client_created_at',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'government_id_checksum_valid' => 'boolean',
            'attributes_json' => 'array',
            'consent_given' => 'boolean',
            'consent_captured_at' => 'datetime',
            'client_created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $beneficiary): void {
            $beneficiary->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<BeneficiaryType, $this> */
    public function beneficiaryType(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryType::class);
    }

    /** @return BelongsTo<Beneficiary, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(self::class, 'household_id');
    }

    /**
     * Individual beneficiaries tagged to this (institutional/household) record.
     *
     * @return HasMany<Beneficiary, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(self::class, 'household_id');
    }

    /** @return BelongsTo<GeographyNode, $this> */
    public function geographyNode(): BelongsTo
    {
        return $this->belongsTo(GeographyNode::class);
    }

    /** @return HasMany<BeneficiaryDuplicateFlag, $this> */
    public function duplicateFlagsRaised(): HasMany
    {
        return $this->hasMany(BeneficiaryDuplicateFlag::class, 'beneficiary_id');
    }
}
