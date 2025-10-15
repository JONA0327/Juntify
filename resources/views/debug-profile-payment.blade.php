<!DOCTYPE html>
<html>
<head>
    <title>Debug Profile Payment</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test-button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .result { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .success { background: #e8f5e8; border: 1px solid #4caf50; }
        .error { background: #ffe8e8; border: 1px solid #f44336; }
        .info { background: #e3f2fd; border: 1px solid #2196f3; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Profile Payment Flow</h1>

        <div class="result" id="result" style="display: none;"></div>

        <h2>🧪 Test Exact Profile Flow</h2>
        <p>Este test replica exactamente lo que hace el profile cuando seleccionas un plan:</p>

        <button class="test-button" onclick="testProfileFlow(1)">Test Plan Básico (ID: 1)</button>
        <button class="test-button" onclick="testProfileFlow(2)">Test Plan Negocios (ID: 2)</button>
        <button class="test-button" onclick="testProfileFlow(3)">Test Plan Empresas (ID: 3)</button>

        <h2>📋 Configuration Debug</h2>
        <div id="config-debug">
            <p><strong>Current URL:</strong> <code id="current-url"></code></p>
            <p><strong>CSRF Token:</strong> <code id="csrf-token"></code></p>
            <p><strong>Request URL:</strong> <code id="request-url"></code></p>
        </div>
    </div>

    <script>
    // Mostrar información de configuración
    document.getElementById('current-url').textContent = window.location.href;
    document.getElementById('csrf-token').textContent = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    document.getElementById('request-url').textContent = `${window.location.origin}/subscription/create-preference`;

    function testProfileFlow(planId) {
        showResult('info', `🚀 Testing profile flow for plan ID: ${planId}...`);

        // Esto replica exactamente el código del profile
        const createPreferenceUrl = `${window.location.origin}/subscription/create-preference`;

        const requestData = {
            plan_id: planId
        };

        console.log('Request URL:', createPreferenceUrl);
        console.log('Request Data:', requestData);
        console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        fetch(createPreferenceUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestData)
        })
        .then(response => {
            console.log('Response Status:', response.status);
            console.log('Response Headers:', response.headers);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response Data:', data);

            if (data.success) {
                showResult('success', `
                    ✅ Preference created successfully!<br><br>
                    <strong>Preference ID:</strong> ${data.preference_id}<br>
                    <strong>Checkout URL (used by profile):</strong><br>
                    <code>${data.checkout_url}</code><br><br>
                    <strong>Init Point:</strong><br>
                    <code>${data.init_point}</code><br><br>
                    <strong>Sandbox Init Point:</strong><br>
                    <code>${data.sandbox_init_point}</code><br><br>
                    <strong>URL Analysis:</strong><br>
                    - Checkout URL Domain: <code>${new URL(data.checkout_url).hostname}</code><br>
                    - Is Sandbox: <strong>${data.checkout_url.includes('sandbox') ? 'YES ✅' : 'NO ❌'}</strong>
                `);

                // Mostrar botón para abrir el checkout
                const button = document.createElement('button');
                button.className = 'test-button';
                button.textContent = 'Open Checkout (Same as Profile)';
                button.onclick = () => window.open(data.checkout_url, '_blank');
                document.getElementById('result').appendChild(document.createElement('br'));
                document.getElementById('result').appendChild(button);

            } else {
                showResult('error', `❌ Error: ${data.error}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showResult('error', `❌ Request failed: ${error.message}`);
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
