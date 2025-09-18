<?php

namespace App\Services;

use App\Models\PlanLimit;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use App\Models\Organization;
use Carbon\Carbon;

class PlanLimitService
{
    /**
     * Get the current user's role within their active organization, if any.
     */
    protected function getUserOrganizationRole(User $user): ?string
    {
        $orgId = $user->current_organization_id;
        if (!$orgId) return null;
        $org = $user->organizations()->where('organization_id', $orgId)->first();
        return $org?->pivot?->rol;
    }

    /**
     * Determine whether the given role is considered unlimited in this system.
     */
    protected function isUnlimitedRole(?string $role): bool
    {
        if (!$role) return false;
        return in_array(strtolower($role), ['founder', 'developer', 'superadmin']);
    }

    public function getLimitsForUser(User $user): array
    {
        $role = $user->roles ?? 'free';
        $plan = PlanLimit::where('role', $role)->first();

        // Defaults if not found
        $maxMeetings = $plan?->max_meetings_per_month;
        $maxMinutes  = $plan?->max_duration_minutes ?? 120;
        $allowPost   = $plan?->allow_postpone ?? true;
        $warnBefore  = $plan?->warn_before_minutes ?? 5;

        // Calculate used this month (personal by default)
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();
        $used = TranscriptionLaravel::where('username', $user->username)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Organization-shared monthly quota for collaborators/administrators (non-owner)
        $orgRole = $this->getUserOrganizationRole($user);
        if (in_array($orgRole, ['colaborador', 'administrador'], true) && $user->current_organization_id) {
            $org = Organization::find($user->current_organization_id);
            if ($org && $org->admin) {
                $owner = $org->admin; // User model (UUID id)
                $ownerPlan = PlanLimit::where('role', $owner->roles ?? 'free')->first();
                $ownerMaxMeetings = $ownerPlan?->max_meetings_per_month;

                // If owner is an unlimited role, treat as unlimited
                if ($this->isUnlimitedRole($owner->roles) || is_null($ownerMaxMeetings)) {
                    $maxMeetings = null; // unlimited
                } else {
                    $maxMeetings = $ownerMaxMeetings; // share owner's monthly cap
                }

                // Aggregate used by all members of the organization within the month
                $memberUsernames = $org->users()->pluck('username');
                $used = TranscriptionLaravel::whereIn('username', $memberUsernames)
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->count();
            }
        }

        return [
            'role' => $role,
            'max_meetings_per_month' => $maxMeetings,
            'used_this_month' => $used,
            'remaining' => is_null($maxMeetings) ? null : max(0, $maxMeetings - $used),
            'max_duration_minutes' => $maxMinutes,
            'allow_postpone' => (bool)$allowPost,
            'warn_before_minutes' => $warnBefore,
        ];
    }

    public function canCreateAnotherMeeting(User $user): bool
    {
        $limits = $this->getLimitsForUser($user);
        if (is_null($limits['max_meetings_per_month'])) {
            return true; // unlimited
        }
        return $limits['used_this_month'] < $limits['max_meetings_per_month'];
    }
}
