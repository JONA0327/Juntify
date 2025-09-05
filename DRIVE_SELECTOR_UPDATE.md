# Actualización del Selector de Drive

## Cambios Realizados

Se ha actualizado el selector de Drive para mostrar los nombres reales de las carpetas raíz en lugar de las opciones genéricas "Personal" y "Organization".

### Archivos Modificados

1. **resources/js/audio-processing.js**
   - Agregada función `loadDriveOptions()` para cargar dinámicamente las opciones con nombres reales
   - Modificada función `loadDriveFolders()` para llamar a `loadDriveOptions()`
   - Ajustado el manejo del sessionStorage

2. **resources/js/reuniones_v2.js**
   - Agregada función `loadDriveOptions()` para cargar dinámicamente las opciones con nombres reales
   - Modificada función `loadDriveFolders()` para llamar a `loadDriveOptions()`

### Funcionalidad

**Antes:**
- El selector mostraba opciones estáticas: "Personal" y "Organization"

**Después:**
- Para administradores: El selector muestra los nombres reales de las carpetas
  - 🏠 [Nombre de la carpeta personal]
  - 🏢 [Nombre de la carpeta de organización]
- Para colaboradores: El selector sigue oculto como antes

### Lógica de Funcionamiento

1. **Al cargar la página:**
   - Se ejecuta `loadDriveOptions()` que hace llamadas a los endpoints de Drive
   - Obtiene los nombres reales de las carpetas desde `/drive/sync-subfolders` (personal) y `/api/organizations/{id}/drive/subfolders` (organización)
   - Pobla el selector con los nombres reales precedidos por iconos

2. **Manejo de errores:**
   - Si no se pueden obtener los nombres reales, se muestran las opciones por defecto
   - Se registran warnings en consola para debugging

3. **Compatibilidad:**
   - Los valores del selector siguen siendo 'personal' y 'organization' para mantener compatibilidad con el backend
   - Solo cambia el texto mostrado al usuario

### Beneficios

- **Mejor UX:** Los usuarios ven inmediatamente qué carpeta específica están eligiendo
- **Claridad:** No hay confusión sobre qué significa "Personal" u "Organization"
- **Consistencia:** Los nombres mostrados coinciden con los que se ven en Google Drive

### Compatibilidad

- ✅ Mantiene total compatibilidad con el backend existente
- ✅ Los colaboradores no ven cambios (selector sigue oculto)
- ✅ Fallback a opciones originales en caso de error
- ✅ Preserva el sessionStorage para recordar selección

## Ejemplo de Uso

Antes: 
```
Drive: [Personal ▼] [Organization ▼]
```

Después:
```
Drive: [🏠 Juntify-Reuniones-2025 ▼] [🏢 Organización-ABC-Grabaciones ▼]
```
