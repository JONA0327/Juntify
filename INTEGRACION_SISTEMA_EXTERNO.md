# Integración de Login con Validación Juntify

## Implementación en el Sistema Externo (DDU)

---

## Opción 1: PHP Vanilla

```php
<?php
// login.php

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validar contra Juntify
    $resultado = validarUsuarioJuntify($email, $password, 'DDU');
    
    if ($resultado['success'] && $resultado['belongs_to_company']) {
        // Usuario válido y pertenece a DDU
        $_SESSION['user_id'] = $resultado['user']['id'];
        $_SESSION['user_name'] = $resultado['user']['name'];
        $_SESSION['user_email'] = $resultado['user']['email'];
        $_SESSION['company'] = $resultado['company'];
        $_SESSION['authenticated'] = true;
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = $resultado['message'] ?? 'Acceso denegado. No pertenece a la empresa DDU.';
    }
}

function validarUsuarioJuntify($email, $password, $empresa) {
    $url = 'http://127.0.0.1:8000/api/auth/validate-user';
    
    $data = [
        'email' => $email,
        'password' => $password,
        'nombre_empresa' => $empresa
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login DDU</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 100px auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h2>Acceso Sistema DDU</h2>
    
    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit">Iniciar Sesión</button>
    </form>
    
    <p style="margin-top: 20px; text-align: center; color: #666; font-size: 14px;">
        Solo usuarios de la empresa DDU pueden acceder
    </p>
</body>
</html>
```

```php
<?php
// dashboard.php - Página protegida

session_start();

if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard DDU</title>
</head>
<body>
    <h1>Bienvenido <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
    <p>Email: <?= htmlspecialchars($_SESSION['user_email']) ?></p>
    <p>Empresa: <?= htmlspecialchars($_SESSION['company']['nombre']) ?></p>
    <p>Rol: <?= htmlspecialchars($_SESSION['company']['rol_usuario']) ?></p>
    
    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>
```

---

## Opción 2: Laravel (PHP Framework)

### Controlador de Autenticación

```php
<?php
// app/Http/Controllers/Auth/JuntifyLoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class JuntifyLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.juntify-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)->post('http://127.0.0.1:8000/api/auth/validate-user', [
                'email' => $request->email,
                'password' => $request->password,
                'nombre_empresa' => 'DDU'
            ]);

            $data = $response->json();

            if ($response->successful() && 
                isset($data['success']) && 
                $data['success'] === true && 
                isset($data['belongs_to_company']) && 
                $data['belongs_to_company'] === true) {
                
                // Usuario válido y pertenece a DDU
                Session::put('juntify_user', $data['user']);
                Session::put('juntify_company', $data['company']);
                Session::put('authenticated', true);
                
                return redirect()->route('dashboard')
                    ->with('success', 'Bienvenido ' . $data['user']['name']);
            }

            // Usuario no válido o no pertenece a DDU
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $data['message'] ?? 'No tienes acceso a este sistema. Solo usuarios de DDU pueden ingresar.'
                ]);

        } catch (\Exception $e) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Error al conectar con el servidor de autenticación. Intenta nuevamente.'
                ]);
        }
    }

    public function logout()
    {
        Session::flush();
        return redirect()->route('login')
            ->with('success', 'Sesión cerrada correctamente');
    }
}
```

### Middleware de Protección

```php
<?php
// app/Http/Middleware/CheckJuntifyAuth.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckJuntifyAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('authenticated') || !session('juntify_user')) {
            return redirect()->route('login')
                ->withErrors(['message' => 'Debes iniciar sesión para acceder.']);
        }

        // Validar que sea empresa DDU
        $company = session('juntify_company');
        if (!$company || strtolower($company['nombre']) !== 'ddu') {
            session()->flush();
            return redirect()->route('login')
                ->withErrors(['message' => 'Acceso denegado. Solo usuarios de DDU pueden acceder.']);
        }

        return $next($request);
    }
}
```

### Rutas

