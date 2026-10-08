<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when deactivating would orphan active dependents.
 */
class CannotDeactivateException extends Exception
{
    /** @var array<int, array{id: int, label: string}> */
    private array $blockingDependents;

    public function __construct(string $message, array $blockingDependents = [])
    {
        parent::__construct($message);
        $this->blockingDependents = $blockingDependents;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'blocking_dependents' => $this->blockingDependents,
        ], 422);
    }
}
