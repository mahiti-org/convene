<?php

namespace App\Jobs;

use App\Services\Auth\DerivedScopeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Triggered by UserLocation/ProjectLocation create/delete observers, so derived-scope
 * Grants are kept in sync without recomputing intersections live on every request.
 */
class RecomputeDerivedLocationSets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?int $projectId = null,
    ) {}

    public function handle(DerivedScopeResolver $resolver): void
    {
        if ($this->userId !== null) {
            $resolver->recomputeForUser($this->userId);
        }

        if ($this->projectId !== null) {
            $resolver->recomputeForProject($this->projectId);
        }
    }
}
