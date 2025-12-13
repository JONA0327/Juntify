# Configuración de Correo - Hostinger SMTP

## Configuración en `.env`

Tu archivo `.env` ha sido actualizado con la siguiente configuración:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=soporte@juntify.com
MAIL_PASSWORD=Cerounocero.com20182417
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="soporte@juntify.com"
MAIL_FROM_NAME="Juntify"
```

## Características

✅ **SMTP Hostinger**: Servidor de correo profesional
✅ **Puerto 465 SSL**: Conexión segura encriptada
✅ **Email de origen**: soporte@juntify.com
✅ **Verificación**: Email de prueba enviado exitosamente

## Funcionalidades de correo habilitadas

1. **Recuperación de Contraseña**: Los usuarios recibirán códigos de recuperación por correo
2. **Notificaciones**: Todos los correos del sistema se enviarán desde soporte@juntify.com
3. **Confirmación de Cuenta**: Emails de bienvenida y verificación

## Pruebas realizadas

✓ Conexión SMTP establecida correctamente
✓ Email de prueba enviado a jona03278@gmail.com
✓ Configuración lista para recuperación de contraseñas

## Comandos útiles

Para probar el envío de correo nuevamente:
```bash
php test-email-hostinger.php
```

Para enviar un código de recuperación de contraseña real:
```bash
# A través de la API
POST /api/password-reset/send-code
{
  "email": "usuario@example.com"
}
```

## Notas importantes

- No commits el `.env` (está en .gitignore)
- Los datos de autenticación SMTP están en `.env` (privado)
- Los correos se enviarán desde soporte@juntify.com
- La configuración es válida tanto en desarrollo como en producción
