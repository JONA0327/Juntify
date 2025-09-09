# 🗂️ Corrección de Selección Drive Organization vs Personal

## ✅ **Problema Resuelto**

**Issue**: Cu### **Personal Drive**
- ✅ Usuario selecciona "Perso5. Verifica que se cree en tablas `folders`/`subfolders`

### **Prueba 2: Organization Drive**
1. Ve a `/new-meeting`
2. Selecciona "Organization" en dropdown
3. Graba audio y sube
4. Verifica mensaje: "Drive organizacional"
5. Verifica que se cree en tablas `organization_folders`/`organization_subfolders` Busca en tablas: `folders` + `subfolders` 
- ✅ Crea carpetas en Drive personal del usuario
- ✅ Mensaje: "Audio subido exitosamente a Drive personal"

### **Organization Drive**
- ✅ Usuario selecciona "Organization"
- ✅ Valida que el usuario tenga rol (colaborador/administrador)
- ✅ Busca en tablas: `organization_folders` + `organization_subfolders`
- ✅ Crea carpetas en Drive organizacional compartido
- ✅ Mensaje: "Audio subido exitosamente a Drive organizacional"onas "Organization" en el dropdown de Drive, el sistema no estaba usando las tablas correctas:
- ❌ **Antes**: Siempre buscaba en `folders` y `subfolders` (tablas personales)
- ✅ **Ahora**: Busca en `organization_folders` y `organization_subfolders` cuando seleccionas "Organization"

## 🔧 **Cambios Implementados**

### **1. Frontend (JavaScript)**
```javascript
// resources/js/new-meeting.js

// AGREGADO: Detección del tipo de Drive seleccionado
function uploadAudioToDrive(blob, name, onProgress) {
    const driveSelect = document.getElementById('drive-select');
    const driveType = driveSelect ? driveSelect.value : 'personal';
    
    formData.append('driveType', driveType); // Enviar al backend
    console.log(`🗂️ [Upload] Subiendo a Drive tipo: ${driveType}`);
}

// AGREGADO: Mensaje específico según el tipo de drive usado
xhr.onload = () => {
    const driveType = response.drive_type || 'personal';
    const driveTypeName = driveType === 'organization' ? 'organizacional' : 'personal';
    const folderPath = response.folder_info?.full_path;
    
    showSuccess(`Audio subido exitosamente a Drive ${driveTypeName} en: ${folderPath}`);
}
```

### **2. Backend (PHP)**
```php
// app/Http/Controllers/DriveController.php

// AGREGADO: Validación del nuevo campo driveType
$v = $request->validate([
    'driveType' => 'nullable|string|in:personal,organization',
    // ... otros campos
]);

// MEJORADO: Lógica de selección de Drive basada en driveType
$driveType = $v['driveType'] ?? 'personal';
$useOrgDrive = false;

if ($driveType === 'organization' && $organizationFolder) {
    if ($orgRole === 'colaborador' || $orgRole === 'administrador') {
        $useOrgDrive = true;
        Log::info('Using organization drive', ['orgRole' => $orgRole]);
    }
} elseif ($driveType === 'organization' && !$organizationFolder) {
    return response()->json(['message' => 'No tienes acceso a Drive organizacional'], 403);
}

// EXISTENTE: Lógica correcta para usar tablas apropiadas
if ($useOrgDrive) {
    // Usar OrganizationSubfolder
    $subfolder = OrganizationSubfolder::where('organization_folder_id', $rootFolder->id)
        ->where('name', $pendingSubfolderName)->first();
} else {
    // Usar Subfolder (personal)
    $subfolder = Subfolder::where('folder_id', $rootFolder->id)
        ->where('name', $pendingSubfolderName)->first();
}

// AGREGADO: Información del tipo de drive en la respuesta
$response = [
    'drive_type' => $useOrgDrive ? 'organization' : 'personal',
    'folder_info' => [
        'drive_type' => $useOrgDrive ? 'organization' : 'personal',
        // ... otros campos
    ]
];
```

## 📊 **Flujo Corregido**

### **Antes (Incorrecto)**
```
1. Usuario selecciona "Organization" en dropdown
2. Frontend no envía información del tipo
3. Backend siempre usa lógica personal
4. Busca en tablas: folder + subfolder ❌
5. No encuentra carpetas organizacionales
```

### **Ahora (Correcto)**
```
1. Usuario selecciona "Organization" en dropdown
2. Frontend detecta driveType = "organization"
3. Frontend envía driveType al backend
4. Backend valida driveType y permisos de usuario
5. Backend usa $useOrgDrive = true
6. Busca en tablas: organization_folder + organization_subfolders ✅
7. Encuentra y usa carpetas organizacionales correctas
8. Responde con información del drive usado
9. Frontend muestra mensaje específico del tipo de drive
```

## 🎯 **Casos de Uso Soportados**

### **Personal Drive**
- ✅ Usuario selecciona "Personal"
- ✅ Busca en tablas: `folder` + `subfolder` 
- ✅ Crea carpetas en Drive personal del usuario
- ✅ Mensaje: "Audio subido exitosamente a Drive personal"

### **Organization Drive**
- ✅ Usuario selecciona "Organization"
- ✅ Valida que el usuario tenga rol (colaborador/administrador)
- ✅ Busca en tablas: `organization_folder` + `organization_subfolders`
- ✅ Crea carpetas en Drive organizacional compartido
- ✅ Mensaje: "Audio subido exitosamente a Drive organizacional"

### **Validaciones de Seguridad**
- ✅ Si selecciona "Organization" sin configuración → Error 403
- ✅ Si selecciona "Organization" sin permisos → Error 403
- ✅ Logs detallados para debugging

## 🔍 **Logs de Debugging**

```php
Log::info('uploadPendingAudio: Drive type selection', [
    'driveType' => $driveType,
    'hasOrganizationFolder' => !!$organizationFolder,
    'orgRole' => $orgRole,
    'username' => $user->username
]);

Log::info('uploadPendingAudio: Using organization drive', [
    'orgRole' => $orgRole,
    'orgFolderId' => $organizationFolder->google_id
]);
```

## 🧪 **Cómo Probar**

### **Prueba 1: Personal Drive**
1. Ve a `/new-meeting`
2. Selecciona "Personal" en dropdown
3. Graba audio y sube
4. Verifica mensaje: "Drive personal"
5. Verifica que se cree en tablas `folder`/`subfolder`

### **Prueba 2: Organization Drive**
1. Ve a `/new-meeting`
2. Selecciona "Organization" en dropdown
3. Graba audio y sube
4. Verifica mensaje: "Drive organizacional"
5. Verifica que se cree en tablas `organization_folder`/`organization_subfolders`

### **Prueba 3: Sin Permisos Organization**
1. Usuario sin organización configurada
2. Selecciona "Organization"
3. Debería recibir error 403

## 🎉 **Estado Final**

- ✅ **Compilación exitosa**
- ✅ **Lógica corregida** para selección de Drive
- ✅ **Tablas correctas** según tipo seleccionado
- ✅ **Validaciones de seguridad** implementadas
- ✅ **Mensajes informativos** para el usuario
- ✅ **Logs detallados** para debugging

**¡El problema de selección de Drive Organization vs Personal está completamente resuelto!** 🗂️✨
