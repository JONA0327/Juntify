# ✅ Endpoints Panel DDU - Implementados

## Estado: OPERATIVOS ✓

Se han implementado exitosamente 3 nuevos endpoints en Juntify (puerto 8000) para integración con Panel DDU.

---

## 📍 Endpoints Disponibles

### 1️⃣ Obtener Lista de Usuarios
**GET** `/api/users/list`

**Parámetros opcionales:**
- `search` - Filtrar por username o email
- `exclude_empresa_id` - Excluir usuarios de empresa específica

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/list?search=juan&exclude_empresa_id=1' -Method GET
```

**Response:**
```json
{
  "success": true,
  "users": [
    {
      "id": "5b324294-6847-4e85-b9f6-1687a9922f75",
      "username": "Administrador_DDU",
      "email": "ddujuntify@gmail.com",
      "name": "Administrador_DDU"
    }
  ],
  "total": 1
}
```

---

### 2️⃣ Añadir Usuario a Empresa
**POST** `/api/users/add-to-company`

**Body:**
```json
{
  "user_id": "5b324294-6847-4e85-b9f6-1687a9922f75",
  "empresa_id": 1,
  "rol": "miembro"
}
```

**Roles permitidos:** `admin`, `miembro`, `administrador`

**Ejemplo:**
```powershell
$body = @{
    user_id = '5b324294-6847-4e85-b9f6-1687a9922f75'
    empresa_id = 1
    rol = 'miembro'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/add-to-company' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body
```

**Response (201):**
```json
{
  "success": true,
  "message": "Usuario añadido a la empresa exitosamente.",
  "integrante": {
    "id": 5,
    "user_id": "5b324294-6847-4e85-b9f6-1687a9922f75",
    "empresa_id": 1,
    "rol": "miembro",
    "user": {
      "username": "juan_perez",
      "email": "juan@example.com",
      "name": "juan_perez"
    },
    "empresa": {
      "id": 1,
      "nombre_empresa": "DDU"
    }
  }
}
```

**Errores:**
- `404` - Usuario o empresa no encontrados
- `409` - Usuario ya es integrante de la empresa

---

### 3️⃣ Listar Miembros de Empresa
**GET** `/api/companies/{empresa_id}/members`

**Parámetros opcionales:**
- `include_owner` - Incluir al dueño de la empresa (default: `true`)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/members' -Method GET
```

**Response (200):**
```json
{
  "success": true,
  "empresa": {
    "id": 3,
    "nombre": "DDU",
    "usuario_principal": "5b324294-6847-4e85-b9f6-1687a9922f75",
    "rol_empresa": "founder"
  },
  "members": [
    {
      "id": "5b324294-6847-4e85-b9f6-1687a9922f75",
      "username": "Administrador_DDU",
      "email": "ddujuntify@gmail.com",
      "name": "Administrador_DDU",
      "is_owner": true,
      "rol": "founder",
      "fecha_agregado": "2026-02-02 16:54:47"
    },
    {
      "id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
      "username": "Jona0327",
      "email": "jona03278@gmail.com",
      "name": "Jona0327",
      "is_owner": false,
      "rol": "miembro",
      "fecha_agregado": "2026-02-02 17:16:05"
    }
  ],
  "total": 2,
  "stats": {
    "total_members": 2,
    "admins": 1,
    "members": 1,
    "active": 2,
    "inactive": 0
  }
}
```

**Errores:**
- `404` - Empresa no encontrada

---

### 4️⃣ Actualizar Rol de Miembro
**PATCH** `/api/companies/{empresa_id}/members/{user_id}/role`

**Body:**
```json
{
  "rol": "admin"
}
```

**Roles permitidos:** `admin`, `miembro`, `administrador`

**Ejemplo:**
```powershell
$body = @{ rol = 'admin' } | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/members/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/role' `
    -Method PATCH `
    -ContentType 'application/json' `
    -Body $body
```

**Response (200):**
```json
{
  "success": true,
  "message": "Rol actualizado exitosamente",
  "data": {
    "empresa_id": 3,
    "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "username": "Jona0327",
    "email": "jona03278@gmail.com",
    "nuevo_rol": "admin"
  }
}
```

**Errores:**
- `404` - Empresa, usuario o integrante no encontrado
- `403` - No se puede cambiar el rol del dueño
- `422` - Datos de validación incorrectos

---

### 5️⃣ Eliminar Miembro de Empresa
**DELETE** `/api/companies/{empresa_id}/members/{user_id}`

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/members/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc' -Method DELETE
```

**Response (200):**
```json
{
  "success": true,
  "message": "Miembro eliminado exitosamente",
  "data": {
    "empresa_id": 3,
    "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "username": "Jona0327",
    "email": "jona03278@gmail.com"
  }
}
```

**Errores:**
- `404` - Empresa, usuario o integrante no encontrado
- `403` - No se puede eliminar al dueño de la empresa

---

### 6️⃣ Obtener Contactos de Usuario
**GET** `/api/users/{user_id}/contacts`

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/contacts'
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "username": "Jona0327",
    "email": "jona03278@gmail.com"
  },
  "contacts": [
    {
      "id": "5b324294-6847-4e85-b9f6-1687a9922f75",
      "username": "Administrador_DDU",
      "email": "ddujuntify@gmail.com",
      "name": "Administrador_DDU",
      "fecha_agregado": "2026-01-15T10:30:00.000000Z"
    }
  ],
  "total": 1
}
```

**Errores:**
- `404` - Usuario no encontrado

---

### 8️⃣ Obtener Reuniones del Usuario
**GET** `/api/users/{user_id}/meetings`

**Parámetros opcionales:**
- `limit` - Cantidad de reuniones (default: `100`, max: `500`)
- `offset` - Offset para paginación (default: `0`)
- `order_by` - Campo de orden: `created_at`, `meeting_name`, `id` (default: `created_at`)
- `order_dir` - Dirección: `asc` o `desc` (default: `desc`)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/meetings'

# Con paginación
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/meetings?limit=10&offset=0&order_by=meeting_name&order_dir=asc'
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "username": "Jona0327",
    "email": "jona03278@gmail.com"
  },
  "meetings": [
    {
      "id": 5,
      "meeting_name": "Reunión del 02/02/2026 12:13",
      "username": "Jona0327",
      "transcript_drive_id": "1N7G9nJRkfmL0yTFilp94NLVunl1LFBh4",
      "audio_drive_id": "1MXc4UFwImSyGYOyJsVVplcRaaCtRmWmO",
      "status": "completed",
      "duration_minutes": null,
      "created_at": "2026-02-02 12:14:30",
      "updated_at": "2026-02-02 12:14:30",
      "transcript": {
        "file_name": "Reunión_del_02_02_2026_12:13.ju",
        "file_size_bytes": 7056,
        "file_size_mb": 0.01,
        "file_content": "ZXlKcGRpSTZJbGwzWVVGVFpXOUpWMDVJVGxoVGVHNXR...",
        "encoding": "base64"
      },
      "audio": {
        "file_name": "Reunión_del_02_02_2026_12:13.mp3",
        "file_size_bytes": 805660,
        "file_size_mb": 0.77,
        "file_content": "SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjE...",
        "encoding": "base64"
      }
    }
  ],
  "pagination": {
    "total": 1,
    "limit": 100,
    "offset": 0,
    "has_more": false
  },
  "stats": {
    "total_meetings": 1,
    "this_week": 1,
    "this_month": 1,
    "total_duration_minutes": 0
  }
}
```

**Nota importante:** Los archivos (.ju y .mp3) se descargan automáticamente desde Google Drive y se incluyen en base64 en cada reunión. Juntify maneja el token de Google Drive de forma transparente.

