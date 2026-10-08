<?php

namespace App\Http\Concerns;

use App\Enums\Channel;
use Illuminate\Http\Request;

/**
 * Reads the `X-Channel` header (web or mobile) used in permission checks.
 */
trait ResolvesRequestChannel
{
    protected function resolveChannel(Request $request): Channel
    {
        $value = $request->header('X-Channel');

        return $value !== null ? (Channel::tryFrom($value) ?? Channel::Web) : Channel::Web;
    }
}
