<?php

namespace App\Services\Tasks;

class TaskAccessContext
{
    public function __construct(
        public string $mode,
        public bool $inOrganization,
        public ?int $organizationId,
        public ?string $organizationRole,
        public bool $isBusiness,
        public bool $isLimitedPlan
    ) {
    }

    public function restrictsApprovedColumn(): bool
    {
        return $this->mode === 'organization_only';
    }

    public function blocksKanban(): bool
    {
        return $this->isBusiness && !$this->inOrganization;
    }

    public function hasAccess(): bool
    {
        return $this->mode !== 'blocked';
    }
}