**Errores:**
- `404` - Usuario no encontrado

---

### 9️⃣ Obtener Todas las Reuniones Accesibles del Usuario
**GET** `/api/users/{user_id}/meetings/all`

**Descripción:** Obtiene todas las reuniones a las que el usuario tiene acceso, incluyendo:
- **Reuniones propias** del usuario
- **Reuniones compartidas directamente** con él (vía shared_meetings)  
- **Reuniones compartidas en grupos** a través de contenedores donde es miembro

Este endpoint es ideal para el **asistente de IA**, ya que proporciona acceso a todo el contenido disponible para el usuario.

**Parámetros opcionales:**
- `limit` - Cantidad de reuniones (default: `100`, max: `500`)
- `offset` - Offset para paginación (default: `0`)
- `include_shared` - Incluir reuniones compartidas directamente (default: `true`)
- `include_groups` - Incluir reuniones de grupos (default: `true`)

**Ejemplo:**
```powershell
# Obtener todas las reuniones accesibles
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/meetings/all'

# Solo reuniones propias y compartidas, sin grupos
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/meetings/all?include_groups=false'

# Con paginación
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/meetings/all?limit=50&offset=0'
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "username": "Jona0327",
    "email": "jona03278@gmail.com"
  },
  "meetings": [
    {
      "id": 5,
      "meeting_name": "Reunión del 02/02/2026 12:13",
      "username": "Jona0327",
      "created_at": "2026-02-02 12:14:30",
      "updated_at": "2026-02-02 12:14:30",
      "transcript_drive_id": "1N7G9nJRkfmL0yTFilp94NLVunl1LFBh4",
      "audio_drive_id": "1MXc4UFwImSyGYOyJsVVplcRaaCtRmWmO",
      "source": "own",
      "shared_by": null,
      "access_type": "owner"
    },
    {
      "id": 12,
      "meeting_name": "Planificación Q1",
      "username": "juan_perez",
      "created_at": "2026-01-28 10:30:00",
      "updated_at": "2026-01-28 10:45:00",
      "transcript_drive_id": "1ABC...",
      "audio_drive_id": "1XYZ...",
      "source": "shared_direct",
      "shared_by": {
        "id": "5b324294-6847-4e85-b9f6-1687a9922f75",
        "name": "Juan Pérez",
        "email": "juan.perez@example.com"
      },
      "shared_at": "2026-01-29 08:00:00",
      "access_type": "shared"
    },
    {
      "id": 18,
      "meeting_name": "Reunión de equipo técnico",
      "username": "maria_garcia",
      "created_at": "2026-01-30 14:00:00",
      "updated_at": "2026-01-30 14:30:00",
      "transcript_drive_id": "1DEF...",
      "audio_drive_id": "1GHI...",
      "source": "group_container",
      "shared_by": {
        "id": 3,
        "name": "Equipo Desarrollo"
      },
      "container": {
        "id": 7,
        "name": "Reuniones Técnicas"
      },
      "access_type": "group"
    }
  ],
  "pagination": {
    "total": 3,
    "limit": 100,
    "offset": 0,
    "has_more": false
  },
  "stats": {
    "own_meetings": 1,
    "shared_meetings": 1,
    "group_meetings": 1,
    "total_accessible": 3
  }
}
```

**Campos de respuesta:**

Cada reunión incluye:
- `id`, `meeting_name`, `username` - Identificación básica
- `created_at`, `updated_at` - Fechas
- `transcript_drive_id`, `audio_drive_id` - IDs de archivos en Google Drive
- `source` - Origen de acceso:
  - `own` - Reunión propia del usuario
  - `shared_direct` - Compartida directamente con el usuario
  - `group_container` - Compartida en grupo a través de contenedor
- `shared_by` - Información de quien compartió (usuario o grupo)
- `access_type` - Tipo de acceso: `owner`, `shared`, `group`

Campos adicionales según `source`:
- **shared_direct**: `shared_at` (fecha de compartición)
- **group_container**: `container` (información del contenedor del grupo)

**Notas:**
- Las reuniones se ordenan por fecha de creación (más recientes primero)
- Se eliminan duplicados si una reunión está accesible por múltiples vías
- Este endpoint **NO descarga archivos** (a diferencia de `/api/users/{user_id}/meetings`)
- Ideal para listar reuniones disponibles para el asistente de IA

**Errores:**
- `404` - Usuario no encontrado

---

### 🔟 Obtener Grupos de Reuniones del Usuario
**GET** `/api/users/{user_id}/meeting-groups`

**Parámetros opcionales:**
- `include_members` - Incluir lista de miembros (default: `true`)
- `include_meetings_count` - Incluir conteo de reuniones (default: `true`)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/meeting-groups'

# Sin miembros
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc/meeting-groups?include_members=false'
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "username": "Jona0327"
  },
  "groups": [],
  "total": 0,
  "stats": {
    "total_groups": 0,
    "owned_groups": 0,
    "member_groups": 0
  }
}
```

**Errores:**
- `404` - Usuario no encontrado

---

### 🔟 Obtener Detalles de Reunión
**GET** `/api/meetings/{meeting_id}`

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/meetings/5'
```

**Response (200):**
```json
{
  "success": true,
  "meeting": {
    "id": 5,
    "meeting_name": "Reunión del 02/02/2026 12:13",
    "username": "Jona0327",
    "user": {
      "id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
      "username": "Jona0327",
      "email": "jona03278@gmail.com"
    },
    "transcript_drive_id": "1N7G9nJRkfmL0yTFilp94NLVunl1LFBh4",
    "audio_drive_id": "1MXc4UFwImSyGYOyJsVVplcRaaCtRmWmO",
    "transcript_download_url": "https://drive.google.com/...",
    "audio_download_url": "https://drive.google.com/...",
    "status": "completed",
    "duration_minutes": null,
    "created_at": "2026-02-02 12:14:30",
    "updated_at": "2026-02-02 12:14:30",
    "shared_with_groups": []
  }
}
```

**Errores:**
- `404` - Reunión no encontrada

---

### 1️⃣1️⃣ Detalles de Reunión (Completo)
**GET** `/api/meetings/{meeting_id}/details`

