<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Guía de API - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/organization.css',
        'resources/js/organization.js',
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'
    ]); ?>
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">
    <div class="flex">
        <?php echo $__env->make('partials.navbar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->make('partials.mobile-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 pb-12">
                <h1 class="text-3xl font-bold mb-8 text-white">API Documentation</h1>
                
                <!-- Access Token Section -->
                <section class="mb-10">
                    <h2 class="text-2xl font-semibold mb-4 text-white">Access Token</h2>
                    <p class="mb-4 text-slate-300">To interact with the API you must first generate a personal access token. These endpoints require a user session (login) but no existing token.</p>
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-medium mb-3 text-white">Generate a key</h3>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl -X POST <?php echo e(url('/api/user/api-key')); ?> -b cookies.txt -c cookies.txt</code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{ "api_key": "YOUR_TOKEN" }</code></pre>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-medium mb-3 text-white">Retrieve current key</h3>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl <?php echo e(url('/api/user/api-key')); ?> -b cookies.txt</code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{ "api_key": "YOUR_TOKEN" }</code></pre>
                    </div>

                    <div class="bg-blue-900/20 border border-blue-600/30 rounded-lg p-4 mb-6">
                        <p class="text-blue-300">All subsequent requests must send the header:</p>
                        <pre class="bg-slate-800/50 p-2 rounded mt-2 border border-slate-700"><code class="text-cyan-300">Authorization: Bearer YOUR_TOKEN</code></pre>
                    </div>
                </section>

                <!-- Endpoints Section -->
                <section class="mb-10">
                    <h2 class="text-2xl font-semibold mb-6 text-white">Endpoints</h2>

                    <!-- List organizations -->
                    <div class="mb-8 bg-slate-900/50 p-6 rounded-lg border border-slate-700">
                        <h3 class="text-lg font-medium mb-3 text-white">List organizations</h3>
                        <p class="text-slate-400 mb-3"><code class="bg-slate-800 px-2 py-1 rounded text-cyan-300">GET /api/organizations</code></p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl -H "Authorization: Bearer YOUR_TOKEN" <?php echo e(url('/api/organizations')); ?></code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{
  "organizations": [
    {
      "id": 1,
      "nombre_organizacion": "Org 1",
      "descripcion": "..."
    }
  ]
}</code></pre>
                    </div>

                    <!-- Organization details -->
                    <div class="mb-8 bg-slate-900/50 p-6 rounded-lg border border-slate-700">
                        <h3 class="text-lg font-medium mb-3 text-white">Organization details</h3>
                        <p class="text-slate-400 mb-3"><code class="bg-slate-800 px-2 py-1 rounded text-cyan-300">GET /api/organizations/{id}</code></p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl -H "Authorization: Bearer YOUR_TOKEN" <?php echo e(url('/api/organizations/1')); ?></code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{
  "organization": {
    "id": 1,
    "nombre_organizacion": "Org 1",
    "groups": []
  }
}</code></pre>
                    </div>

                    <!-- Group containers -->
                    <div class="mb-8 bg-slate-900/50 p-6 rounded-lg border border-slate-700">
                        <h3 class="text-lg font-medium mb-3 text-white">Group containers</h3>
                        <p class="text-slate-400 mb-3"><code class="bg-slate-800 px-2 py-1 rounded text-cyan-300">GET /api/groups/{group}/containers</code></p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl -H "Authorization: Bearer YOUR_TOKEN" <?php echo e(url('/api/groups/1/containers')); ?></code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{
  "containers": [
    {
      "id": 3,
      "name": "My Container",
      "description": "...",
      "created_at": "01/09/2025 12:00",
      "meetings_count": 1,
      "is_company": true,
      "group_name": "Marketing"
    }
  ]
}</code></pre>
                    </div>

                    <!-- Content containers -->
                    <div class="mb-8 bg-slate-900/50 p-6 rounded-lg border border-slate-700">
                        <h3 class="text-lg font-medium mb-3 text-white">Content containers</h3>
                        <p class="text-slate-400 mb-3"><code class="bg-slate-800 px-2 py-1 rounded text-cyan-300">GET /api/content-containers</code></p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl -H "Authorization: Bearer YOUR_TOKEN" <?php echo e(url('/api/content-containers')); ?></code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{
  "success": true,
  "containers": [
    {
      "id": 7,
      "name": "My Container",
      "description": "...",
      "created_at": "01/09/2025 12:00",
      "meetings_count": 2,
      "is_company": false,
      "group_name": null
    }
  ]
}</code></pre>
                    </div>

                    <!-- Meetings in content container -->
                    <div class="mb-8 bg-slate-900/50 p-6 rounded-lg border border-slate-700">
                        <h3 class="text-lg font-medium mb-3 text-white">Meetings in a content container</h3>
                        <p class="text-slate-400 mb-3"><code class="bg-slate-800 px-2 py-1 rounded text-cyan-300">GET /api/content-containers/{id}/meetings</code></p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl -H "Authorization: Bearer YOUR_TOKEN" <?php echo e(url('/api/content-containers/7/meetings')); ?></code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{
  "success": true,
  "container": {
    "id": 7,
    "name": "My Container",
    "description": "...",
    "is_company": false,
    "group_name": null
  },
  "meetings": [
    {
      "id": 25,
      "meeting_name": "Weekly Sync",
      "created_at": "05/09/2025 10:00",
      "audio_drive_id": "abc123",
      "transcript_drive_id": "def456",
      "audio_folder": "2025-09-05",
      "transcript_folder": "Transcripts",
      "has_transcript": true
    }
  ]
}</code></pre>
                    </div>

                    <!-- List meetings -->
                    <div class="mb-8 bg-slate-900/50 p-6 rounded-lg border border-slate-700">
                        <h3 class="text-lg font-medium mb-3 text-white">List meetings</h3>
                        <p class="text-slate-400 mb-3"><code class="bg-slate-800 px-2 py-1 rounded text-cyan-300">GET /api/meetings</code></p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-4 border border-slate-700"><code class="text-green-400">curl -H "Authorization: Bearer YOUR_TOKEN" <?php echo e(url('/api/meetings')); ?></code></pre>
                        <p class="font-medium mb-2 text-slate-300">Response</p>
                        <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm border border-slate-700"><code class="text-yellow-300">{
  "success": true,
  "meetings": [
    {
      "id": 25,
      "meeting_name": "Free Meeting",
      "created_at": "05/09/2025 10:00",
      "audio_folder": "folderA",
      "transcript_folder": "Base de datos",
      "is_legacy": false,
      "source": "meetings"
    }
  ]
}</code></pre>
                    </div>
                </section>

                <!-- Errors Section -->
                <section class="mb-10">
                    <h2 class="text-2xl font-semibold mb-4 text-white">Errors</h2>
                    <div class="bg-red-900/20 border border-red-600/30 rounded-lg p-4">
                        <ul class="space-y-2 text-red-300">
                        <li><strong>403 Forbidden</strong> – The access token is missing, invalid, or you lack permissions.</li>
                            <li><strong>404 Not Found</strong> – The requested resource does not exist.</li>
                        </ul>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>

<?php /**PATH C:\laragon\www\Juntify\resources\views/organization/api-guide.blade.php ENDPATH**/ ?>