# 📚 Sistema de Tutorial Interactivo para Juntify

## 🎯 Descripción

El sistema de tutorial interactivo de Juntify utiliza **Shepherd.js** para proporcionar tours guiados contextuales que ayudan a los usuarios a navegar y entender las funcionalidades de la plataforma.

## ✨ Características

- **Tours Contextuales**: Tutorial diferente para cada sección de la aplicación
- **Progreso Persistente**: El sistema recuerda el progreso del usuario
- **Preferencias Personalizables**: Los usuarios pueden configurar su experiencia
- **Responsive**: Funciona perfectamente en dispositivos móviles
- **Tema Personalizado**: Integrado con el diseño oscuro de Juntify

## 🚀 Funcionalidades Implementadas

### 1. **Motor de Tutorial (Shepherd.js)**
- Sistema de pasos guiados con highlighting automático
- Navegación intuitiva con botones de siguiente/anterior
- Tooltips posicionables con flechas direccionales
- Modal overlay para enfocar elementos

### 2. **Sistema de Progreso**
- Tracking de pasos completados por usuario
- Estado de finalización de secciones
- Fecha de última interacción
- Configuración de auto-inicio para nuevos usuarios

### 3. **API REST Completa**
```php
GET  /tutorial/status      - Estado del tutorial del usuario
POST /tutorial/progress    - Actualizar progreso
PUT  /tutorial/preferences - Configurar preferencias
POST /tutorial/reset       - Reiniciar tutorial
GET  /tutorial/config      - Configuración por página
GET  /tutorial/settings    - Página de configuración
```

### 4. **Secciones de Tutorial**

#### 📅 **Reuniones**
- Navegación por la interfaz
- Creación de nuevas reuniones
- Gestión de lista de reuniones
- Funciones de búsqueda y filtrado

#### 🤖 **Asistente IA**
- Introducción al chat inteligente
- Cómo hacer consultas específicas
- Consultas sobre participantes
- Análisis de transcripciones

#### 👥 **Contactos**
- Gestión de participantes
- Organización de colaboradores
- Funciones de importación/exportación

#### 📋 **Tareas**
- Creación y asignación
- Seguimiento de progreso
- Integración con reuniones

#### ⚙️ **Organización**
- Contenedores y proyectos
- Gestión de equipos
- Configuraciones avanzadas

## 🛠️ Instalación y Configuración

### 1. **Dependencias**
```bash
# Instalar Shepherd.js
npm install shepherd.js
```

### 2. **Archivos Principales**
```
resources/
├── js/
│   └── tutorial.js              # Lógica principal del tutorial
├── css/
│   └── tutorial.css             # Estilos personalizados
└── views/
    ├── components/
    │   └── tutorial.blade.php   # Componente Blade
    └── tutorial/
        └── settings.blade.php   # Página de configuración
```

### 3. **Controlador Backend**
```php
app/Http/Controllers/TutorialController.php
```

### 4. **Rutas**
```php
// En routes/web.php
Route::prefix('tutorial')->name('tutorial.')->group(function () {
    Route::get('/status', [TutorialController::class, 'getStatus']);
    Route::post('/progress', [TutorialController::class, 'updateProgress']);
    // ... más rutas
});
```

## 🎨 Personalización

### **Temas y Estilos**

El tutorial utiliza un tema personalizado que coincide con el diseño de Juntify:

```css
/* Colores principales */
.shepherd-theme-custom .shepherd-element {
    background: rgb(30, 41, 59);     /* slate-800 */
    border: 1px solid rgb(51, 65, 85); /* slate-700 */
    color: rgb(226, 232, 240);       /* slate-200 */
}

/* Botones primarios */
.shepherd-button.btn-primary {
    background: rgb(14, 165, 233);   /* sky-500 */
    border-color: rgb(56, 189, 248); /* sky-400 */
}
```

### **Atributos data-tutorial**

Para que el tutorial pueda identificar elementos, añade atributos `data-tutorial`:

```html
<!-- Navegación principal -->
<nav data-tutorial="navigation">...</nav>

<!-- Área de reuniones -->
<div data-tutorial="meetings-list">...</div>

<!-- Chat del asistente -->
<div data-tutorial="ai-chat">...</div>
```

## 📱 Uso para Usuarios

### **Auto-inicio**
- El tutorial se inicia automáticamente para nuevos usuarios
- Se puede desactivar en las preferencias del usuario

### **Botón de Ayuda**
- Botón flotante siempre disponible (personalizable)
- Permite iniciar el tutorial en cualquier momento
- Posicionado en la esquina inferior derecha