**Parámetros opcionales:**
- `user_id` - UUID del usuario para verificar permisos

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/meetings/8d445506-8069-6g07-d8h8-3809c1b44h97/details?user_id=5b324294-6847-4e85-b9f6-1687a9922f75' -Method GET
```

**Response:**
```json
{
  "success": true,
  "meeting": {
    "id": "meeting-uuid",
    "meeting_name": "Reunión de Planificación",
    "meeting_date": "2026-02-02T10:00:00Z",
    "duration": 45,
    "created_at": "2026-02-02T09:30:00Z",
    "updated_at": "2026-02-02T11:00:00Z",
    "user_id": "user-uuid",
    "group_id": "group-uuid",
    "organization_id": "org-uuid"
  },
  "container": {
    "id": 12,
    "name": "Proyecto Alpha",
    "description": "Contenedor para reuniones del proyecto",
    "folder_id": "1A2B3C4D5E6F7G8H9I0J"
  },
  "audio_file": {
    "filename": "meeting_uuid.ju",
    "file_path": "/path/to/file.ju",
    "file_size_bytes": 5242880,
    "file_size_mb": 5.0,
    "encrypted": true,
    "google_drive_file_id": "1Z2Y3X4W5V6U7T8S9R0Q",
    "download_url": "https://drive.google.com/file/d/1Z2Y3X4W5V6U7T8S9R0Q/view"
  },
  "transcription": {
    "id": 45,
    "transcription_text": "Texto completo de la transcripción...",
    "language": "es-MX",
    "confidence_score": 0.95,
    "created_at": "2026-02-02T10:50:00Z"
  },
  "tasks": [
    {
      "id": 101,
      "task_description": "Revisar propuesta de presupuesto",
      "assigned_to_user_id": "user-uuid",
      "assigned_to_username": "juan_perez",
      "status": "pending",
      "due_date": "2026-02-10",
      "priority": "high",
      "created_at": "2026-02-02T10:45:00Z"
    }
  ],
  "permissions": {
    "can_edit": true,
    "can_delete": true,
    "can_share": true,
    "is_owner": true
  }
}
```

**Errores:**
- `404` - Reunión no encontrada
- `403` - Sin permisos para acceder

---

### 1️⃣2️⃣ Descargar Archivo de Reunión
**GET** `/api/meetings/{meeting_id}/download/{file_type}`

**Descripción:** Descargar archivo de reunión (.ju transcripción o audio). Juntify maneja el token de Google Drive y descarga el archivo automáticamente.

**Path Parameters:**
- `meeting_id` (integer) - ID de la reunión
- `file_type` (string) - Tipo de archivo: `transcript`, `audio`, o `both`

**Query Parameters:**
- `username` (string, requerido) - Username del dueño de la reunión
- `format` (string, opcional) - Formato de respuesta: `base64`, `url`, `stream`. Default: `base64`
  - **`base64`** (DEFAULT): Descarga los archivos completos desde Google Drive y los envía codificados en base64
  - **`url`**: Solo retorna URLs de Google Drive, NO descarga los archivos
  - **`stream`**: Descarga y envía como stream binario (solo para archivos individuales, no soportado con `both`)

**Ejemplo base64 (descarga completa):**
```powershell
$response = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/meetings/5/download/transcript?username=Jona0327'

# Guardar archivo desde base64
$bytes = [Convert]::FromBase64String($response.file_content)
[IO.File]::WriteAllBytes("C:\Downloads\reunion.ju", $bytes)
```

**Response base64 (200):**
```json
{
  "success": true,
  "meeting_id": 5,
  "file_type": "transcript",
  "file_name": "Reunión_del_02_02_2026_12:13_5.ju",
  "file_size_bytes": 7056,
  "file_size_mb": 0.01,
  "mime_type": "application/octet-stream",
  "file_content": "ZXlKcGRpSTZJbGwzWVVGVFpXOUpWMDVJVGxoVGVHNXR...",
  "encoding": "base64",
  "downloaded_at": "2026-02-02T19:42:28-06:00"
}
```

**Ejemplo URL (solo retorna URL de Google Drive):**
```powershell
$response = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/meetings/5/download/audio?username=Jona0327&format=url'
Write-Output $response.download_url
```

**Response URL (200):**
```json
{
  "success": true,
  "meeting_id": 5,
  "file_type": "audio",
  "file_name": "Reunión_del_02_02_2026_12:13_5.mp3",
  "download_url": "https://drive.google.com/uc?export=download&id=1MXc4UFwImSyGYOyJsVVplcRaaCtRmWmO",
  "drive_id": "1MXc4UFwImSyGYOyJsVVplcRaaCtRmWmO",
  "note": "URL requiere acceso a Google Drive del usuario"
}
```

**Ejemplo stream (descarga directa):**
```powershell
Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/meetings/5/download/audio?username=Jona0327&format=stream' `
    -OutFile "C:\Downloads\audio.mp3"
```

**Ejemplo BOTH - Descargar ambos archivos (transcript + audio):**
```powershell
# Formato base64 (DEFAULT) - Juntify DESCARGA ambos archivos completos desde Google Drive
$response = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/meetings/5/download/both?username=Jona0327'

# Guardar transcript
$transcriptBytes = [Convert]::FromBase64String($response.transcript.file_content)
[IO.File]::WriteAllBytes("C:\Downloads\transcript.ju", $transcriptBytes)

# Guardar audio
$audioBytes = [Convert]::FromBase64String($response.audio.file_content)
[IO.File]::WriteAllBytes("C:\Downloads\audio.mp3", $audioBytes)

Write-Output "Tamaño total: $($response.total_size_mb) MB"
```

**Response BOTH base64 (200):**
```json
{
  "success": true,
  "meeting_id": 5,
  "file_type": "both",
  "transcript": {
    "file_name": "Reunión_del_02_02_2026_12:13_5.ju",
    "file_size_bytes": 7056,
    "file_content": "ZXlKcGRpSTZJbGwzWVVGVFpXOUpWMDVJVGxoVGVHNXR...",
    "encoding": "base64",
    "file_size_mb": 0.01
  },
  "audio": {
    "file_name": "Reunión_del_02_02_2026_12:13_5.mp3",
    "file_size_bytes": 815632,
    "file_content": "SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjE...",
    "encoding": "base64",
    "file_size_mb": 0.78
  },
  "total_size_mb": 0.79,
  "downloaded_at": "2026-02-02T19:48:23-06:00",
  "note": "Archivos descargados completamente desde Google Drive y codificados en base64"
}
```

**Ejemplo BOTH - Solo URLs (NO descarga archivos):**
```powershell
# Formato URL - Solo obtiene URLs, Juntify NO descarga los archivos
$response = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/meetings/5/download/both?username=Jona0327&format=url'
Write-Output "Transcript: $($response.transcript.download_url)"
Write-Output "Audio: $($response.audio.download_url)"
```

**Response BOTH url (200):**
```json
{
  "success": true,
  "meeting_id": 5,
  "file_type": "both",
  "transcript": {
    "file_name": "Reunión_del_02_02_2026_12:13_5.ju",
    "download_url": "https://drive.google.com/uc?export=download&id=1N7G9nJRkfmL0yTFilp94NLVunl1LFBh4",
    "drive_id": "1N7G9nJRkfmL0yTFilp94NLVunl1LFBh4"
  },
  "audio": {
    "file_name": "Reunión_del_02_02_2026_12:13_5.mp3",
    "download_url": "https://drive.google.com/uc?export=download&id=1MXc4UFwImSyGYOyJsVVplcRaaCtRmWmO",
    "drive_id": "1MXc4UFwImSyGYOyJsVVplcRaaCtRmWmO"
  },
  "note": "URLs requieren acceso a Google Drive del usuario"
}
```

**Características de Seguridad:**
- ✅ Juntify maneja el token de Google Drive automáticamente
- ✅ Refresca token expirado de forma transparente
- ✅ Verifica que el usuario sea dueño de la reunión
- ✅ Panel DDU nunca accede directamente a tokens de Google

**Errores:**
- `400` - Tipo de archivo inválido o username faltante
- `404` - Reunión no encontrada o no pertenece al usuario
- `404` - Token de Google no encontrado (usuario debe conectar Drive)
- `401` - Error al refrescar token (reconectar Google Drive)
- `500` - Error al descargar desde Google Drive

---

## 📁 Archivos Creados

### Controladores:
- `app/Http/Controllers/Api/UserApiController.php`
- `app/Http/Controllers/Api/MeetingDetailsController.php`
- `app/Http/Controllers/Api/CompanyMembersController.php`
- `app/Http/Controllers/Api/UserMeetingsController.php`
- `app/Http/Controllers/Api/MeetingDownloadController.php`

### Rutas:
- `routes/api.php` (líneas 53-73)

---

## 🧪 Testing Completo

### Test 1: Listar usuarios
```powershell
# Todos los usuarios
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/list'

# Con búsqueda
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/list?search=admin'

