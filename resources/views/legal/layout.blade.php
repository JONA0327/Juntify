<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Información Legal - Juntify')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #111827 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .legal-wrapper {
            max-width: 960px;
            margin: 0 auto;
            padding: 4rem 1.5rem 6rem;
        }
        .legal-card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(12px);
        }
        .legal-card h1 {
            font-size: 2.25rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 1rem;
        }
        .legal-card h2 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            color: #bfdbfe;
        }
        .legal-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: #60a5fa;
        }
        .legal-card p, .legal-card li {
            line-height: 1.7;
            color: #cbd5f5;
        }
        .legal-card ul {
            list-style: disc;
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .legal-card ol {
            list-style: decimal;
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .legal-card a {
            color: #93c5fd;
            text-decoration: underline;
        }
        .legal-card a:hover {
            color: #bfdbfe;
        }
        .legal-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: #93c5fd;
        }
        .back-link svg {
            width: 1.1rem;
            height: 1.1rem;
        }
        .legal-footer {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            font-size: 0.85rem;
            color: #94a3b8;
        }
        @media (max-width: 768px) {
            .legal-card {
                padding: 2rem;
                border-radius: 18px;
            }
            .legal-card h1 {
                font-size: 1.9rem;
            }
            .legal-card h2 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="legal-wrapper">
        <a href="{{ url('/') }}" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Volver a Juntify
        </a>
        <div class="legal-card">
            @yield('content')
            <div class="legal-footer">
                Última actualización: {{ now()->format('d/m/Y') }}. Para consultas sobre este documento escribe a <a href="mailto:legal@juntify.com">legal@juntify.com</a>.
            </div>
        </div>
    </div>
</body>
</html>
