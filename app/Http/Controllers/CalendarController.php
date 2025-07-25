<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    protected function applyToken(GoogleCalendarService $calendar): GoogleToken
    {
        $token = GoogleToken::where('username', Auth::user()->username)->firstOrFail();

        $client = $calendar->getClient();
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in'    => max(1, Carbon::parse($token->expiry_date)->timestamp - time()),
            'created'       => time(),
        ]);

        if ($client->isAccessTokenExpired() && $token->refresh_token) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (!isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date'  => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            }
        }

        return $token;
    }

    public function createEvent(Request $request, GoogleCalendarService $calendar)
    {
        $request->validate([
            'summary' => 'required|string',
            'start'   => 'required|date',
            'end'     => 'required|date',
            'calendarId' => 'sometimes|string',
        ]);

        $this->applyToken($calendar);

        $id = $calendar->createEvent(
            $request->input('summary'),
            $request->input('start'),
            $request->input('end'),
            $request->input('calendarId', 'primary')
        );

        return response()->json(['id' => $id]);
    }
}