# Excluyendo empresa
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/list?exclude_empresa_id=1'
```

### Test 2: Añadir usuario a empresa
```powershell
$addUser = @{
    user_id = '5b324294-6847-4e85-b9f6-1687a9922f75'
    empresa_id = 1
    rol = 'miembro'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/add-to-company' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $addUser
```

### Test 3: Listar miembros de empresa
```powershell
# Obtener todos los miembros de DDU (empresa_id = 3)
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/members'

# Sin incluir al dueño
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/members?include_owner=false'
```

### Test 4: Actualizar rol de miembro
```powershell
$updateRole = @{ rol = 'admin' } | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/members/USER_ID_AQUI/role' `
    -Method PATCH `
    -ContentType 'application/json' `
    -Body $updateRole
```

### Test 5: Eliminar miembro
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/members/USER_ID_AQUI' -Method DELETE
```

### Test 6: Obtener contactos de usuario
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/USER_ID_AQUI/contacts'
```

### Test 7: Obtener reuniones del usuario
```powershell
$userId = "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc"

# Todas las reuniones
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/users/$userId/meetings"

# Con paginación
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/users/$userId/meetings?limit=10&offset=0"
```

### Test 8: Obtener grupos del usuario
```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/users/$userId/meeting-groups"
```

### Test 9: Obtener detalles de reunión
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/meetings/5'
```

### Test 10: Detalles de reunión (completo)
```powershell
# Reemplazar MEETING_ID con un UUID real de reunión
$meetingId = "TU_MEETING_ID_AQUI"
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/meetings/$meetingId/details"
```

### Test 11: Descargar transcripción (.ju)
```powershell
$meetingId = 5
$username = "Jona0327"

# Formato base64 (descarga completa)
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/meetings/$meetingId/download/transcript?username=$username"
$bytes = [Convert]::FromBase64String($response.file_content)
[IO.File]::WriteAllBytes("C:\Downloads\reunion.ju", $bytes)

# Formato URL (solo obtener URL)
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/meetings/$meetingId/download/transcript?username=$username&format=url"
Write-Output $response.download_url
```

### Test 12: Descargar audio
```powershell
# Formato stream (descarga directa como archivo)
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/meetings/5/download/audio?username=Jona0327&format=stream" `
    -OutFile "C:\Downloads\audio.mp3"
```

### Test 13: Descargar AMBOS archivos (transcript + audio)
```powershell
$meetingId = 5
$username = "Jona0327"

# Descargar ambos archivos en base64
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/meetings/$meetingId/download/both?username=$username"

# Guardar transcript
$transcriptBytes = [Convert]::FromBase64String($response.transcript.file_content)
[IO.File]::WriteAllBytes("C:\Downloads\transcript.ju", $transcriptBytes)

# Guardar audio
$audioBytes = [Convert]::FromBase64String($response.audio.file_content)
[IO.File]::WriteAllBytes("C:\Downloads\audio.mp3", $audioBytes)

Write-Output "Archivos descargados - Tamaño total: $($response.total_size_mb) MB"

# Solo obtener URLs de ambos
$urlResponse = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/meetings/$meetingId/download/both?username=$username&format=url"
Write-Output "Transcript URL: $($urlResponse.transcript.download_url)"
Write-Output "Audio URL: $($urlResponse.audio.download_url)"
```

### **Test 14: Obtener lista de reuniones con archivos incluidos**

```powershell
# Obtener lista de reuniones (archivos descargados automáticamente)
$userId = "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc"
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/users/$userId/meetings?limit=1" -Method Get

# Ver resumen
Write-Host "Total reuniones: $($response.meetings.Count)"
$meeting = $response.meetings[0]
Write-Host "Reunión: $($meeting.meeting_name)"
Write-Host "Tiene transcript: $($null -ne $meeting.transcript.file_content)"
Write-Host "Tiene audio: $($null -ne $meeting.audio.file_content)"
Write-Host "Transcript size: $($meeting.transcript.file_size_mb) MB"
Write-Host "Audio size: $($meeting.audio.file_size_mb) MB"

# Guardar archivos desde base64
$transcriptBytes = [Convert]::FromBase64String($meeting.transcript.file_content)
[IO.File]::WriteAllBytes("C:\temp\$($meeting.transcript.file_name)", $transcriptBytes)

$audioBytes = [Convert]::FromBase64String($meeting.audio.file_content)
[IO.File]::WriteAllBytes("C:\temp\$($meeting.audio.file_name)", $audioBytes)

Write-Host "Archivos guardados en C:\temp\"
```

**Output esperado:**
```
Total reuniones: 1
Reunión: Reunión del 02/02/2026 12:13
Tiene transcript: True
Tiene audio: True
Transcript size: 0.01 MB
Audio size: 0.77 MB
Archivos guardados en C:\temp\
```

---

## 🏷️ Endpoint 13: Tipo de Reunión (Etiqueta)

Obtiene el tipo de una reunión para mostrar una etiqueta en Panel DDU.

### **Tipos disponibles:**
| Tipo | Label | Color | Descripción |
|------|-------|-------|-------------|
| `personal` | Personal | `#8B5CF6` (violet) | Reunión del usuario, sin compartir |
| `organizational` | Organizacional | `#3B82F6` (blue) | Reunión en contenedor de organización/grupo |
| `shared` | Compartida | `#10B981` (green) | Reunión compartida con otros usuarios |

### **GET /api/meetings/{meeting_id}/type**

Obtiene el tipo de una reunión específica.

**URL:** `http://127.0.0.1:8000/api/meetings/{meeting_id}/type`

**Response (200) - Personal:**
```json
{
  "success": true,
  "meeting_id": 5,
  "meeting_name": "Reunión del 02/02/2026 12:13",
  "owner_username": "Jona0327",
  "type": "personal",
  "type_label": "Personal",
  "type_color": "#8B5CF6",
  "details": {
    "container_id": 1,
    "container_name": "Contenedor de prueba",
    "is_in_container": true
  }
}
```

**Response (200) - Organizacional:**
```json
{
  "success": true,
  "meeting_id": 10,
  "meeting_name": "Reunión de equipo",
  "owner_username": "admin",
  "type": "organizational",
  "type_label": "Organizacional",
  "type_color": "#3B82F6",
  "details": {
    "container_id": 5,
    "container_name": "Contenedor Marketing",
    "group_id": 2,
    "group_name": "Equipo Marketing",
    "organization_id": 1,
    "organization_name": "Mi Empresa"
  }
}
```

**Response (200) - Compartida:**
```json
{
  "success": true,
  "meeting_id": 15,
  "meeting_name": "Reunión compartida",
  "owner_username": "user1",
  "type": "shared",
  "type_label": "Compartida",
  "type_color": "#10B981",
  "details": {
    "shared_by": {
      "username": "user1",
      "name": "Usuario Uno"
    },
    "shared_with": {
      "username": "user2",
      "name": "Usuario Dos"
    },
    "shared_at": "2026-02-01 14:30:00"
  }
}
```

### **POST /api/meetings/types**

Obtiene tipos de múltiples reuniones en una sola petición (batch).

**URL:** `http://127.0.0.1:8000/api/meetings/types`

**Body:**
```json
{
  "meeting_ids": [1, 5, 10, 15]
}
```

**Response (200):**
```json
{
  "success": true,
  "meetings": {
    "1": {
      "success": false,
      "message": "Reunión no encontrada"
    },
    "5": {
      "success": true,
      "meeting_name": "Reunión del 02/02/2026 12:13",
      "owner_username": "Jona0327",
      "type": "personal",
      "type_label": "Personal",
      "type_color": "#8B5CF6",
      "details": {
        "container_id": 1,
        "container_name": "Contenedor de prueba",
        "is_in_container": true
      }
    }
  }
}
```

### **Test 15: Obtener tipo de reunión**
```powershell
# Obtener tipo de una reunión
$meetingId = 5
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/meetings/$meetingId/type" -Method Get

Write-Host "Tipo: $($response.type_label)"
Write-Host "Color: $($response.type_color)"

# Ejemplo de uso en HTML para etiqueta
$labelHtml = "<span style='background-color: $($response.type_color); color: white; padding: 2px 8px; border-radius: 4px;'>$($response.type_label)</span>"
Write-Host "HTML: $labelHtml"
```

### **Test 16: Obtener tipos de múltiples reuniones (batch)**
```powershell
$body = @{ meeting_ids = @(1, 5, 10) } | ConvertTo-Json
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/meetings/types" -Method Post -Body $body -ContentType "application/json"

foreach ($key in $response.meetings.PSObject.Properties.Name) {
    $meeting = $response.meetings.$key
    if ($meeting.success) {
        Write-Host "Reunión $key : $($meeting.type_label) - $($meeting.meeting_name)"
    } else {
        Write-Host "Reunión $key : $($meeting.message)"
    }
}
```

---

## ⚙️ Configuración

### Rate Limiting
Todos los endpoints tienen límite de **60 peticiones por minuto** (middleware throttle).

### CORS
Si Panel DDU está en dominio diferente, configurar en `config/cors.php`:
```php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:3000', 'https://panel-ddu.com'],
```

---

## � Sistema de Grupos en Empresas

Sistema completo para gestionar grupos dentro de empresas con roles, miembros y compartir reuniones con autorización delegada de tokens.

### Tablas de Base de Datos (Juntify_Panels)
- **grupos_empresa** - Grupos dentro de empresas
- **miembros_grupo_empresa** - Miembros con roles (administrador/colaborador/invitado)
- **reuniones_compartidas_grupo** - Reuniones compartidas con permisos

---

### 🔹 Grupos - CRUD

#### Listar Grupos de una Empresa
**GET** `/api/companies/{empresa_id}/groups`

**Parámetros opcionales:**
- `only_active` - Solo grupos activos (default: true)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/groups' -Method GET
```

**Response (200):**
```json
{
  "groups": [
    {
      "id": 1,
      "nombre": "Equipo Desarrollo",
      "descripcion": "Grupo para compartir reuniones de desarrollo",
      "empresa_id": 3,
      "created_by": "Jona0327",
      "created_at": "2026-02-02T20:30:15-06:00",
      "miembros": [
        {
          "id": 1,
          "user_id": "uuid-usuario",
          "nombre": "Jona0327",
          "rol": "administrador"
        }
      ],
      "miembros_count": 2,
      "reuniones_compartidas": [
        {
          "id": 1,
          "meeting_id": 5,
          "nombre": "Reunión semanal",
          "compartido_por": "Jona0327"
        }
      ]
    }
  ],
  "total": 1
}
```

---

#### Crear Grupo
**POST** `/api/companies/{empresa_id}/groups`

**Body:**
```json
{
  "nombre": "Equipo Desarrollo",
  "descripcion": "Grupo para compartir reuniones de desarrollo",
  "created_by": "UUID-del-usuario"
}
```

**Ejemplo:**
```powershell
$body = @{
    nombre = 'Equipo Desarrollo'
    descripcion = 'Grupo para compartir reuniones de desarrollo'
    created_by = '5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/groups' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body
```

**Response (201):**
```json
{
  "message": "Grupo creado exitosamente",
  "group": {
    "id": 1,
    "nombre": "Equipo Desarrollo",
    "descripcion": "Grupo para compartir reuniones de desarrollo",
    "empresa_id": 3,
    "created_by": "Jona0327",
    "created_at": "2026-02-02T20:30:15-06:00"
  }
}
```

**Nota:** El creador se añade automáticamente como administrador del grupo.

---

#### Ver Grupo
**GET** `/api/companies/{empresa_id}/groups/{grupo_id}`

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/groups/1' -Method GET
```

**Response (200):**
```json
{
  "group": {
    "id": 1,
    "nombre": "Equipo Desarrollo",
    "descripcion": "Grupo para compartir reuniones",
    "created_by": "Jona0327",
    "is_active": true,
    "miembros": [
      {
        "id": 1,
        "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
        "username": "Jona0327",
        "email": "jona03278@gmail.com",
        "rol": "administrador"
      }
    ],
    "reuniones_compartidas": [
      {
        "meeting_id": 5,
        "meeting_name": "Reunión del 02/02/2026 12:13",
        "shared_by": "Jona0327",
        "permisos": {
          "ver_audio": true,
          "ver_transcript": true,
          "descargar": true
        }
      }
    ]
  }
}
```

---

#### Actualizar Grupo
**PUT** `/api/companies/{empresa_id}/groups/{grupo_id}`

**Body:**
```json
{
  "nombre": "Nuevo Nombre",
  "descripcion": "Nueva descripción",
  "is_active": true
}
```

**Response (200):**
```json
{
  "message": "Grupo actualizado exitosamente"
}
```

---

#### Eliminar Grupo
**DELETE** `/api/companies/{empresa_id}/groups/{grupo_id}`

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/companies/3/groups/1' -Method DELETE
```

**Response (200):**
```json
{
  "message": "Grupo eliminado exitosamente"
}
```

---

### 🔹 Miembros de Grupo

#### Añadir Miembro a Grupo
**POST** `/api/groups/{grupo_id}/members`

**Body:**
```json
{
  "user_id": "5b324294-6847-4e85-b9f6-1687a9922f75",
  "rol": "colaborador"
}
```

**Roles permitidos:** `administrador`, `colaborador`, `invitado` (default: colaborador)

**Ejemplo:**
```powershell
$body = @{
    user_id = '5b324294-6847-4e85-b9f6-1687a9922f75'
    rol = 'colaborador'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/groups/1/members' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body
```

**Response (201):**
```json
{
  "message": "Miembro añadido exitosamente",
  "member": {
    "id": 2,
    "user_id": "5b324294-6847-4e85-b9f6-1687a9922f75",
    "nombre": "Administrador_DDU",
    "rol": "colaborador"
  }
}
```

---

#### Actualizar Rol de Miembro
**PUT** `/api/groups/{grupo_id}/members/{member_id}`

**Body:**
```json
{
  "rol": "administrador"
}
```

**Response (200):**
```json
{
  "message": "Rol actualizado exitosamente"
}
```

---

#### Eliminar Miembro de Grupo
**DELETE** `/api/groups/{grupo_id}/members/{member_id}`

**Response (200):**
```json
{
  "message": "Miembro eliminado exitosamente"
}
```

---

### 🔹 Compartir Reuniones

#### Compartir Reunión con Grupo
**POST** `/api/groups/{grupo_id}/share-meeting`

**Body:**
```json
{
  "meeting_id": 5,
  "shared_by": "Jona0327",
  "permisos": {
    "ver_audio": true,
    "ver_transcript": true,
    "descargar": true
  },
  "mensaje": "Les comparto esta reunión importante",
  "expires_at": "2026-03-01 00:00:00"
}
```

**Permisos disponibles:**
- `ver_audio` - Permite escuchar el audio
- `ver_transcript` - Permite ver la transcripción (.ju)
- `descargar` - Permite descargar los archivos

**Ejemplo:**
```powershell
$body = @{
    meeting_id = 5
    shared_by = 'Jona0327'
    permisos = @{
        ver_audio = $true
        ver_transcript = $true
        descargar = $true
    }
    mensaje = 'Reunión importante del proyecto'
} | ConvertTo-Json -Depth 3

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/groups/1/share-meeting' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body
```

**Response (201):**
```json
{
  "message": "Reunión compartida exitosamente",
  "shared_meeting": {
    "id": 1,
    "meeting_id": 5,
    "grupo_id": 1,
    "shared_by": "Jona0327",
    "permisos": {
      "ver_audio": true,
      "ver_transcript": true,
      "descargar": true
    },
    "created_at": "2026-02-02T22:24:59-06:00"
  }
}
```

---

#### Listar Reuniones Compartidas del Grupo
**GET** `/api/groups/{grupo_id}/shared-meetings`

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/groups/1/shared-meetings' -Method GET
```

**Response (200):**
```json
{
  "shared_meetings": [
    {
      "id": 1,
      "meeting_id": 5,
      "meeting_name": "Reunión del 02/02/2026 12:13",
      "shared_by": "Jona0327",
      "permisos": {
        "ver_audio": true,
        "ver_transcript": true,
        "descargar": true
      },
      "created_at": "2026-02-02T22:24:59-06:00"
    }
  ]
}
```

---

#### Dejar de Compartir Reunión
**DELETE** `/api/groups/{grupo_id}/shared-meetings/{meeting_id}`

**Response (200):**
```json
{
  "message": "Se dejó de compartir la reunión"
}
```

---

### 🔹 Descargar Archivos de Reunión Compartida ⭐

**GET** `/api/companies/{empresa_id}/groups/{grupo_id}/shared-meetings/{meeting_id}/files`

Este endpoint permite a los miembros del grupo descargar archivos de reuniones compartidas **usando el token de Google Drive del usuario que compartió** (autorización delegada).

**Parámetros:**
- `requester_user_id` (requerido) - ID del usuario que solicita
- `file_type` - Tipo de archivo: `transcript`, `audio`, `both` (default: `both`)

**Ejemplo:**
```powershell
$uri = 'http://127.0.0.1:8000/api/companies/3/groups/1/shared-meetings/5/files'
$params = '?requester_user_id=5b324294-6847-4e85-b9f6-1687a9922f75&file_type=both'

$response = Invoke-RestMethod -Uri ($uri + $params) -Method GET
```

**Response (200):**
```json
{
  "meeting_id": 5,
  "meeting_name": "Reunión del 02/02/2026 12:13",
  "shared_by": "Jona0327",
  "permisos": {
    "ver_audio": true,
    "ver_transcript": true,
    "descargar": true
  },
  "can_download": true,
  "transcript": {
    "file_name": "Reunión del 02_02_2026 12_13.ju",
    "file_size_bytes": 7056,
    "file_size_mb": 0.01,
    "file_content": "base64_encoded_content...",
    "encoding": "base64"
  },
  "audio": {
    "file_name": "Reunión del 02_02_2026 12_13.mp3",
    "file_size_bytes": 807936,
    "file_size_mb": 0.77,
    "file_content": "base64_encoded_content...",
    "encoding": "base64"
  },
  "total_size_mb": 0.78,
  "downloaded_at": "2026-02-02T21:30:00+00:00"
}
```

**Errores:**
- `400` - Falta requester_user_id
- `403` - Usuario no es miembro del grupo
- `403` - No tiene permiso para ver audio/transcript
- `404` - Grupo, reunión o reunión compartida no encontrada

**Nota importante:** Este endpoint usa **autorización delegada**. Cuando un usuario comparte una reunión con el grupo, su token de Google Drive se utiliza para que todos los miembros puedan acceder a los archivos, incluso si no tienen su propia conexión a Google Drive.

---

### 🔹 Grupos del Usuario

#### Obtener Grupos a los que Pertenece un Usuario
**GET** `/api/users/{user_id}/company-groups`

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users/5b324294-6847-4e85-b9f6-1687a9922f75/company-groups' -Method GET
```

**Response (200):**
```json
{
  "user_id": "5b324294-6847-4e85-b9f6-1687a9922f75",
  "username": "Administrador_DDU",
  "groups": [
    {
      "id": 1,
      "nombre": "Equipo Desarrollo",
      "empresa_id": 3,
      "empresa_nombre": "DDU",
      "rol_en_grupo": "colaborador",
      "total_miembros": 2,
      "reuniones_compartidas": 1
    }
  ],
  "total": 1
}
```

---

## 📊 Resumen de Integración

| Endpoint | Método | Propósito |
|----------|--------|-----------|
| `/api/users/list` | GET | Obtener usuarios disponibles |
| `/api/users/add-to-company` | POST | Registrar integrante en empresa |
| `/api/users/{user_id}/contacts` | GET | Obtener contactos de usuario |
| `/api/users/{user_id}/meetings` | GET | Obtener reuniones del usuario |
| `/api/users/{user_id}/meeting-groups` | GET | Obtener grupos de reuniones |
| `/api/users/{user_id}/company-groups` | GET | Obtener grupos de empresa del usuario 🆕 |
| `/api/companies/{empresa_id}/members` | GET | Listar miembros de una empresa |
| `/api/companies/{empresa_id}/members/{user_id}/role` | PATCH | Actualizar rol de miembro |
| `/api/companies/{empresa_id}/members/{user_id}` | DELETE | Eliminar miembro de empresa |
| `/api/companies/{empresa_id}/groups` | GET | Listar grupos de empresa 🆕 |
| `/api/companies/{empresa_id}/groups` | POST | Crear grupo 🆕 |
| `/api/companies/{empresa_id}/groups/{id}` | GET | Ver grupo 🆕 |
| `/api/companies/{empresa_id}/groups/{id}` | PUT | Actualizar grupo 🆕 |
| `/api/companies/{empresa_id}/groups/{id}` | DELETE | Eliminar grupo 🆕 |
| `/api/groups/{grupo_id}/members` | POST | Añadir miembro a grupo 🆕 |
| `/api/groups/{grupo_id}/members/{id}` | PUT | Actualizar rol de miembro 🆕 |
| `/api/groups/{grupo_id}/members/{id}` | DELETE | Eliminar miembro de grupo 🆕 |
| `/api/groups/{grupo_id}/share-meeting` | POST | Compartir reunión con grupo 🆕 |
| `/api/groups/{grupo_id}/shared-meetings` | GET | Listar reuniones compartidas 🆕 |
| `/api/groups/{grupo_id}/shared-meetings/{id}` | DELETE | Dejar de compartir reunión 🆕 |
| `/api/companies/{id}/groups/{g}/shared-meetings/{m}/files` | GET | Descargar archivos compartidos 🆕 |
| `/api/meetings/{meeting_id}` | GET | Obtener detalles de reunión |
| `/api/meetings/{meeting_id}/details` | GET | Detalles completos de reunión |
| `/api/meetings/{meeting_id}/download/{file_type}` | GET | Descargar archivo (.ju o audio) |
| `/api/meetings/{meeting_id}/type` | GET | Obtener tipo de reunión (etiqueta) |
| `/api/meetings/types` | POST | Obtener tipos de múltiples reuniones |
| `/api/auth/validate-user` | POST | Validar credenciales y empresa |
| `/api/auth/check-company-membership` | POST | Verificar pertenencia a empresa |
| `/api/ddu/assistant-settings/{userId}` | GET | Obtener configuración del asistente 🆕 |
| `/api/ddu/assistant-settings` | POST | Crear/actualizar configuración 🆕 |
| `/api/ddu/assistant-settings/{userId}/api-key` | GET | Obtener API key (uso interno) 🆕 |
| `/api/ddu/assistant-settings/{userId}/api-key` | DELETE | Eliminar API key 🆕 |
| `/api/ddu/assistant/conversations` | GET | Listar conversaciones 🆕 |
| `/api/ddu/assistant/conversations` | POST | Crear conversación 🆕 |
| `/api/ddu/assistant/conversations/{id}` | GET | Obtener conversación con mensajes 🆕 |
| `/api/ddu/assistant/conversations/{id}` | PUT | Actualizar conversación 🆕 |
| `/api/ddu/assistant/conversations/{id}` | DELETE | Eliminar conversación 🆕 |
| `/api/ddu/assistant/conversations/{id}/messages` | GET | Obtener mensajes 🆕 |
| `/api/ddu/assistant/conversations/{id}/messages` | POST | Agregar mensaje 🆕 |
| `/api/ddu/assistant/conversations/{id}/documents` | GET | Obtener documentos 🆕 |
| `/api/ddu/assistant/conversations/{id}/documents` | POST | Subir documento 🆕 |
| `/api/ddu/assistant/conversations/{id}/documents/{docId}` | DELETE | Eliminar documento 🆕 |

---

## 🤖 Configuración del Asistente DDU

Sistema para almacenar configuraciones del asistente (API keys, preferencias) de forma centralizada en Juntify.

### 1️⃣4️⃣ Obtener Configuración del Asistente
**GET** `/api/ddu/assistant-settings/{user_id}`

**Descripción:** Obtiene la configuración del asistente para un usuario específico. No devuelve la API key en texto plano, solo indica si está configurada.

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant-settings/550e8400-e29b-41d4-a716-446655440000' -Method GET
```

**Response Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "openai_api_key_configured": true,
        "enable_drive_calendar": true,
        "created_at": "2026-01-15T10:30:00Z",
        "updated_at": "2026-02-01T14:20:00Z"
    }
}
```

**Response No Encontrado (200):**
```json
{
    "success": true,
    "data": null
}
```

---

### 1️⃣5️⃣ Crear/Actualizar Configuración del Asistente
**POST** `/api/ddu/assistant-settings`

**Descripción:** Crea o actualiza la configuración del asistente para un usuario. La API key se encripta automáticamente antes de guardar.

**Body:**
```json
{
    "user_id": "550e8400-e29b-41d4-a716-446655440000",
    "openai_api_key": "sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "enable_drive_calendar": true
}
```

**Campos:**
| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `user_id` | string (UUID) | Sí | ID del usuario de Juntify |
| `openai_api_key` | string/null | No | API Key de OpenAI (se encripta automáticamente) |
| `enable_drive_calendar` | boolean | No | Habilitar integración con Drive/Calendar (default: true) |

**Ejemplo:**
```powershell
$body = @{
    user_id = '550e8400-e29b-41d4-a716-446655440000'
    openai_api_key = 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
    enable_drive_calendar = $true
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant-settings' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body
```

**Response Exitosa (200/201):**
```json
{
    "success": true,
    "message": "Configuración guardada correctamente",
    "data": {
        "id": 1,
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "openai_api_key_configured": true,
        "enable_drive_calendar": true,
        "updated_at": "2026-02-03T10:00:00Z"
    }
}
```

**Response Error (422):**
```json
{
    "success": false,
    "message": "Datos inválidos",
    "errors": {
        "user_id": ["El usuario no existe"]
    }
}
```

---

### 1️⃣6️⃣ Obtener API Key del Asistente (Uso Interno)
**GET** `/api/ddu/assistant-settings/{user_id}/api-key`

**Descripción:** Obtiene la API key desencriptada para uso del asistente. Este endpoint es para uso interno de Panel DDU cuando necesita hacer llamadas a OpenAI.

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant-settings/550e8400-e29b-41d4-a716-446655440000/api-key' -Method GET
```

**Response Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "openai_api_key": "sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
    }
}
```

**Response No Configurada (404):**
```json
{
    "success": false,
    "message": "API key no configurada para este usuario"
}
```

---

### 1️⃣7️⃣ Eliminar API Key
**DELETE** `/api/ddu/assistant-settings/{user_id}/api-key`

**Descripción:** Elimina la API key configurada para un usuario.

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant-settings/550e8400-e29b-41d4-a716-446655440000/api-key' -Method DELETE
```

