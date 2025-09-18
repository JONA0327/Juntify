<?php

namespace App\Http\Controllers;

use App\Models\OrganizationActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrganizationActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrganizationActivity::with('user');

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->query('organization_id'));
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->query('group_id'));
        }

        $activities = $query->orderByDesc('created_at')->get()->map(function ($activity) {
            $actor = null;
            if ($activity->user) {
                $actor = $activity->user->full_name ?: ($activity->user->username ?? null);
            }
            return [
                'id' => $activity->id,
                'actor' => $actor,
                'action' => $activity->action,
                'description' => $activity->description,
                'created_at' => $activity->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'activities' => $activities,
        ]);
    }
}
