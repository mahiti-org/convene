<?php

namespace App\Enums;

enum ScopeType: string
{
    // Location set is assigned directly to the Grant by an admin.
    case Explicit = 'explicit';

    // Intersection of the user's UserLocation rows and the project's ProjectLocation rows; see
    // DerivedScopeResolver.
    case Derived = 'derived';
}
