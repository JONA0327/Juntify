<?php

namespace App\Services;

use App\Models\PlanLimit;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use Carbon\Carbon;

class PlanLimitService
{
    public function getLimitsForUser(User $user): array
    {
        $role = $user->roles ?? 'free';
        $plan = PlanLimit::where('role', $role)->first();

        // Defaults if not found
        $maxMeetings = $plan?->max_meetings_per_month;
        $maxMinutes  = $plan?->max_duration_minutes ?? 120;
        $allowPost   = $plan?->allow_postpone ?? true;
        $warnBefore  = $plan?->warn_before_minutes ?? 5;

        // Calculate used this month
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();
        $used = TranscriptionLaravel::where('username', $user->username)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

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
