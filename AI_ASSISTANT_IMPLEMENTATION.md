# ASISTENTE IA - DISEÑO Y IMPLEMENTACIÓN

## Resumen del Diseño

Se ha diseñado un asistente IA integral para Juntify que permite:

### 1. **Chat Inteligente con Contexto**
- Chat central con historial de conversaciones
- Contexto dinámico basado en contenedores, reuniones, conversaciones y documentos
- Respuestas contextuales usando RAG (Retrieval Augmented Generation)

### 2. **Gestión de Contexto**
- **Contenedores**: Selección de contenedores específicos con todas sus reuniones
- **Reuniones**: Acceso a reuniones individuales o todas las reuniones
- **Conversaciones**: Integración con chats de contactos para análisis completo
- **Documentos**: Subida y análisis de documentos con OCR

### 3. **Panel de Detalles**
Pestañas con información detallada:
- **Resumen**: Resúmenes automáticos del contexto seleccionado
- **Puntos Clave**: Extracción de puntos importantes
- **Tareas**: Tareas relacionadas con el contexto
- **Transcripción**: Transcripciones completas cuando están disponibles

## Estructura de Base de Datos

### Tablas Creadas

#### 1. `ai_chat_sessions`
```sql
- id (primary key)
- username (foreign key a users)
- title (título de la conversación)
- context_data (JSON con datos del contexto)
- context_type (enum: general, container, meeting, contact_chat, documents)
- context_id (ID del elemento de contexto)
- is_active (boolean)
- last_activity (timestamp)
- timestamps
```

#### 2. `ai_chat_messages`
```sql
- id (primary key)
- session_id (foreign key a ai_chat_sessions)
- role (enum: user, assistant, system)
- content (longtext del mensaje)
- metadata (JSON con información adicional)
- attachments (JSON con referencias a archivos)
- is_hidden (boolean para mensajes del sistema)
- timestamps
```

#### 3. `ai_documents`
```sql
- id (primary key)
- username (foreign key a users)
- name (nombre del documento)
- original_filename (nombre original del archivo)
- document_type (enum: pdf, word, excel, powerpoint, image, text)
- mime_type (tipo MIME)
- file_size (tamaño en bytes)
- drive_file_id (ID en Google Drive)
- drive_folder_id (carpeta de Drive)
- drive_type (enum: personal, organization)
- extracted_text (texto extraído via OCR)
- ocr_metadata (JSON con metadatos del OCR)
- processing_status (enum: pending, processing, completed, failed)
- processing_error (texto del error si falla)
- document_metadata (JSON con metadatos adicionales)
- timestamps
```

#### 4. `ai_meeting_documents`
```sql
- id (primary key)
- document_id (foreign key a ai_documents)
- meeting_id (ID de la reunión)
- meeting_type (enum: legacy, modern)
- assigned_by_username (foreign key a users)
- assignment_note (nota de asignación)
- timestamps
```

#### 5. `ai_task_documents`
```sql
- id (primary key)
- document_id (foreign key a ai_documents)
- task_id (ID de la tarea)
- assigned_by_username (foreign key a users)
- assignment_note (nota de asignación)
- timestamps
```

#### 6. `ai_context_embeddings`
```sql
- id (primary key)
- username (foreign key a users)
- content_type (enum: meeting_summary, meeting_transcription, document_text, task_description, contact_message)
- content_id (ID del contenido referenciado)
- content_snippet (fragmento del contenido)
- embedding_vector (JSON con vector de embedding)
- metadata (JSON con metadatos adicionales)
- timestamps
```

## Modelos Laravel

Se han creado los siguientes modelos con sus relaciones:

- `AiChatSession`: Gestión de sesiones de chat
- `AiChatMessage`: Mensajes individuales del chat
- `AiDocument`: Documentos subidos para análisis
- `AiMeetingDocument`: Relación entre documentos y reuniones
- `AiTaskDocument`: Relación entre documentos y tareas
- `AiContextEmbedding`: Embeddings para búsqueda semántica

## Controlador Principal

`AiAssistantController` maneja:

### Sesiones de Chat
- `getSessions()`: Obtener todas las sesiones del usuario
- `createSession()`: Crear nueva sesión con contexto
- `getMessages()`: Obtener mensajes de una sesión
- `sendMessage()`: Enviar mensaje y procesar respuesta IA

### Contexto y Datos
- `getContainers()`: Obtener contenedores del usuario
- `getMeetings()`: Obtener reuniones del usuario
- `getContactChats()`: Obtener conversaciones con contactos

