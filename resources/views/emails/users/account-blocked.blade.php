<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de tu cuenta</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #0f172a; background-color: #f8fafc; padding: 32px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);">
        <tr>
            <td style="background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 24px; color: #ffffff;">
                <h1 style="margin: 0; font-size: 22px;">Importante: acceso restringido</h1>
                <p style="margin: 8px 0 0; font-size: 15px; opacity: 0.85;">Tu acceso a Juntify ha sido actualizado</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <p style="margin: 0 0 16px; font-size: 15px;">Hola <strong>{{ $user->full_name ?? $user->username }}</strong>,</p>
                <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6;">
                    El administrador <strong>{{ $admin->full_name ?? $admin->username }}</strong> ha {{ $permanent ? 'bloqueado permanentemente' : 'restringido temporalmente' }} tu acceso a la plataforma.
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef2f2; border-radius: 10px; padding: 16px; margin: 16px 0;">
                    <tr>
                        <td style="font-size: 14px; color: #991b1b;">Motivo</td>
                        <td style="font-size: 14px; color: #7f1d1d; text-align: right;"><strong>{{ $reason }}</strong></td>
                    </tr>
                    @if(! $permanent && $blockedUntil)
                        <tr>
                            <td style="font-size: 14px; color: #991b1b; padding-top: 8px;">Duración</td>
                            <td style="font-size: 14px; color: #7f1d1d; text-align: right; padding-top: 8px;"><strong>Hasta {{ $blockedUntil->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</strong></td>
                        </tr>
                    @endif
                </table>
                <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6;">
                    Si consideras que se trata de un error o necesitas más información, responde a este correo con los detalles del caso para que podamos ayudarte.
                </p>
                <p style="margin: 0; font-size: 14px; color: #64748b;">Gracias por tu comprensión.</p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #0f172a; color: #94a3b8; text-align: center; padding: 16px; font-size: 12px;">
                © {{ date('Y') }} Juntify. Todos los derechos reservados.
            </td>
        </tr>
    </table>
</body>
</html>
