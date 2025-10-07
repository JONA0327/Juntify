# API de Integraciones Juntify

Esta guía explica cómo consumir los endpoints expuestos para paneles o integraciones externas.
Todos los ejemplos asumen que la API se sirve desde `https://app.juntify.com` (ajusta el dominio
según tu entorno).

## Autenticación

1. **Login con credenciales Juntify**
   ```http
   POST /api/integrations/login
   Content-Type: application/json

   {
     "email": "usuario@empresa.com",
     "password": "********"
   }
   ```
   Respuesta 200:
   ```json
   {
     "token": "token_de_api",
     "expires_at": "2024-05-11T00:00:00Z"
   }
   ```

2. **Obtener token desde una sesión activa (opcional)**
   Desde el navegador autenticado puedes invocar:
   ```http
   POST /api/integrations/token
   X-CSRF-TOKEN: {token csrf}
   ```
   Devuelve el mismo objeto `{ token, expires_at }` para usarlo desde tu panel externo.

3. **Usar el token**
   Incluye el header `Authorization: Bearer {token}` en todas las peticiones protegidas.

4. **Cerrar sesión**
   ```http
   POST /api/integrations/logout
   Authorization: Bearer {token}
   ```

## Reuniones

### Listar últimas reuniones del usuario autenticado
```http
GET /api/integrations/meetings
Authorization: Bearer {token}
```
Respuesta 200:
```json
{
  "data": [
    {
      "id": 1234,
      "title": "Demo con cliente",
      "created_at": "2024-05-10T17:00:12Z",
      "created_at_readable": "10/05/2024 14:00"
    }
  ]
}
```

### Obtener detalles de una reunión
```http
GET /api/integrations/meetings/{meetingId}
Authorization: Bearer {token}
```
Respuesta 200:
```json
{
  "data": {
    "id": 1234,
    "title": "Demo con cliente",
    "created_at": "2024-05-10T17:00:12Z",
    "ju": {
      "available": true,
      "source": "cache|fresh|missing",
      "needs_encryption": false,
      "summary": "Resumen de la reunión...",
      "key_points": ["Punto 1", "Punto 2"],
      "tasks": [{"title": "Enviar propuesta", "owner": "María"}],
      "transcription": "Texto completo...",
      "speakers": [{"name": "Orador 1"}],
      "segments": [{"speaker": "Orador 1", "text": "Hola"}]
    },
    "audio": {
      "available": true,
      "source": "drive_id|direct_url",
      "filename": "demo_cliente.mp3",
      "mime_type": "audio/mpeg",
      "size_bytes": 10485760,
      "stream_url": "https://app.juntify.com/api/integrations/meetings/1234/audio"
    }
  }
}
```

* `ju.available` indica si se pudo obtener y desencriptar el archivo `.ju`.
* `audio.stream_url` se usa para reproducir/descargar el audio directamente desde la API.

### Descargar audio de la reunión
```http
GET /api/integrations/meetings/{meetingId}/audio
Authorization: Bearer {token}
Accept: audio/*
```
Devuelve el flujo binario (`Content-Type` según el archivo original). Usa este endpoint para
reproducción en el panel sin requerir acceso directo a Google Drive.

### Tareas de una reunión específica
```http
GET /api/integrations/meetings/{meetingId}/tasks
Authorization: Bearer {token}
```
Respuesta 200:
```json
{
  "meeting": {
    "id": 1234,
    "title": "Demo con cliente",
    "created_at": "2024-05-10T17:00:12Z"
  },
  "tasks": [
    {
      "id": 777,
      "title": "Enviar propuesta",
      "status": "pendiente",
      "progress": 0,
      "due_date": "2024-05-12",
      "due_time": "18:00"
    }
  ]
}
```

## Tareas asignadas al usuario
```http
GET /api/integrations/tasks
Authorization: Bearer {token}
```
Parámetros opcionales:
- `meeting_id`: filtra tareas de una reunión específica.

Respuesta 200:
```json
{
  "data": [
    {
      "id": 777,
      "title": "Enviar propuesta",
      "status": "en_progreso",
      "progress": 50,
      "starts_at": "2024-05-10",
      "due_date": "2024-05-12",
      "due_time": "18:00",
      "assigned_to": "María",
      "meeting": {
        "id": 1234,
        "title": "Demo con cliente",
        "date": "2024-05-10T17:00:12Z"
      }
    }
  ]
}
```

## Búsqueda de usuarios
```http
GET /api/integrations/users/search?query=mar
Authorization: Bearer {token}
```
Respuesta 200:
```json
{
  "data": [
    {
      "id": 55,
      "full_name": "María García",
      "email": "maria@empresa.com",
      "username": "maria",
      "role": "manager"
    }
  ]
}
```

## Errores comunes
- `401 Unauthorized`: token inválido o ausente.
- `404`: reunión o recurso no disponible para el usuario autenticado.
- `429`: demasiadas peticiones (aplica cuota de `throttle`).

Mantén los tokens seguros. Puedes revocarlos cerrando sesión o eliminándolos desde Juntify.
