<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\UserApiKey;

class AuthTokenController extends Controller
{
    public function getApiKey(Request $request)
    {
        $user = $request->user();
        $key = $user->apiKey()->first();

        if (!$key) {
            $key = UserApiKey::create([
                'user_id' => $user->id,
                'api_key' => Str::random(64),
            ]);
        }

        return response()->json(['api_key' => $key->api_key]);
    }

    public function rotateApiKey(Request $request)
    {
        $user = $request->user();
        $key = $user->apiKey()->first();

        if (!$key) {
            $key = new UserApiKey();
            $key->user_id = $user->id;
        }

        $key->api_key = Str::random(64);
        $key->save();

        return response()->json([
            'api_key' => $key->api_key,
            'rotated' => true,
        ]);
    }
}
