<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Never hard-delete; use this instead of ->delete() so a reason is always captured.
 *
 * @property Carbon|null $deleted_at
 * @property string|null $deactivation_reason
 * @property int|null $deactivated_by
 */
trait Deactivatable
{
    use SoftDeletes;

    public function deactivate(string $reason, ?User $actor = null): bool
    {
        $this->deactivation_reason = $reason;
        $this->deactivated_by = $actor?->id;
        $this->save();

        return $this->delete();
    }

    public function reactivate(): bool
    {
        $this->deactivation_reason = null;
        $this->deactivated_by = null;
        $this->save();

        return $this->restore();
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }
}
