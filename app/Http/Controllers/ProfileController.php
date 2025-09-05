<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\GoogleToken;
use App\Services\GoogleDriveService;
use App\Services\GoogleCalendarService;
use App\Services\GoogleTokenRefreshService;
use App\Http\Controllers\Auth\GoogleAuthController;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(GoogleDriveService $drive, GoogleAuthController $auth, GoogleTokenRefreshService $tokenService)
    {
        $user = Auth::user();

        // Usar el nuevo servicio para verificar y renovar automáticamente el token
        $connectionStatus = $tokenService->checkConnectionStatus($user);

        $token = GoogleToken::where('username', $user->username)->first();
        $lastSync = optional($token)->updated_at;
        $subfolders = collect();
        $folderMessage = null;

        $folder = null;
        if ($token && $token->recordings_folder_id) {
            $folder = Folder::where('google_token_id', $token->id)
                           ->where('google_id', $token->recordings_folder_id)
                           ->first();
            if ($folder) {
                $subfolders = Subfolder::where('folder_id', $folder->id)->get();
            }
        }

        // Si no hay conexión válida
        if (!$connectionStatus['drive_connected'] && !$connectionStatus['calendar_connected']) {
            $driveConnected = false;
            $calendarConnected = false;
            $folderMessage = $connectionStatus['needs_reconnection']
                ? 'Token expirado. Se intentó renovar automáticamente pero falló. Necesitas reconectarte.'
                : $connectionStatus['message'];

            return view('profile', compact('user', 'driveConnected', 'calendarConnected', 'folder', 'subfolders', 'lastSync', 'folderMessage'));
        }

        $driveConnected = $connectionStatus['drive_connected'];
        $calendarConnected = $connectionStatus['calendar_connected'];

        // Si hay token válido, obtener información de la carpeta
        if ($token && $token->recordings_folder_id) {
            try {
                $client = $drive->getClient();

                // Usar el método del modelo para obtener el token como array completo
                $tokenArray = $token->getTokenArray();
                if (empty($tokenArray['access_token'])) {
                    throw new \Exception("Token inválido");
                }

                $client->setAccessToken($tokenArray);

                $file = $drive->getDrive()->files->get(
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
                $folderMessage = 'No se pudo acceder a la carpeta principal. El token se renovó automáticamente pero hay problemas de permisos.';
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