**Response Exitosa (200):**
```json
{
    "success": true,
    "message": "API key eliminada correctamente"
}
```

---

### 🧪 Tests de Configuración del Asistente

```powershell
$userId = "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc"

# Test 1: Obtener configuración (antes de crearla)
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant-settings/$userId" -Method GET

# Test 2: Crear configuración con API key
$body = @{
    user_id = $userId
    openai_api_key = 'sk-test-key-12345'
    enable_drive_calendar = $true
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant-settings' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body

# Test 3: Obtener configuración (después de crearla)
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant-settings/$userId" -Method GET

# Test 4: Obtener API key desencriptada
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant-settings/$userId/api-key" -Method GET

# Test 5: Eliminar API key
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant-settings/$userId/api-key" -Method DELETE

# Test 6: Verificar que ya no tiene API key
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant-settings/$userId/api-key" -Method GET
```

---

## 💬 Asistente DDU - Conversaciones, Mensajes y Documentos

Sistema completo para gestionar conversaciones del asistente con historial de mensajes y documentos adjuntos.

### Tablas en `juntify_panels`:
- **ddu_assistant_conversations** - Conversaciones del asistente
- **ddu_assistant_messages** - Mensajes (system, user, assistant, tool)
- **ddu_assistant_documents** - Documentos adjuntos a conversaciones

