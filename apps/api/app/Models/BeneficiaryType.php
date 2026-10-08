<?php

namespace App\Models;

use App\Models\Concerns\Deactivatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $class
 * @property string $code
 * @property string $label
 * @property array<int, array{key: string, label: string, data_type: string, required: bool}>|null $attribute_schema
 * @property bool $is_system
 */
class BeneficiaryType extends Model
{
    use Deactivatable;

    public const CLASS_INSTITUTIONAL = 'institutional';

    public const CLASS_INDIVIDUAL = 'individual';

    protected $fillable = ['class', 'code', 'label', 'attribute_schema', 'is_system'];

    protected function casts(): array
    {
        return [
            'attribute_schema' => 'array',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $type): void {
            $type->uuid ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<Beneficiary, $this> */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function isHousehold(): bool
    {
        return $this->class === self::CLASS_INSTITUTIONAL && $this->code === 'household';
    }
}