### **Navegación**
- **Siguiente**: Avanza al próximo paso
- **Anterior**: Regresa al paso previo
- **Saltar**: Omite el tutorial actual
- **Cerrar**: Finaliza el tutorial (guarda progreso)

### **Configuración de Preferencias**
Accesible desde el perfil del usuario:

- ✅ **Auto-iniciar tutorial**: Para nuevos usuarios
- ✅ **Mostrar botón de ayuda**: Botón flotante
- ✅ **Saltar secciones completadas**: Optimización de experiencia

## 🔧 API y Desarrollo

### **Endpoints Principales**

```javascript
// Obtener estado del tutorial
fetch('/tutorial/status')
  .then(response => response.json())
  .then(data => console.log(data));

// Actualizar progreso
fetch('/tutorial/progress', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    step: 3,
    section: 'meetings',
    action: 'step_completed'
  })
});
```

### **Funciones JavaScript Globales**

```javascript
// Iniciar tutorial manualmente
window.startTutorial();

// Reiniciar tutorial (con confirmación)
window.resetTutorial();

// Acceso al objeto tutorial
window.juntifyTutorial.tour.start();
```

## 📊 Datos y Analytics

### **Estructura de Datos**
```json
{
  "completed": false,
  "current_step": 2,
  "completed_sections": ["meetings", "contacts"],
  "last_seen": "2024-11-24T10:30:00Z",
  "preferences": {
    "auto_start": true,
    "show_help_button": true,
    "skip_completed_sections": false
  }
}
```

### **Métricas Disponibles**
- Tasa de finalización del tutorial
- Puntos de abandono más comunes
- Secciones más/menos utilizadas
- Tiempo promedio de completación

## 🚀 Extensibilidad

### **Añadir Nueva Sección**

1. **Definir pasos en tutorial.js:**
```javascript
getNewSectionSteps() {
    return [
        {
            title: 'Nueva Funcionalidad',
            text: 'Descripción de la nueva función...',
            attachTo: { element: '[data-tutorial="new-feature"]', on: 'bottom' }
        }
    ];
}
```

2. **Añadir elementos HTML:**
```html
<div data-tutorial="new-feature">Nueva funcionalidad</div>
```

3. **Actualizar método principal:**
```javascript
case 'new-section':
    return [...commonSteps, ...this.getNewSectionSteps()];
```

### **Personalizar Comportamiento**

```javascript
// Personalizar eventos del tour
tutorial.tour.on('show', function(event) {
    console.log('Mostrando paso:', event.step);
});

tutorial.tour.on('complete', function() {
    // Acción personalizada al completar
});
```

## 🐛 Debugging y Troubleshooting

### **Logs del Sistema**
El tutorial incluye logging detallado:

```bash
# Logs de Laravel
tail -f storage/logs/laravel.log | grep Tutorial

# Logs de JavaScript en consola del navegador
console.log('Tutorial initialized:', window.juntifyTutorial);
```

### **Problemas Comunes**

1. **Tutorial no aparece:**
   - Verificar que `data-tutorial` está presente
   - Comprobar que el usuario está autenticado
   - Revisar configuración en localStorage

2. **Elementos no destacados:**
   - Verificar selectores CSS
   - Comprobar que elementos son visibles
   - Revisar z-index conflicts

3. **Progreso no guardado:**
   - Verificar CSRF token
   - Comprobar rutas API
   - Revisar logs del servidor

## 📈 Mejoras Futuras

### **Características Planeadas**
- [ ] Analytics avanzados de uso
- [ ] Tutorial multi-idioma
- [ ] Tours condicionales basados en rol
- [ ] Integración con sistema de notificaciones
- [ ] Tutorial en video embebido
- [ ] Gamificación (badges, puntos)

### **Optimizaciones Técnicas**
- [ ] Lazy loading de pasos de tutorial
- [ ] Caché inteligente de configuraciones
- [ ] Compresión de datos de progreso
- [ ] API rate limiting para endpoints

## 👥 Contribución

Para contribuir al sistema de tutorial:

1. Fork del repositorio
2. Crear rama feature: `git checkout -b tutorial-nueva-funcionalidad`
3. Hacer cambios siguiendo convenciones
4. Probar en diferentes dispositivos
5. Crear Pull Request con descripción detallada

## 📄 Licencia

Este sistema de tutorial es parte de Juntify y está sujeto a la misma licencia del proyecto principal.

---

¿Necesitas ayuda con el tutorial? Contacta al equipo de desarrollo o consulta la documentación en línea. 🚀
