<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\GoogleToken;
use App\Services\GoogleDriveService;
use App\Services\GoogleCalendarService;
use App\Http\Controllers\Auth\GoogleAuthController;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(GoogleDriveService $drive, GoogleAuthController $auth)
    {
        $user = Auth::user();

        $token          = GoogleToken::where('username', $user->username)->first();
        $lastSync       = optional($token)->updated_at;
        $subfolders     = collect();
        $folderMessage  = null;

        if (!$token || !$token->access_token) {
            $driveConnected    = false;
            $calendarConnected = false;
            $folder           = null;
            return view('profile', compact('user', 'driveConnected', 'calendarConnected', 'folder', 'subfolders', 'lastSync'));
        }

        $client = $drive->getClient();
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

        $driveConnected = true;

        $calendarService = new GoogleCalendarService();
        $calendarClient  = $calendarService->getClient();
        $calendarClient->setAccessToken($client->getAccessToken());

        try {
            $calendarService->getCalendar()->calendarList->get('primary');
            $calendarConnected = true;
        } catch (\Throwable $e) {
            $calendarConnected = false;
        }

        $folder = null;
        if ($token->recordings_folder_id) {
            try {
                $file       = $drive->getDrive()->files->get(
                    $token->recordings_folder_id,
                    ['fields' => 'name']
                );
                $folderName = $file->getName() ?? "recordings_{$user->username}";

                $folder = Folder::updateOrCreate(
                    [
                        'google_token_id' => $token->id,
                        'google_id'       => $token->recordings_folder_id,
                    ],
                    [
                        'name'      => $folderName,
                        'parent_id' => null,
                    ]
                );

                $subfolders = Subfolder::where('folder_id', $folder->id)->get();
            } catch (\Throwable $e) {
                $token->update(['recordings_folder_id' => null]);
                $folderMessage = 'No se pudo acceder a la carpeta principal. Configúrala nuevamente.';
            }
        }

        return view('profile', compact('user', 'driveConnected', 'calendarConnected', 'folder', 'subfolders', 'lastSync', 'folderMessage'));
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