```php
<?php
// routes/web.php

use App\Http\Controllers\Auth\JuntifyLoginController;

Route::get('/login', [JuntifyLoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [JuntifyLoginController::class, 'login']);
Route::post('/logout', [JuntifyLoginController::class, 'logout'])->name('logout');

Route::middleware('juntify.auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard', [
            'user' => session('juntify_user'),
            'company' => session('juntify_company')
        ]);
    })->name('dashboard');
    
    // Todas las demás rutas protegidas aquí
});
```

### Vista de Login

```blade
{{-- resources/views/auth/juntify-login.blade.php --}}

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login DDU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center">Acceso Sistema DDU</h2>
            
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif
            
            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-bold mb-2">
                        Email
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-bold mb-2">
                        Contraseña
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-600 transition">
                    Iniciar Sesión
                </button>
            </form>
            
            <p class="mt-4 text-center text-gray-600 text-sm">
                Solo usuarios de la empresa DDU pueden acceder
            </p>
        </div>
    </div>
</body>
</html>
```

### Registrar Middleware

```php
<?php
// app/Http/Kernel.php

protected $middlewareAliases = [
    // ... otros middlewares
    'juntify.auth' => \App\Http\Middleware\CheckJuntifyAuth::class,
];
```

---

## Opción 3: JavaScript/Node.js (Express)

```javascript
// routes/auth.js

const express = require('express');
const axios = require('axios');
const router = express.Router();

// Mostrar formulario de login
router.get('/login', (req, res) => {
    res.render('login', { error: null });
});

// Procesar login
router.post('/login', async (req, res) => {
    const { email, password } = req.body;

    try {
        const response = await axios.post('http://127.0.0.1:8000/api/auth/validate-user', {
            email: email,
            password: password,
            nombre_empresa: 'DDU'
        });

        const data = response.data;

        if (data.success && data.belongs_to_company) {
            // Usuario válido y pertenece a DDU
            req.session.user = data.user;
            req.session.company = data.company;
            req.session.authenticated = true;

            return res.redirect('/dashboard');
        }

        res.render('login', { 
            error: data.message || 'No tienes acceso. Solo usuarios de DDU pueden ingresar.' 
        });

    } catch (error) {
        console.error('Error de autenticación:', error.message);
        
        const errorMessage = error.response?.data?.message || 
                           'Error al conectar con el servidor de autenticación';
        
        res.render('login', { error: errorMessage });
    }
});

// Logout
router.get('/logout', (req, res) => {
    req.session.destroy();
    res.redirect('/login');
});

module.exports = router;
```

```javascript
// middleware/authMiddleware.js

function requireAuth(req, res, next) {
    if (!req.session.authenticated || !req.session.user) {
        return res.redirect('/login');
    }

    // Verificar que sea empresa DDU
    if (!req.session.company || req.session.company.nombre.toLowerCase() !== 'ddu') {
        req.session.destroy();
        return res.redirect('/login');
    }

    next();
}

module.exports = requireAuth;
```

```javascript
// app.js

const express = require('express');
const session = require('express-session');
const authRoutes = require('./routes/auth');
const requireAuth = require('./middleware/authMiddleware');

const app = express();

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(session({
    secret: 'tu-clave-secreta-aqui',
    resave: false,
    saveUninitialized: false,
    cookie: { secure: false, maxAge: 3600000 } // 1 hora
}));

// Rutas de autenticación
app.use('/', authRoutes);

// Rutas protegidas
app.get('/dashboard', requireAuth, (req, res) => {
    res.render('dashboard', {
        user: req.session.user,
        company: req.session.company
    });
});

app.listen(3000, () => {
    console.log('Servidor corriendo en http://localhost:3000');
});
```

---

