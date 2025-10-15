<!DOCTYPE html>
<html>
<head>
    <title>Test Create Preference</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <h1>Test Create Preference</h1>
    <button onclick="testCreatePreference()">Test Plan Básico</button>
    <div id="result"></div>

    <script>
    function testCreatePreference() {
        fetch('/subscription/create-preference', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                plan_id: 1  // Plan básico
            })
        })
        .then(response => {
            console.log('Status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response:', data);
            document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';

            if (data.success) {
                alert('✅ Preferencia creada! Preference ID: ' + data.preference_id);
            } else {
                alert('❌ Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
        });
    }
    </script>
</body>
</html>
