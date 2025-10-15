<!DOCTYPE html>
<html>
<head>
    <title>🧪 Simulador de Pagos - Juntify</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 2rem; }
        .header p { margin: 10px 0 0 0; opacity: 0.9; }
        .content { padding: 30px; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert strong { color: #d63031; }
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .plan-card { border: 2px solid #e1e8ed; border-radius: 12px; padding: 25px; transition: all 0.3s ease; background: #fafafa; }
        .plan-card:hover { border-color: #667eea; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1); }
        .plan-card.popular { border-color: #667eea; background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%); }
        .plan-badge { background: #667eea; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block; margin-bottom: 15px; }
        .plan-name { font-size: 1.4rem; font-weight: bold; color: #2d3436; margin-bottom: 10px; }
        .plan-price { font-size: 2rem; font-weight: bold; color: #667eea; margin-bottom: 15px; }
        .plan-price.free { color: #00b894; }
        .plan-description { color: #636e72; margin-bottom: 20px; line-height: 1.4; }
        .simulate-btn { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.3s ease; }
        .simulate-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3); }
        .simulate-btn:active { transform: translateY(0); }
        .real-btn { width: 100%; background: #00b894; color: white; border: none; padding: 15px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; }
        .real-btn:hover { background: #00a085; }
        .features { list-style: none; padding: 0; margin: 0; }
        .features li { padding: 8px 0; color: #2d3436; }
        .features li:before { content: "✓ "; color: #00b894; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Simulador de Pagos</h1>
            <p>Prueba el flujo completo sin depender del sandbox problemático de MercadoPago</p>
        </div>

        <div class="content">
            <div class="alert">
                <strong>🚨 Problema del Sandbox:</strong> El sandbox de MercadoPago está presentando problemas técnicos que impiden completar el flujo de pago, aunque tu integración está funcionando correctamente (como confirma el registro en la base de datos). <br><br>
                <strong>💡 Solución:</strong> Esta herramienta te permite simular un pago exitoso para probar el flujo completo de tu aplicación.
            </div>

            <h2>📋 Selecciona un Plan para Simular</h2>

            <div class="plans-grid">
                @foreach($plans as $plan)
                @php
                    $isFreePlan = (float) $plan->price === 0.0;
                    $currencySymbol = $plan->currency === 'MXN' ? '$' : $plan->currency;
                @endphp

                <div class="plan-card @if($plan->code === 'basico') popular @endif">
                    @if($plan->code === 'basico')
                    <div class="plan-badge">Más Popular</div>
                    @endif

                    <div class="plan-name">{{ $plan->name }}</div>

                    <div class="plan-price @if($isFreePlan) free @endif">
                        @if($isFreePlan)
                            Gratis
                        @else
                            {{ $currencySymbol }}{{ number_format($plan->price, 0, ',', '.') }}
                            <small style="font-size: 0.6em; color: #636e72;">/mes</small>
                        @endif
                    </div>

                    <div class="plan-description">{{ $plan->description }}</div>

                    @if(count($plan->features ?? []) > 0)
                    <ul class="features">
                        @foreach(array_slice($plan->features, 0, 4) as $feature)
                        <li>{{ $feature }}</li>
                        @endforeach
                        @if(count($plan->features) > 4)
                        <li style="color: #636e72; font-style: italic;">Y {{ count($plan->features) - 4 }} características más...</li>
                        @endif
                    </ul>
                    @endif

                    @if(!$isFreePlan)
                    <form method="POST" action="{{ route('payment-test.simulate') }}" style="margin-top: 20px;">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <button type="submit" class="simulate-btn">
                            🎯 Simular Pago Exitoso
                        </button>
                    </form>

                    <button class="real-btn" onclick="testRealPayment({{ $plan->id }})">
                        💳 Intentar Pago Real (Sandbox)
                    </button>
                    @else
                    <div style="text-align: center; padding: 15px; background: #dff0d8; border-radius: 8px; color: #3c763d;">
                        ✅ Plan Gratuito - No requiere pago
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #667eea;">
                <h3 style="margin-top: 0; color: #667eea;">ℹ️ Cómo funciona</h3>
                <ul style="color: #2d3436; line-height: 1.6;">
                    <li><strong>Simular Pago:</strong> Crea un registro de pago exitoso en la BD y te lleva al flujo de confirmación</li>
                    <li><strong>Pago Real:</strong> Te lleva al checkout real de MercadoPago (puede tener problemas del sandbox)</li>
                    <li><strong>Producción:</strong> En producción, solo usarás el flujo real con credenciales de producción</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    function testRealPayment(planId) {
        // Crear preferencia real como lo hace el perfil
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Abrir el checkout real de MercadoPago
                window.location.href = data.checkout_url;
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al crear la preferencia: ' + error.message);
        });
    }
    </script>
</body>
</html>
