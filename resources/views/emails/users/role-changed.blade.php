<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualización de rol</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #0f172a; background-color: #f8fafc; padding: 32px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);">
        <tr>
            <td style="background: linear-gradient(135deg, #2563eb, #1d4ed8); padding: 24px; color: #ffffff;">
                <h1 style="margin: 0; font-size: 22px;">Actualización en tu cuenta</h1>
                <p style="margin: 8px 0 0; font-size: 15px; opacity: 0.85;">Tu rol dentro de Juntify ha sido actualizado</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <p style="margin: 0 0 16px; font-size: 15px;">Hola <strong>{{ $user->full_name ?? $user->username }}</strong>,</p>
                <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6;">
                    Queremos informarte que el administrador <strong>{{ $admin->full_name ?? $admin->username }}</strong> actualizó tu rol en la plataforma.
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; border-radius: 10px; padding: 16px; margin: 16px 0;">
                    <tr>
                        <td style="font-size: 14px; color: #475569;">Rol anterior</td>
                        <td style="font-size: 14px; color: #1e293b; text-align: right;"><strong>{{ $oldRole }}</strong></td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; color: #475569; padding-top: 8px;">Nuevo rol</td>
                        <td style="font-size: 14px; color: #1d4ed8; text-align: right; padding-top: 8px;"><strong>{{ $newRole }}</strong></td>
                    </tr>
                </table>
                <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6;">
                    Si tienes alguna duda sobre los permisos asociados a tu nuevo rol, responde a este correo y estaremos encantados de ayudarte.
                </p>
                <p style="margin: 0; font-size: 14px; color: #64748b;">Gracias por ser parte de Juntify.</p>
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
