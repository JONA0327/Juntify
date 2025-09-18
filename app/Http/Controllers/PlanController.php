<?php

namespace App\Http\Controllers;

use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PlanController extends Controller
{
    public function limits(PlanLimitService $service): JsonResponse
    {
        $user = Auth::user();
        $limits = $service->getLimitsForUser($user);
        return response()->json($limits);
    }
}
