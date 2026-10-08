<?php

namespace App\Models;

use App\Models\Concerns\Deactivatable;
use App\Services\Form\FormSchemaValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Versioned form schema. `code` is stable across versions; responses FK `id`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 * @property int|null $beneficiary_type_id
 * @property int|null $activity_id
 * @property int|null $geography_level
 * @property string $periodicity
 * @property string $status
 * @property int $version
 * @property array<int, array<string, mixed>>|null $schema_json
 * @property int|null $predecessor_id
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property-read BeneficiaryType|null $beneficiaryType
 * @property-read Activity|null $activity
 * @property-read FormDefinition|null $predecessor
 */
class FormDefinition extends Model
{
    use Deactivatable;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DEPRECATED = 'deprecated';

    // Fillable for createNewDraftVersionFromPublished(); the form requests do not accept these
    // fields from users.
    protected $fillable = [
        'code', 'name', 'beneficiary_type_id', 'activity_id', 'geography_level',
        'periodicity', 'schema_json', 'created_by', 'version', 'predecessor_id',
    ];

    // Mirrors DB defaults, which Eloquent does not reload after create(), so new instances are
    // correct without ->fresh().
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'version' => 1,
    ];

    protected function casts(): array
    {
        return ['schema_json' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $form): void {
            $form->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<BeneficiaryType, $this> */
    public function beneficiaryType(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryType::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<FormDefinition, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_id');
    }

    /** @return HasMany<FormResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Locks the draft. Published schema_json is immutable; edit via createNewDraftVersionFromPublished().
     */
    public function publish(): void
    {
        if (! $this->isDraft()) {
            throw new RuntimeException("Form '{$this->code}' v{$this->version} is not a draft and cannot be published.");
        }

        if (empty($this->schema_json)) {
            throw new RuntimeException("Form '{$this->code}' has no widgets to publish.");
        }

        $errors = (new FormSchemaValidator)->validate($this->schema_json);
        if (! empty($errors)) {
            throw new RuntimeException("Form '{$this->code}' schema is invalid: ".implode(' ', $errors));
        }

        $this->status = self::STATUS_PUBLISHED;
        $this->published_at = now();
        $this->save();
    }

    /**
     * Never mutates a published form: creates a new draft (version+1, predecessor_id set) so old
     * responses keep their schema.
     */
    public function createNewDraftVersionFromPublished(): self
    {
        if (! $this->isPublished()) {
            throw new RuntimeException("Only a published form can be revised into a new version; '{$this->code}' v{$this->version} is {$this->status}.");
        }

        return self::create([
            'code' => $this->code,
            'name' => $this->name,
            'beneficiary_type_id' => $this->beneficiary_type_id,
            'activity_id' => $this->activity_id,
            'geography_level' => $this->geography_level,
            'periodicity' => $this->periodicity,
            'schema_json' => $this->schema_json,
            'version' => $this->version + 1,
            'predecessor_id' => $this->id,
            'created_by' => $this->created_by,
        ]);
    }
}
