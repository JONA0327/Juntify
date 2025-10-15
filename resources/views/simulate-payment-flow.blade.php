<!DOCTYPE html>
<html>
<head>
    <title>Simulador de Flujo de Pago</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test-button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .success-button { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .result { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .success { background: #e8f5e8; border: 1px solid #4caf50; }
        .error { background: #ffe8e8; border: 1px solid #f44336; }
        .info { background: #e3f2fd; border: 1px solid #2196f3; }
        .db-record { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Simulador de Flujo de Pago Completo</h1>

        <div class="result" id="result" style="display: none;"></div>

        <h2>📊 Último registro en BD</h2>
        <div class="db-record">
            <p><strong>External Reference:</strong> plan_basico_user_00aefd1b-c0c1-4245-9d76-16cb888d18cc_1760564102</p>
            <p><strong>Status:</strong> <span style="color: orange;">pending</span></p>
            <p><strong>Amount:</strong> $499.00 MXN</p>
            <p><strong>Description:</strong> Plan Plan Basic - Juntify</p>
        </div>

        <h2>🔄 Simular Flujos de Pago</h2>
        <p>Como el pago se registra en BD pero no completa el flujo, vamos a simular las diferentes respuestas:</p>

        <button class="success-button" onclick="simulateSuccess()">✅ Simular Pago Exitoso</button>
        <button class="test-button" onclick="simulateFailure()">❌ Simular Pago Fallido</button>
        <button class="test-button" onclick="simulatePending()">⏳ Simular Pago Pendiente</button>

        <h2>🔍 URLs de Retorno Configuradas</h2>
        <div class="info">
            <p><strong>Success:</strong> <code>{{ config('app.url') }}/payment/success</code></p>
            <p><strong>Failure:</strong> <code>{{ config('app.url') }}/payment/failure</code></p>
            <p><strong>Pending:</strong> <code>{{ config('app.url') }}/payment/pending</code></p>
        </div>

        <h2>🎯 Soluciones Recomendadas</h2>
        <div class="info">
            <h3>1. Problema del Sandbox</h3>
            <p>El sandbox de MercadoPago a veces no completa el flujo correctamente. Esto es normal.</p>

            <h3>2. Tarjetas Alternativas</h3>
            <ul>
                <li><strong>Mastercard:</strong> 5031 7557 3453 0604 (APRO, 123, 11/25)</li>
                <li><strong>Visa Alt:</strong> 4009 1753 3280 7176 (APRO, 123, 11/25)</li>
            </ul>

            <h3>3. Probar en Incógnito</h3>
            <p>A veces el cache del navegador interfiere con el sandbox.</p>
        </div>
    </div>

    <script>
    function simulateSuccess() {
        showResult('success', '✅ Simulando pago exitoso...');

        // Simular la redirección que haría MercadoPago
        const successUrl = '{{ config("app.url") }}/payment/success?external_reference=plan_basico_user_00aefd1b-c0c1-4245-9d76-16cb888d18cc_1760564102&payment_id=123456789&status=approved';

        setTimeout(() => {
            showResult('success', `
                ✅ Pago simulado como exitoso<br>
                <strong>Redirigiendo a:</strong><br>
                <code>${successUrl}</code><br><br>
                <button class="success-button" onclick="window.open('${successUrl}', '_blank')">Abrir página de éxito</button>
            `);
        }, 1000);
    }

    function simulateFailure() {
        showResult('error', '❌ Simulando pago fallido...');

        const failureUrl = '{{ config("app.url") }}/payment/failure?external_reference=plan_basico_user_00aefd1b-c0c1-4245-9d76-16cb888d18cc_1760564102&status=rejected';

        setTimeout(() => {
            showResult('error', `
                ❌ Pago simulado como fallido<br>
                <strong>Redirigiendo a:</strong><br>
                <code>${failureUrl}</code><br><br>
                <button class="test-button" onclick="window.open('${failureUrl}', '_blank')">Abrir página de error</button>
            `);
        }, 1000);
    }

    function simulatePending() {
        showResult('info', '⏳ Simulando pago pendiente...');

        const pendingUrl = '{{ config("app.url") }}/payment/pending?external_reference=plan_basico_user_00aefd1b-c0c1-4245-9d76-16cb888d18cc_1760564102&status=pending';

        setTimeout(() => {
            showResult('info', `
                ⏳ Pago simulado como pendiente<br>
                <strong>Redirigiendo a:</strong><br>
                <code>${pendingUrl}</code><br><br>
                <button class="test-button" onclick="window.open('${pendingUrl}', '_blank')">Abrir página pendiente</button>
            `);
        }, 1000);
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
