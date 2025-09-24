<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\DeleteUserService;

class DeleteAccountController extends Controller
{
    public function destroy(Request $request, DeleteUserService $deleter)
    {
        $request->validate([
            'confirmation' => 'required|string',
        ]);

        $user = Auth::user();
        $expected = strtoupper($user->username);
        $input = strtoupper(trim($request->input('confirmation')));

        if ($input !== $expected) {
            return back()->with('error', 'El texto de confirmación no coincide. Escribe exactamente tu nombre de usuario: ' . $user->username);
        }

        $deleteDrive = (bool) $request->input('delete_drive', true);

        try {
            Auth::logout();
            $deleter->deleteUser($user, $deleteDrive);
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/')->with('success', 'Cuenta eliminada correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error al eliminar cuenta', [
                'user' => $user->username,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'No se pudo eliminar la cuenta: ' . $e->getMessage());
        }
    }
}
