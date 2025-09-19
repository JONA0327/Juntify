<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recupera tu contraseña - Juntify</title>
  <style>
    body{font-family:Inter,Arial,sans-serif;background:#0a0e27;margin:0;padding:0;color:#e2e8f0}
    .container{max-width:600px;margin:0 auto;background:#1a1f3a;border:1px solid rgba(59,130,246,.3);border-radius:16px;overflow:hidden}
    .header{background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%);padding:24px;color:#fff;text-align:center}
    .header h1{margin:0;font-size:28px;letter-spacing:.5px;text-shadow:0 0 12px rgba(0,0,0,.2)}
    .content{padding:24px}
    .card{background:rgba(255,255,255,.04);border:1px solid rgba(59,130,246,.2);border-radius:12px;padding:20px}
    .title{font-size:20px;color:#fff;margin:0 0 10px}
    .text{color:#cbd5e1;line-height:1.6;margin:0 0 16px}
    .code{display:inline-block;background:#0a0e27;color:#fff;border:1px dashed rgba(59,130,246,.6);border-radius:10px;padding:12px 16px;font-size:24px;letter-spacing:6px;margin:12px 0}
    .footer{padding:16px 24px;color:#94a3b8;text-align:center;font-size:12px}
  </style>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:400,600&display=swap" rel="stylesheet">
  <!-- Nota: muchos clientes de correo no cargan fuentes externas. -->
  <meta name="color-scheme" content="dark light">
  <meta name="supported-color-schemes" content="dark light">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="format-detection" content="telephone=no,date=no,address=no,email=no,url=no">
  <meta name="referrer" content="no-referrer">
  <meta name="robots" content="noindex,nofollow">
  <meta name="x-robots-tag" content="noarchive,noimageindex">
  <meta name="og:title" content="Código de recuperación - Juntify">
  <meta name="og:site_name" content="Juntify">
  <meta name="og:type" content="website">
  <meta name="og:locale" content="es_ES">
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>JUNTIFY</h1>
    </div>
    <div class="content">
      <div class="card">
        <h2 class="title">Hola, parece que intentas recuperar la contraseña</h2>
        <p class="text">Ingresa el siguiente código para cambiar tu contraseña. Por seguridad, este código expira en 10 minutos.</p>
        <div class="code">{{ $code }}</div>
        <p class="text">Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña seguirá siendo la misma.</p>
      </div>
    </div>
    <div class="footer">
      © {{ date('Y') }} Juntify. Todos los derechos reservados.
    </div>
  </div>
</body>
</html>