## Opción 4: JavaScript Puro (Frontend)

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login DDU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center">Acceso Sistema DDU</h2>
            
            <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            </div>
            
            <form id="login-form">
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-bold mb-2">Email</label>
                    <input type="email" 
                           id="email" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-bold mb-2">Contraseña</label>
                    <input type="password" 
                           id="password" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                
                <button type="submit" 
                        id="login-btn"
                        class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-600 transition">
                    Iniciar Sesión
                </button>
            </form>
            
            <p class="mt-4 text-center text-gray-600 text-sm">
                Solo usuarios de la empresa DDU pueden acceder
            </p>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('login-form');
        const errorMessage = document.getElementById('error-message');
        const loginBtn = document.getElementById('login-btn');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            // Ocultar errores previos
            errorMessage.classList.add('hidden');
            loginBtn.disabled = true;
            loginBtn.textContent = 'Validando...';

            try {
                const response = await fetch('http://127.0.0.1:8000/api/auth/validate-user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password,
                        nombre_empresa: 'DDU'
                    })
                });

                const data = await response.json();

                if (data.success && data.belongs_to_company) {
                    // Usuario válido - Guardar en localStorage
                    localStorage.setItem('juntify_user', JSON.stringify(data.user));
                    localStorage.setItem('juntify_company', JSON.stringify(data.company));
                    localStorage.setItem('authenticated', 'true');
                    
                    // Redirigir al dashboard
                    window.location.href = 'dashboard.html';
                } else {
                    // Mostrar error
                    showError(data.message || 'No tienes acceso. Solo usuarios de DDU pueden ingresar.');
                }

            } catch (error) {
                console.error('Error:', error);
                showError('Error al conectar con el servidor de autenticación. Intenta nuevamente.');
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Iniciar Sesión';
            }
        });

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.classList.remove('hidden');
        }
    </script>
</body>
</html>
```

```html
<!-- dashboard.html -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard DDU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen p-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Dashboard DDU</h1>
                <button onclick="logout()" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    Cerrar Sesión
                </button>
            </div>
            
            <div id="user-info" class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded">
                    <p class="text-gray-600 text-sm">Nombre</p>
                    <p class="font-bold" id="user-name"></p>
                </div>
                <div class="bg-gray-50 p-4 rounded">
                    <p class="text-gray-600 text-sm">Email</p>
                    <p class="font-bold" id="user-email"></p>
                </div>
                <div class="bg-gray-50 p-4 rounded">
                    <p class="text-gray-600 text-sm">Empresa</p>
                    <p class="font-bold" id="company-name"></p>
                </div>
                <div class="bg-gray-50 p-4 rounded">
                    <p class="text-gray-600 text-sm">Rol</p>
                    <p class="font-bold" id="user-role"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Verificar autenticación
        const authenticated = localStorage.getItem('authenticated');
        const user = JSON.parse(localStorage.getItem('juntify_user') || '{}');
        const company = JSON.parse(localStorage.getItem('juntify_company') || '{}');

        if (!authenticated || !user.id || company.nombre?.toLowerCase() !== 'ddu') {
            window.location.href = 'login.html';
        }

        // Mostrar información del usuario
        document.getElementById('user-name').textContent = user.name || 'N/A';
        document.getElementById('user-email').textContent = user.email || 'N/A';
        document.getElementById('company-name').textContent = company.nombre || 'N/A';
        document.getElementById('user-role').textContent = company.rol_usuario || 'N/A';

        function logout() {
            localStorage.clear();
            window.location.href = 'login.html';
        }
    </script>
</body>
</html>
```

---

## Configuración de CORS (en Juntify)

Si el otro sistema está en un dominio diferente, necesitas configurar CORS:

```php
// config/cors.php (en Juntify)

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:3000', 'https://sistema-ddu.com'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

---

## Resumen del Flujo

1. **Usuario ingresa credenciales** en el sistema DDU
2. **Sistema DDU envía** credenciales a `POST /api/auth/validate-user` con `nombre_empresa: "DDU"`
3. **Juntify valida**:
   - ✅ Usuario existe
   - ✅ Contraseña correcta
   - ✅ Usuario pertenece a empresa DDU
4. **Si todo OK**: Juntify responde `success: true, belongs_to_company: true`
5. **Sistema DDU**: Crea sesión y redirige a dashboard
6. **Si falla**: Mostrar error y denegar acceso

**Importante:** Cambia `http://127.0.0.1:8000` por la URL real de tu servidor Juntify en producción.
