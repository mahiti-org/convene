<?php

namespace App\Services\Auth;

/**
 * Per-request authorization context. Unresolved fails closed; null means unrestricted; an array
 * lists allowed node IDs.
 */
class ScopeContext
{
    private ?array $allowedGeographyNodeIds = null;

    private bool $resolved = false;

    private ?int $activeProjectId = null;

    public function setAllowedGeographyNodeIds(?array $nodeIds): void
    {
        $this->allowedGeographyNodeIds = $nodeIds;
        $this->resolved = true;
    }

    public function allowedGeographyNodeIds(): ?array
    {
        return $this->allowedGeographyNodeIds;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setActiveProjectId(?int $projectId): void
    {
        $this->activeProjectId = $projectId;
    }

    public function activeProjectId(): ?int
    {
        return $this->activeProjectId;
    }
}
