<?php

namespace App\Services\Tasks;

use App\Models\TaskLaravel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TaskAccessService
{
    /** @var array<string, TaskAccessContext> */
    protected array $contextCache = [];

    /** @var array<int, array<int>> */
    protected array $organizationMeetingsCache = [];

    /** @var array<int, int|null> */
    protected array $taskOrganizationCache = [];

    public function getContext(User $user): TaskAccessContext
    {
        $cacheKey = (string) $user->id;
        if (isset($this->contextCache[$cacheKey])) {
            return $this->contextCache[$cacheKey];
        }

        $role = strtolower((string) ($user->roles ?? ''));
        $plan = strtolower((string) ($user->plan_code ?? ''));

        $isElevated = $this->matchesAny([$role], ['superadmin', 'founder', 'developer', 'admin']);
        $isBusiness = $this->matchesAny([$role, $plan], ['business', 'negocios', 'buisness']);
        $isEnterprise = $this->matchesAny([$role, $plan], ['enterprise', 'empresa', 'enterprice']);

        $isLimitedPlan = $this->matchesAny([$role, $plan], ['free', 'gratis'])
            || $this->matchesAny([$role, $plan], ['basic', 'básico', 'basico']);

        $orgContext = $this->getOrganizationContext($user);
        $inOrganization = $orgContext['id'] !== null;
        $orgRole = $orgContext['role'];

        $mode = 'blocked';
        if ($isElevated || $isBusiness || $isEnterprise || !$isLimitedPlan) {
            $mode = 'full';
        } elseif ($inOrganization && $orgRole && in_array($orgRole, ['invitado', 'colaborador', 'administrador'], true)) {
            $mode = 'organization_only';
        }

        $context = new TaskAccessContext(
            $mode,
            $inOrganization,
            $orgContext['id'],
            $orgRole,
            $isBusiness,
            $isLimitedPlan
        );

        return $this->contextCache[$cacheKey] = $context;
    }

    public function applyVisibilityScope(Builder $query, User $user): Builder
    {
        $context = $this->getContext($user);

        if (!$context->hasAccess()) {
            return $query->whereRaw('1 = 0');
        }

        if ($context->mode === 'organization_only') {
            $meetingIds = $this->getOrganizationMeetingIds($context->organizationId);
            if (empty($meetingIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('meeting_id', $meetingIds);
            }
        }

        return $query;
    }

    /**
     * Determine if the given user can receive tasks, optionally ensuring the task
     * belongs to their accessible scope.
     */
    public function userCanReceiveTask(User $user, ?TaskLaravel $task = null): array
    {
        $context = $this->getContext($user);

        if (!$context->hasAccess()) {
            return ['allowed' => false, 'reason' => 'plan_blocked'];
        }

        if ($context->mode === 'organization_only') {
            $orgId = $context->organizationId;
            if (!$orgId) {
                return ['allowed' => false, 'reason' => 'organization_missing'];
            }

            if ($task) {
                $taskOrg = $this->getTaskOrganizationId($task);
                if (!$taskOrg || $taskOrg !== $orgId) {
                    return ['allowed' => false, 'reason' => 'outside_organization'];
                }
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function taskBelongsToOrganization(TaskLaravel $task, ?int $organizationId): bool
    {
        if (!$organizationId) {
            return false;
        }

        $taskOrg = $this->getTaskOrganizationId($task);
        return $taskOrg !== null && $taskOrg === $organizationId;
    }

    public function meetingBelongsToOrganization(int $meetingId, ?int $organizationId): bool
    {
        if (!$organizationId) {
            return false;
        }

        $meetingIds = $this->getOrganizationMeetingIds($organizationId);
        return in_array($meetingId, $meetingIds, true);
    }

    protected function getOrganizationMeetingIds(?int $organizationId): array
    {
        if (!$organizationId) {
            return [];
        }

        if (isset($this->organizationMeetingsCache[$organizationId])) {
            return $this->organizationMeetingsCache[$organizationId];
        }

        if (!Schema::hasTable('meeting_content_relations')
            || !Schema::hasTable('meeting_content_containers')
            || !Schema::hasTable('groups')) {
            return $this->organizationMeetingsCache[$organizationId] = [];
        }

        $ids = DB::table('meeting_content_relations')
            ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
            ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
            ->where('groups.id_organizacion', $organizationId)
            ->pluck('meeting_content_relations.meeting_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->organizationMeetingsCache[$organizationId] = $ids;
    }

    protected function getTaskOrganizationId(TaskLaravel $task): ?int
    {
        $taskId = (int) $task->id;
        if (array_key_exists($taskId, $this->taskOrganizationCache)) {
            return $this->taskOrganizationCache[$taskId];
        }

        if (!$task->meeting_id) {
            return $this->taskOrganizationCache[$taskId] = null;
        }

        if (!Schema::hasTable('meeting_content_relations')
            || !Schema::hasTable('meeting_content_containers')
            || !Schema::hasTable('groups')) {
            return $this->taskOrganizationCache[$taskId] = null;
        }

        $orgId = DB::table('meeting_content_relations')
            ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
            ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
            ->where('meeting_content_relations.meeting_id', $task->meeting_id)
            ->value('groups.id_organizacion');

        return $this->taskOrganizationCache[$taskId] = $orgId ? (int) $orgId : null;
    }

    protected function getOrganizationContext(User $user): array
    {
        $orgId = $user->current_organization_id ? (int) $user->current_organization_id : null;
        $role = null;

        if ($orgId) {
            $relation = $user->organizations()
                ->where('organization_id', $orgId)
                ->select('organization_id', 'rol')
                ->first();

            $role = $relation?->pivot?->rol;
            if ($role) {
                $role = strtolower($role);
            }
        }

        return ['id' => $orgId, 'role' => $role];
    }

    /**
     * @param array<int, string> $values
     * @param array<int, string> $needles
     */
    protected function matchesAny(array $values, array $needles): bool
    {
        $normalized = array_filter(array_map(function ($value) {
            $value = strtolower((string) $value);
            return $value === '' ? null : $value;
        }, $values));

        if (empty($normalized)) {
            return false;
        }

        $targets = array_filter(array_map(fn ($needle) => strtolower((string) $needle), $needles));

        foreach ($normalized as $value) {
            foreach ($targets as $needle) {
                if ($value === $needle || str_contains($value, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
