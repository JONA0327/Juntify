# API de Validación de Usuario y Empresa

## Endpoints disponibles

### 1. Validar Usuario y Pertenencia a Empresa
**POST** `/api/auth/validate-user`

Valida las credenciales de un usuario y verifica su pertenencia a empresas.

#### Request Body
```json
{
  "email": "usuario@ejemplo.com",
  "password": "contraseña_secreta",
  "nombre_empresa": "DDU" // Opcional
}
```

#### Response Exitosa (200)
```json
{
  "success": true,
  "message": "Usuario validado exitosamente",
  "user_exists": true,
  "belongs_to_company": true,
  "company": {
    "id": 1,
    "nombre": "DDU",
    "rol_usuario": "founder",
    "es_propietario": true,
    "es_administrador": true,
    "permisos": ["acceso_total"]
  },
  "user": {
    "id": "uuid-del-usuario",
    "name": "Nombre Usuario",
    "email": "usuario@ejemplo.com",
    "rol": "founder",
    "plan": "founder",
    "plan_code": "founder",
    "is_active": true
  },
  "all_companies": [
    {
      "id": 1,
      "nombre": "DDU",
      "rol_usuario": "founder",
      "es_propietario": true,
      "es_administrador": true,
      "permisos": ["acceso_total"]
    }
  ]
}
```

#### Response - Usuario no encontrado (404)
```json
{
  "success": false,
  "message": "Usuario no encontrado",
  "user_exists": false,
  "belongs_to_company": false,
  "company": null
}
```

#### Response - Contraseña incorrecta (401)
```json
{
  "success": false,
  "message": "Contraseña incorrecta",
  "user_exists": true,
  "belongs_to_company": false,
  "company": null
}
```

#### Response - Usuario no pertenece a empresa específica (403)
```json
{
  "success": false,
  "message": "El usuario no pertenece a la empresa 'DDU'",
  "user_exists": true,
  "belongs_to_company": false,
  "company": null,
  "user": {
    "id": "uuid",
    "name": "Nombre",
    "email": "email@ejemplo.com",
    "rol": "basic"
  },
  "available_companies": []
}
```

---

### 2. Verificar Pertenencia a Empresa (sin validar contraseña)
**POST** `/api/auth/check-company-membership`

Verifica si un usuario pertenece a una empresa específica. Útil cuando ya existe una sesión activa.

#### Request Body
```json
{
  "user_id": "uuid-del-usuario",
  "nombre_empresa": "DDU"
}
```

#### Response Exitosa (200)
```json
{
  "success": true,
  "belongs_to_company": true,
  "company": {
    "id": 1,
    "nombre": "DDU",
    "rol_usuario": "founder",
    "es_propietario": true,
    "es_administrador": true
  },
  "user": {
    "id": "uuid",
    "name": "Nombre Usuario",
    "email": "usuario@ejemplo.com"
  }
}
```

#### Response - No pertenece (403)
```json
{
  "success": false,
  "message": "El usuario no pertenece a la empresa especificada",
  "belongs_to_company": false
}
```

---

## Ejemplo de Uso desde Otro Proyecto

### PHP (Laravel/Guzzle)
```php
use Illuminate\Support\Facades\Http;

$response = Http::post('http://juntify.test/api/auth/validate-user', [
    'email' => 'usuario@ejemplo.com',
    'password' => $request->password,
    'nombre_empresa' => 'DDU'
]);

if ($response->successful() && $response->json('success')) {
    $company = $response->json('company');
    $user = $response->json('user');
    
    // Usuario válido y pertenece a la empresa DDU
    if ($company && $company['nombre'] === 'DDU') {
        // Permitir acceso al sistema
        session(['juntify_user' => $user]);
        session(['juntify_company' => $company]);
        return redirect()->route('dashboard');
    }
}

// Acceso denegado
return back()->withErrors(['message' => 'Acceso denegado']);
```

### JavaScript (Fetch API)
```javascript
async function validarUsuario(email, password, empresa) {
    try {
        const response = await fetch('http://juntify.test/api/auth/validate-user', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                email: email,
                password: password,
                nombre_empresa: empresa
            })
        });

        const data = await response.json();

        if (data.success && data.belongs_to_company) {
            // Usuario válido y pertenece a la empresa
            console.log('Usuario:', data.user);
            console.log('Empresa:', data.company);
            return true;
        }

        return false;
    } catch (error) {
        console.error('Error:', error);
        return false;
    }
}

// Uso
if (await validarUsuario('usuario@ejemplo.com', 'password123', 'DDU')) {
    // Redirigir al dashboard
    window.location.href = '/dashboard';
}
```

### cURL
```bash
curl -X POST http://juntify.test/api/auth/validate-user \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "usuario@ejemplo.com",
    "password": "contraseña_secreta",
    "nombre_empresa": "DDU"
  }'
```

---

## Notas de Seguridad

1. **Rate Limiting**: Los endpoints tienen un límite de 60 peticiones por minuto.

2. **HTTPS**: En producción, asegúrate de usar HTTPS para proteger las credenciales.

3. **CORS**: Si necesitas hacer peticiones desde un dominio diferente, configura CORS en `config/cors.php`:
   ```php
   'paths' => ['api/*'],
   'allowed_origins' => ['https://tu-otro-proyecto.com'],
   ```

4. **API Token (Opcional)**: Para mayor seguridad, considera agregar un API token:
   ```php
   // En el middleware
   if ($request->header('X-API-Token') !== config('app.api_token')) {
       return response()->json(['error' => 'Unauthorized'], 401);
   }
   ```

---

## Flujo de Autenticación Recomendado

1. Usuario intenta acceder al sistema externo
2. Sistema externo envía credenciales a `/api/auth/validate-user` con el nombre de empresa
3. Juntify valida:
   - ✓ Usuario existe
   - ✓ Contraseña correcta
   - ✓ Usuario pertenece a la empresa especificada
4. Si todo es correcto, el sistema externo crea sesión y permite acceso
5. Para validaciones posteriores (sin contraseña), usar `/api/auth/check-company-membership`

---

## Testing

Puedes probar los endpoints usando herramientas como:
- **Postman**
- **Insomnia**
- **Thunder Client** (extensión de VS Code)
- **cURL** (línea de comandos)

Ejemplo de prueba rápida:
```bash
# Reemplaza los valores con datos reales
curl -X POST http://127.0.0.1:8000/api/auth/validate-user \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"password","nombre_empresa":"DDU"}'
```
