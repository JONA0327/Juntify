# Instrucciones de despliegue

Después de aplicar esta actualización, ejecuta inmediatamente el comando:

```
php artisan encrypt:drive-tokens
```

Este paso migra los tokens legacy de Google Drive almacenados en texto plano y garantiza que queden protegidos con los nuevos mutadores. El comando genera un resumen de cuántos registros fueron actualizados, por lo que puede ejecutarse de forma segura más de una vez si es necesario.
