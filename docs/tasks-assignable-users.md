# `/api/tasks-laravel/assignable-users`

Endpoint que devuelve las personas a las que se puede asignar una tarea.

## Parámetros de consulta

- `meeting_id` (opcional): filtra los usuarios que tienen reuniones compartidas con el propietario de la tarea.
- `query` (opcional, string, máx. 255 caracteres): cuando se incluye se realizan coincidencias parciales por nombre, correo o usuario dentro de la base de datos de usuarios de Juntify. Este parámetro agrega una fuente adicional (`source: "platform"`, `platform: "users"`) a la respuesta.

## Respuesta

La respuesta mantiene la estructura `{ success: true, users: Usuario[] }`. Cada usuario contiene los campos `id`, `name`, `email`, `source` y `platform` (cuando aplica).

Los resultados provenientes de la plataforma están limitados a 10 coincidencias por solicitud para evitar respuestas excesivamente grandes y mantener tiempos de respuesta consistentes.
