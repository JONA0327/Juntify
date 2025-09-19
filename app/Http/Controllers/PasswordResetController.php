<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return view('auth.forgot');
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No encontramos un usuario con ese correo.',
        ]);

    $email = strtolower($data['email']);
    $user = User::where('email', $email)->firstOrFail();
        // Generar un código de 6 dígitos
        $code = (string) random_int(100000, 999999);

        $hasEmailCol = Schema::hasColumn('password_reset_tokens', 'email');
        $hasUserIdCol = Schema::hasColumn('password_reset_tokens', 'user_id');

        // Borrar códigos previos para ese usuario/correo (según columnas disponibles)
        DB::table('password_reset_tokens')
            ->where(function ($q) use ($email, $user, $hasEmailCol, $hasUserIdCol) {
                if ($hasEmailCol) {
                    $q->orWhere('email', $email);
                }
                if ($hasUserIdCol) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->delete();

        // Guardar el token (hash del código para no almacenar en claro) y created_at
        $insert = [
            'token' => Hash::make($code),
            'created_at' => now(),
        ];
        if ($hasEmailCol) {
            $insert['email'] = $email;
        }
        if ($hasUserIdCol) {
            $insert['user_id'] = $user->id;
        }
        DB::table('password_reset_tokens')->insert($insert);

        // Enviar correo con el código
        $this->sendResetCodeEmail($email, $code);

        return response()->json([
            'ok' => true,
            'message' => 'Código enviado a tu correo.'
        ]);
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'code'  => 'required|string',
        ]);

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();
        $hasEmailCol = Schema::hasColumn('password_reset_tokens', 'email');
        $hasUserIdCol = Schema::hasColumn('password_reset_tokens', 'user_id');

        $query = DB::table('password_reset_tokens');
        // Si existe la columna email, filtrar por email
        if ($hasEmailCol) {
            $query->where('email', $email);
        }
        // Si existe la columna user_id y tenemos usuario, filtrar también por user_id (AND)
        if ($hasUserIdCol && $user) {
            $query->where('user_id', $user->id);
        }
        $record = $query->first();
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Código inválido o expirado.'], 422);
        }

        // Verificar expiración: 10 minutos
        $createdAt = Carbon::parse($record->created_at);
        if (now()->greaterThan($createdAt->copy()->addMinutes(10))) {
            DB::table('password_reset_tokens')
                ->where(function ($q) use ($email, $user, $hasEmailCol, $hasUserIdCol) {
                    if ($hasEmailCol) {
                        $q->orWhere('email', $email);
                    }
                    if ($hasUserIdCol && $user) {
                        $q->orWhere('user_id', $user->id);
                    }
                })
                ->delete();
            return response()->json(['ok' => false, 'message' => 'El código ha expirado.'], 410);
        }

        // Verificar el código con el hash
        if (!Hash::check($data['code'], $record->token)) {
            return response()->json(['ok' => false, 'message' => 'Código incorrecto.'], 422);
        }

        // Código válido
        return response()->json(['ok' => true, 'message' => 'Código verificado.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'                    => 'required|email|exists:users,email',
            'code'                     => 'required|string',
            'password_hash'            => 'required|string',
            'password_confirmation_hash' => 'required|string|same:password_hash',
        ]);

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();
        $hasEmailCol = Schema::hasColumn('password_reset_tokens', 'email');
        $hasUserIdCol = Schema::hasColumn('password_reset_tokens', 'user_id');

        $query = DB::table('password_reset_tokens');
        if ($hasEmailCol) {
            $query->where('email', $email);
        }
        if ($hasUserIdCol && $user) {
            $query->where('user_id', $user->id);
        }
        $record = $query->first();
        if (!$record) {
            return response()->json(['ok' => false, 'message' => 'Código inválido o expirado.'], 422);
        }

        $createdAt = Carbon::parse($record->created_at);
        if (now()->greaterThan($createdAt->copy()->addMinutes(10))) {
            DB::table('password_reset_tokens')
                ->where(function ($q) use ($email, $user, $hasEmailCol, $hasUserIdCol) {
                    if ($hasEmailCol) {
                        $q->orWhere('email', $email);
                    }
                    if ($hasUserIdCol && $user) {
                        $q->orWhere('user_id', $user->id);
                    }
                })
                ->delete();
            return response()->json(['ok' => false, 'message' => 'El código ha expirado.'], 410);
        }

        if (!Hash::check($data['code'], $record->token)) {
            return response()->json(['ok' => false, 'message' => 'Código incorrecto.'], 422);
        }

        // Actualizar password: el backend de registro espera bcryptjs ya pre-hasheado.
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        $user->password = $data['password_hash'];
        $user->save();

        // Eliminar el token para evitar reutilización
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['ok' => true, 'message' => 'Contraseña actualizada correctamente.']);
    }

    protected function sendResetCodeEmail(string $email, string $code): void
    {
        Mail::send('emails.password_reset_code', ['code' => $code], function ($message) use ($email) {
            $message->to($email)
                ->subject('Código para recuperar tu contraseña - Juntify');
        });
    }
}
