<!DOCTYPE html>
<html>
<head>
    <title>Guía de Tarjetas de Prueba - MercadoPago</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .card { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .test-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .card-number { font-family: monospace; font-weight: bold; font-size: 16px; }
        .success { background: #e8f5e8; border-color: #4caf50; }
        .decline { background: #ffe8e8; border-color: #f44336; }
        .pending { background: #fff3e0; border-color: #ff9800; }
        .test-button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .result { margin: 20px 0; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Guía de Tarjetas de Prueba - MercadoPago</h1>

        <div class="result" id="result" style="display: none;"></div>

        <h2>📋 Información del Entorno</h2>
        <div class="card">
            <p><strong>Entorno:</strong> <span style="color: green;">SANDBOX (Pruebas) ✅</span></p>
            <p><strong>Access Token:</strong> TEST-*** (Correcto para pruebas)</p>
            <p><strong>Moneda:</strong> MXN (Peso Mexicano)</p>
        </div>

        <h2>💳 Tarjetas de Prueba para México (MXN)</h2>
        <div class="test-cards">
            <div class="card success">
                <h3>✅ Visa - Pago Aprobado (USAR ESTA)</h3>
                <p class="card-number">4174 0005 1758 0553</p>
                <p><strong>CVV:</strong> 123</p>
                <p><strong>Vencimiento:</strong> 11/30</p>
                <p><strong>Nombre:</strong> APRO</p>
                <p><strong>Resultado:</strong> Pago aprobado</p>
                <p style="color: red; font-weight: bold;">⚠️ NO uses: 4075 5957 1648 3764 (No válida)</p>
            </div>

            <div class="card success">
                <h3>✅ Mastercard - Pago Aprobado (ALTERNATIVA)</h3>
                <p class="card-number">5031 7557 3453 0604</p>
                <p><strong>CVV:</strong> 123</p>
                <p><strong>Vencimiento:</strong> 11/30</p>
                <p><strong>Nombre:</strong> APRO</p>
                <p><strong>Resultado:</strong> Pago aprobado</p>
            </div>

            <div class="card decline">
                <h3>❌ Visa - Pago Rechazado</h3>
                <p class="card-number">4013 5406 8274 6260</p>
                <p><strong>CVV:</strong> 123</p>
                <p><strong>Vencimiento:</strong> 11/30</p>
                <p><strong>Nombre:</strong> OTHE</p>
                <p><strong>Resultado:</strong> Pago rechazado</p>
            </div>

            <div class="card pending">
                <h3>⏳ Mastercard - Pago Pendiente</h3>
                <p class="card-number">5031 7557 3453 0604</p>
                <p><strong>CVV:</strong> 123</p>
                <p><strong>Vencimiento:</strong> 11/30</p>
                <p><strong>Nombre:</strong> CONT</p>
                <p><strong>Resultado:</strong> Pago pendiente</p>
            </div>
        </div>

        <h2>🔧 Probar Integración</h2>
        <div class="card">
            <p>Usa estos botones para probar el flujo completo:</p>
            <button class="test-button" onclick="testPlan('basico')">Probar Plan Básico ($499)</button>
            <button class="test-button" onclick="testPlan('negocios')">Probar Plan Negocios ($999)</button>
            <button class="test-button" onclick="testPlan('empresas')">Probar Plan Empresas ($2999)</button>
        </div>

        <h2>⚠️ Notas Importantes</h2>
        <div class="card">
            <ul>
                <li><strong>Usar EXACTAMENTE</strong> los datos mostrados arriba</li>
                <li>El nombre del titular debe ser <strong>APRO</strong> para pagos aprobados</li>
                <li>La tarjeta que usaste (4174 0005 1758 0553) es correcta, pero verifica el nombre</li>
                <li>Si dice "No es posible continuar", verifica que estés en el sandbox</li>
                <li>El CVV siempre debe ser <strong>123</strong> para tarjetas de prueba</li>
                <li>El vencimiento puede ser cualquier fecha futura, usa <strong>11/30</strong></li>
            </ul>
        </div>

        <h2>🐛 Errores Conocidos del Sandbox</h2>
        <div class="card">
            <p><strong>Si ves estos errores en la consola, son NORMALES:</strong></p>
            <ul>
                <li><code>404 /jms/lgz/background/session/...</code> - Error interno de MP, no afecta el pago</li>
                <li><code>Cannot read properties of null</code> - Error JavaScript interno de MP</li>
                <li><code>Failed to load resource</code> - Recursos internos de sandbox</li>
            </ul>
            <p><strong>✅ Estos errores NO impiden realizar el pago de prueba.</strong></p>
            <p><strong>🎯 Si el formulario de pago aparece, puedes continuar normalmente.</strong></p>
        </div>
    </div>

    <script>
    function testPlan(planCode) {
        const planIds = {
            'basico': 1,
            'negocios': 2,
            'empresas': 3
        };

        const planId = planIds[planCode];
        if (!planId) return;

        showResult('info', 'Creando preferencia de pago...');

        fetch('/subscription/create-preference', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                plan_id: planId
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);

            if (data.success) {
                showResult('success', `
                    ✅ Preferencia creada exitosamente!<br>
                    <strong>Preference ID:</strong> ${data.preference_id}<br>
                    <strong>Sandbox URL:</strong> <a href="${data.sandbox_init_point}" target="_blank">Abrir checkout de prueba</a><br>
                    <strong>Production URL:</strong> <a href="${data.init_point}" target="_blank">Abrir checkout real</a>
                `);

                // Abrir automáticamente el checkout de sandbox
                window.open(data.sandbox_init_point, '_blank');
            } else {
                showResult('error', `❌ Error: ${data.error}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showResult('error', `❌ Error de conexión: ${error.message}`);
        });
    }

    function showResult(type, message) {
        const resultDiv = document.getElementById('result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'result ' + type;
        resultDiv.innerHTML = message;
    }
    </script>
</body>
</html>
