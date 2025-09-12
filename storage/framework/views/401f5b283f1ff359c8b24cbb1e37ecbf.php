

<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <title>Iniciar Sesión - Juntify</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

  <!-- Styles & Scripts -->
  <?php echo app('Illuminate\Foundation\Vite')([
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/css/auth/login.css',
    'resources/js/auth/login.js'
  ]); ?>
</head>
<body>
  <div class="particles" id="particles"></div>

  <div class="auth-container">
    <div class="auth-card">
      <div class="logo">
        <h1>Juntify</h1>
        <p>Bienvenido de vuelta</p>
      </div>

      
      <?php if($errors->has('login')): ?>
        <div class="error-message" style="display:block; margin-bottom:1rem;">
          <?php echo e($errors->first('login')); ?>

        </div>
      <?php endif; ?>

      <form id="loginForm" method="POST" action="<?php echo e(route('login')); ?>">
        <?php echo csrf_field(); ?>

        <div class="form-group">
          <label for="login" class="form-label">Usuario o Correo Electrónico</label>
          <input
            type="text"
            id="login"
            name="login"
            class="form-input"
            placeholder="Ingresa tu usuario o email"
            value="<?php echo e(old('login')); ?>"
            required
          >
          <div class="error-message" id="loginError"></div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Contraseña</label>
          <input
            type="password"
            id="password"
            name="password"
            class="form-input"
            placeholder="Ingresa tu contraseña"
            required
          >
          <div class="error-message" id="passwordError"></div>
          <div class="forgot-password">
            <a href="#">¿Olvidaste tu contraseña?</a>
          </div>
        </div>

        <button type="submit" class="btn-primary" id="submitBtn">
          Iniciar Sesión
        </button>
      </form>

      <div class="auth-links">
        <p style="color: #cbd5e1; margin-bottom: 1rem;">¿No tienes una cuenta?</p>
        <a href="<?php echo e(route('register')); ?>">Regístrate en Juntify</a>
      </div>
    </div>
  </div>
</body>
</html>
<?php /**PATH C:\Users\Admin\Desktop\Cerounocero\Juntify\resources\views/auth/login.blade.php ENDPATH**/ ?>