---

### 1️⃣8️⃣ Listar Conversaciones del Usuario
**GET** `/api/ddu/assistant/conversations`

**Query params:**
- `user_id` (required) - UUID del usuario

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations?user_id=5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc' -Method GET
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
            "title": "Consulta sobre reunión",
            "description": null,
            "messages_count": 5,
            "created_at": "2026-02-03T10:00:00Z",
            "updated_at": "2026-02-03T10:30:00Z"
        }
    ],
    "total": 1
}
```

---

### 1️⃣9️⃣ Crear Conversación
**POST** `/api/ddu/assistant/conversations`

**Body:**
```json
{
    "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "title": "Nueva conversación"
}
```

**Ejemplo:**
```powershell
$body = @{
    user_id = '5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc'
    title = 'Consulta sobre tareas'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
        "title": "Consulta sobre tareas",
        "description": null,
        "messages_count": 0,
        "created_at": "2026-02-03T11:00:00Z"
    }
}
```

---

### 2️⃣0️⃣ Obtener Conversación con Mensajes
**GET** `/api/ddu/assistant/conversations/{id}`

**Query params:**
- `user_id` (required) - UUID del usuario (para verificar propiedad)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1?user_id=5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc' -Method GET
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
        "title": "Consulta sobre reunión",
        "description": null,
        "messages": [
            {
                "id": 1,
                "role": "system",
                "content": "Eres el asistente de DDU...",
                "metadata": null,
                "created_at": "2026-02-03T10:00:00Z"
            },
            {
                "id": 2,
                "role": "user",
                "content": "¿Qué tareas tengo pendientes?",
                "metadata": null,
                "created_at": "2026-02-03T10:01:00Z"
            },
            {
                "id": 3,
                "role": "assistant",
                "content": "Según tu última reunión...",
                "metadata": null,
                "created_at": "2026-02-03T10:01:05Z"
            }
        ],
        "documents": [],
        "messages_count": 3,
        "created_at": "2026-02-03T10:00:00Z",
        "updated_at": "2026-02-03T10:01:05Z"
    }
}
```

