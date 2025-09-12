<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Mis Reuniones - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <script>
        window.userRole = <?php echo json_encode($userRole, 15, 512) ?>;
        window.currentOrganizationId = <?php echo json_encode($organizationId, 15, 512) ?>;
    </script>

    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/js/app.js', 'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'
    ]); ?>
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        <?php echo $__env->make('partials.navbar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->make('partials.mobile-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?> <main class="w-full pt-20 md:pt-24 lg:pl-24 lg:mt-[130px]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <header class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6 pb-12 fade-in">
                    <div class="flex-1 space-y-6">
                        <div class="space-y-3">
                            <h1 class="text-3xl sm:text-4xl font-bold text-white tracking-tight">Reuniones</h1>
                            <p class="text-slate-400 text-lg">Gestiona y organiza todas tus reuniones</p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="relative flex-1 w-full">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="text" placeholder="Buscar en reuniones..." class="bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 block w-full text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50 transition-all duration-200 shadow-lg shadow-black/10">
                            </div>

                            <button class="bg-slate-800/50 backdrop-blur-custom text-slate-200 px-4 py-3 rounded-xl flex items-center justify-center sm:justify-start gap-3 hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 border border-slate-700/50 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span class="font-medium">Fecha</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col items-stretch lg:items-end gap-6 fade-in stagger-1 w-full lg:w-auto">
                        <div class="w-full lg:w-80 bg-gradient-to-br from-slate-800/50 to-slate-800/30 backdrop-blur-custom border border-slate-700/50 rounded-xl p-5 shadow-lg shadow-black/10">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-sm font-semibold text-slate-300">Análisis mensuales</p>
                                <span class="text-xs text-slate-400 bg-slate-700/50 px-2 py-1 rounded-full">45/100</span>
                            </div>
                            <div class="w-full bg-slate-700/50 rounded-full h-2.5 overflow-hidden">
                                <div class="progress-bar bg-gradient-to-r from-yellow-400 to-yellow-500 h-2.5 rounded-full shadow-lg shadow-yellow-400/25" style="width: 45%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">55 análisis restantes este mes</p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <button id="create-container-btn" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-5 py-3 bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl text-slate-200 font-medium hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                                <span>Nuevo Contenedor</span>
                            </button>

                            <button class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-xl hover:from-yellow-500 hover:to-yellow-600 transition-all duration-200 shadow-lg shadow-yellow-400/25 hover:shadow-yellow-400/40 transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Reuniones Pendientes</span>
                            </button>
                        </div>
                    </div>
                </header>

                <nav class="mb-6 overflow-x-auto">
                    <ul class="flex gap-3 whitespace-nowrap">
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="my-meetings">Mis reuniones</button>
                        </li>
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="shared-meetings">Reuniones compartidas</button>
                        </li>
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="containers">Contenedores</button>
                        </li>
                        <li>
                            <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="contacts">Contactos</button>
                        </li>
                    </ul>
                </nav>

                <div class="fade-in stagger-2" id="meetings-container">
                    <div id="my-meetings" class="hidden">
                        <?php if(isset($meetings)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                <?php $__empty_1 = true; $__currentLoopData = $meetings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php if (isset($component)) { $__componentOriginal0f4bc99ffed4313a925286a46c272232 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0f4bc99ffed4313a925286a46c272232 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.meeting-card','data' => ['id' => $m['id'],'meetingName' => $m['meeting_name'],'createdAt' => $m['created_at'],'audioFolder' => $m['audio_folder'] ?? '','transcriptFolder' => $m['transcript_folder'] ?? '']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('meeting-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($m['id']),'meeting-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($m['meeting_name']),'created-at' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($m['created_at']),'audio-folder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($m['audio_folder'] ?? ''),'transcript-folder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($m['transcript_folder'] ?? '')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0f4bc99ffed4313a925286a46c272232)): ?>
<?php $attributes = $__attributesOriginal0f4bc99ffed4313a925286a46c272232; ?>
<?php unset($__attributesOriginal0f4bc99ffed4313a925286a46c272232); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0f4bc99ffed4313a925286a46c272232)): ?>
<?php $component = $__componentOriginal0f4bc99ffed4313a925286a46c272232; ?>
<?php unset($__componentOriginal0f4bc99ffed4313a925286a46c272232); ?>
<?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="loading-card md:col-span-2 xl:col-span-3"><p>No tienes reuniones</p></div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            
                            <div class="loading-card">
                                <div class="loading-spinner"></div>
                                <p>Cargando reuniones...</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="shared-meetings" class="hidden">
                        <div class="loading-card"><p>No hay reuniones compartidas</p></div>
                    </div>

                    <div id="containers" class="hidden">
                        <div class="loading-card"><p>No tienes contenedores</p></div>
                    </div>
                    <div id="contacts" class="hidden">
                        <?php echo $__env->make('contacts.index', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    
    </body>
</html>
<?php /**PATH C:\Users\Admin\Desktop\Cerounocero\Juntify\resources\views/reuniones.blade.php ENDPATH**/ ?>