### Documentos
- `uploadDocument()`: Subir nuevo documento
- `getDocuments()`: Obtener documentos existentes

## Interface de Usuario

### Diseño de 3 Columnas
1. **Sidebar Izquierdo (320px)**: Historial de chats
2. **Área Central**: Chat principal con controles de contexto
3. **Panel Derecho (400px)**: Detalles con pestañas

### Características Visuales
- Diseño consistente con el tema existente de Juntify
- Colores basados en las variables CSS existentes
- Responsive design para móviles y tablets
- Modales para selección de contexto y subida de documentos

### Controles de Contexto
- **Botón Contenedores**: Selector de contenedores con búsqueda
- **Botón Conversaciones**: Selector de chats con contactos
- **Botón Documentos**: Uploader con drag & drop y gestión de archivos

## Funcionalidades Implementadas

### 1. **Chat Inteligente**
- Historial persistente de conversaciones
- Mensajes en tiempo real
- Sugerencias contextales
- Adjuntos y referencias

### 2. **Gestión de Contexto**
- Selección dinámica de contexto
- Carga automática de datos relevantes
- Indicadores visuales del contexto activo

### 3. **Subida de Documentos**
- Drag & drop interface
- Validación de tipos de archivo
- Integración con Google Drive
- Procesamiento OCR para extracción de texto

### 4. **Análisis Semántico**
- Embeddings para búsqueda contextual
- RAG (Retrieval Augmented Generation)
- Indexación automática de contenido

## Rutas API

```php
// Sesiones
GET    /api/ai-assistant/sessions
POST   /api/ai-assistant/sessions
GET    /api/ai-assistant/sessions/{id}/messages
POST   /api/ai-assistant/sessions/{id}/messages

// Contexto
GET    /api/ai-assistant/containers
GET    /api/ai-assistant/meetings
GET    /api/ai-assistant/contact-chats

// Documentos
GET    /api/ai-assistant/documents
POST   /api/ai-assistant/documents/upload

// Vista principal
GET    /ai-assistant
```

## Archivos Creados

### Migraciones
- `2025_01_15_000001_create_ai_chat_sessions_table.php`
- `2025_01_15_000002_create_ai_chat_messages_table.php`
- `2025_01_15_000003_create_ai_documents_table.php`
- `2025_01_15_000004_create_ai_meeting_documents_table.php`
- `2025_01_15_000005_create_ai_task_documents_table.php`
- `2025_01_15_000006_create_ai_context_embeddings_table.php`

### Modelos
- `app/Models/AiChatSession.php`
- `app/Models/AiChatMessage.php`
- `app/Models/AiDocument.php`
- `app/Models/AiMeetingDocument.php`
- `app/Models/AiTaskDocument.php`
- `app/Models/AiContextEmbedding.php`

### Controlador
- `app/Http/Controllers/AiAssistantController.php`

### Vistas
- `resources/views/ai-assistant/index.blade.php`
- `resources/views/ai-assistant/modals/container-selector.blade.php`
- `resources/views/ai-assistant/modals/contact-chat-selector.blade.php`
- `resources/views/ai-assistant/modals/document-uploader.blade.php`

### Assets
- `public/css/ai-assistant.css`
- `public/js/ai-assistant.js`

## Próximos Pasos para Implementación

### 1. **Integración con IA**
- Implementar cliente para OpenAI/Claude
- Configurar embeddings con modelos como text-embedding-ada-002
- Desarrollar sistema RAG completo

### 2. **Procesamiento OCR**
- Integrar Google Cloud Vision API o Tesseract
- Procesamiento en background con queues
- Extracción de texto de diferentes tipos de documentos

### 3. **Optimizaciones**
- Implementar caché para embeddings
- Sistema de notificaciones en tiempo real
- Búsqueda vectorial optimizada

### 4. **Características Avanzadas**
- Exportación de conversaciones
- Compartir contextos entre usuarios
- Análisis de sentimientos en conversaciones

## Consideraciones de Seguridad

- Validación de permisos por usuario
- Sanitización de contenido
- Encriptación de datos sensibles
- Auditoría de accesos

## Notas Importantes

- **NO ejecutar las migraciones** desde desarrollo - debe hacerse desde el VPS
- La integración con IA debe configurarse con las claves API apropiadas
- El sistema está diseñado para escalar con más tipos de contexto
- Mantiene compatibilidad con el sistema existente de reuniones legacy y modernas