---

### 2️⃣1️⃣ Actualizar Conversación
**PUT** `/api/ddu/assistant/conversations/{id}`

**Body:**
```json
{
    "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "title": "Nuevo título"
}
```

**Ejemplo:**
```powershell
$body = @{
    user_id = '5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc'
    title = 'Consulta sobre proyecto Alpha'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1' `
    -Method PUT `
    -ContentType 'application/json' `
    -Body $body
```

**Response (200):**
```json
{
    "success": true,
    "message": "Conversación actualizada",
    "data": {
        "id": 1,
        "title": "Consulta sobre proyecto Alpha",
        "description": null,
        "updated_at": "2026-02-03T12:00:00Z"
    }
}
```

---

### 2️⃣2️⃣ Eliminar Conversación
**DELETE** `/api/ddu/assistant/conversations/{id}`

**Query params:**
- `user_id` (required) - UUID del usuario

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1?user_id=5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc' -Method DELETE
```

**Response (200):**
```json
{
    "success": true,
    "message": "Conversación eliminada correctamente"
}
```

---

### 2️⃣3️⃣ Obtener Mensajes de Conversación
**GET** `/api/ddu/assistant/conversations/{id}/messages`

**Query params:**
- `user_id` (required)
- `limit` (optional, default: 100)
- `offset` (optional, default: 0)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1/messages?user_id=5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc&limit=50' -Method GET
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "role": "system",
            "content": "Eres el asistente de DDU...",
            "metadata": null,
            "created_at": "2026-02-03T10:00:00Z"
        },
        {
            "id": 2,
            "role": "user",
            "content": "¿Qué tareas tengo pendientes?",
            "metadata": null,
            "created_at": "2026-02-03T10:01:00Z"
        }
    ],
    "pagination": {
        "total": 10,
        "limit": 50,
        "offset": 0
    }
}
```

---

### 2️⃣4️⃣ Agregar Mensaje a Conversación
**POST** `/api/ddu/assistant/conversations/{id}/messages`

**Body:**
```json
{
    "user_id": "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc",
    "role": "user",
    "content": "¿Qué tareas tengo pendientes?",
    "metadata": null
}
```

**Roles permitidos:** `system`, `user`, `assistant`, `tool`

**Ejemplo:**
```powershell
$body = @{
    user_id = '5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc'
    role = 'user'
    content = '¿Puedes resumir mi última reunión?'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1/messages' `
    -Method POST `
    -ContentType 'application/json' `
    -Body $body
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": 5,
        "assistant_conversation_id": 1,
        "role": "user",
        "content": "¿Puedes resumir mi última reunión?",
        "metadata": null,
        "created_at": "2026-02-03T10:05:00Z"
    }
}
```

---

### 2️⃣5️⃣ Obtener Documentos de Conversación
**GET** `/api/ddu/assistant/conversations/{id}/documents`

**Query params:**
- `user_id` (required)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1/documents?user_id=5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc' -Method GET
```

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "original_name": "documento.pdf",
            "mime_type": "application/pdf",
            "size": 102400,
            "summary": "Resumen del documento...",
            "created_at": "2026-02-03T10:10:00Z"
        }
    ]
}
```

---

### 2️⃣6️⃣ Subir Documento a Conversación
**POST** `/api/ddu/assistant/conversations/{id}/documents`

**Body (multipart/form-data):**
- `user_id` - UUID del usuario
- `file` - Archivo a subir (max 50MB)
- `extracted_text` (optional) - Texto extraído
- `summary` (optional) - Resumen del documento

**Ejemplo PowerShell:**
```powershell
$form = @{
    user_id = '5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc'
    file = Get-Item -Path 'C:\Documents\reporte.pdf'
    summary = 'Reporte mensual de ventas'
}

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1/documents' `
    -Method POST `
    -Form $form
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "assistant_conversation_id": 1,
        "original_name": "reporte.pdf",
        "path": "assistant_documents/5b2161d8.../abc123.pdf",
        "mime_type": "application/pdf",
        "size": 102400,
        "extracted_text": null,
        "summary": "Reporte mensual de ventas",
        "created_at": "2026-02-03T10:10:00Z"
    }
}
```

