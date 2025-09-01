<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserApiKeyController extends Controller
{
    public function show(Request $request)
    {
        $apiKey = optional($request->user()->apiKey)->api_key;

        return response()->json(['api_key' => $apiKey]);
    }

    public function generate(Request $request)
    {
        $user = $request->user();
        $apiKey = Str::random(40);

        $user->apiKey()->updateOrCreate([], ['api_key' => $apiKey]);

        return response()->json(['api_key' => $apiKey]);
    }
}
