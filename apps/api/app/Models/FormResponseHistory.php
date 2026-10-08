<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only edit history; never updated or deleted once written.
 *
 * @property int $id
 * @property int $form_response_id
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property array{before: array<string, mixed>, after: array<string, mixed>} $diff_json
 * @property string|null $reason
 * @property-read FormResponse $formResponse
 * @property-read User|null $changedBy
 */
class FormResponseHistory extends Model
{
    protected $fillable = ['form_response_id', 'changed_by', 'changed_at', 'diff_json', 'reason'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'diff_json' => 'array'];
    }

    /** @return BelongsTo<FormResponse, $this> */
    public function formResponse(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