---

### 2️⃣7️⃣ Eliminar Documento
**DELETE** `/api/ddu/assistant/conversations/{id}/documents/{docId}`

**Query params:**
- `user_id` (required)

**Ejemplo:**
```powershell
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations/1/documents/1?user_id=5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc' -Method DELETE
```

**Response (200):**
```json
{
    "success": true,
    "message": "Documento eliminado correctamente"
}
```

---

### 🧪 Tests del Asistente DDU

```powershell
$userId = "5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc"

# Test 1: Crear conversación
$body = @{ user_id = $userId; title = 'Test conversación' } | ConvertTo-Json
$conv = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/ddu/assistant/conversations' -Method POST -ContentType 'application/json' -Body $body
$convId = $conv.data.id
Write-Host "Conversación creada: $convId"

# Test 2: Agregar mensaje del sistema
$body = @{ user_id = $userId; role = 'system'; content = 'Eres un asistente útil.' } | ConvertTo-Json
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant/conversations/$convId/messages" -Method POST -ContentType 'application/json' -Body $body

# Test 3: Agregar mensaje del usuario
$body = @{ user_id = $userId; role = 'user'; content = '¿Qué tareas tengo?' } | ConvertTo-Json
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant/conversations/$convId/messages" -Method POST -ContentType 'application/json' -Body $body

# Test 4: Agregar respuesta del asistente
$body = @{ user_id = $userId; role = 'assistant'; content = 'Tienes 3 tareas pendientes...' } | ConvertTo-Json
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant/conversations/$convId/messages" -Method POST -ContentType 'application/json' -Body $body

# Test 5: Obtener conversación completa
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant/conversations/$convId?user_id=$userId" -Method GET

# Test 6: Listar todas las conversaciones
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant/conversations?user_id=$userId" -Method GET

# Test 7: Actualizar título
$body = @{ user_id = $userId; title = 'Consulta de tareas actualizada' } | ConvertTo-Json
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant/conversations/$convId" -Method PUT -ContentType 'application/json' -Body $body

# Test 8: Eliminar conversación
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/ddu/assistant/conversations/$convId?user_id=$userId" -Method DELETE
```

---

## ✅ Estado Final

- ✅ Endpoint 1 (Lista Usuarios) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 2 (Añadir a Empresa) - **PROBADO Y FUNCIONANDO**  
- ✅ Endpoint 3 (Listar Miembros) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 4 (Actualizar Rol) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 5 (Eliminar Miembro) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 6 (Obtener Contactos) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 7 (Detalles Reunión Completo) - **IMPLEMENTADO**
- ✅ Endpoint 8 (Reuniones del Usuario) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 9 (Grupos de Reuniones) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 10 (Detalles de Reunión) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 11 (Descargar Archivos) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 12 (Tipo de Reunión) - **PROBADO Y FUNCIONANDO**
- ✅ Endpoint 13 (Tipo de Reunión Batch) - **PROBADO Y FUNCIONANDO**
- ✅ Validación de Usuario - **FUNCIONANDO**
- ✅ Check Membership - **FUNCIONANDO**
- ✅ **Sistema de Grupos en Empresas** - **PROBADO Y FUNCIONANDO**
  - ✅ CRUD de Grupos
  - ✅ Gestión de Miembros con Roles
  - ✅ Compartir Reuniones con Permisos
  - ✅ Descargar Archivos con Autorización Delegada
- ✅ **Configuración del Asistente DDU** - **IMPLEMENTADO**
  - ✅ Obtener configuración del asistente
  - ✅ Crear/actualizar configuración
  - ✅ Obtener API key desencriptada
  - ✅ Eliminar API key
- ✅ **Asistente DDU - Conversaciones** - **IMPLEMENTADO** 🆕
  - ✅ Listar conversaciones
  - ✅ Crear conversación
  - ✅ Obtener conversación con mensajes
  - ✅ Actualizar conversación
  - ✅ Eliminar conversación
  - ✅ Obtener mensajes
  - ✅ Agregar mensaje
  - ✅ Obtener documentos
  - ✅ Subir documento
  - ✅ Eliminar documento

**Total: 41 endpoints disponibles para Panel DDU** 🚀

---

**Última actualización:** 03/02/2026
**Servidor:** http://127.0.0.1:8000

