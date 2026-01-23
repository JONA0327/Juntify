<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('profile.edit.page_title') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/js/index.js',
        'resources/css/profile.css',
        'resources/js/profile.js'
    ])
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <!-- Navbar principal -->
    @include('partials.navbar')

    <!-- Barra de navegación móvil -->


    <!-- Botón para abrir sidebar en móvil -->
    <button class="mobile-sidebar-btn mobile-menu-btn" onclick="toggleSidebar()" aria-label="{{ __('common.open_menu') }}">
        <svg class="icon-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 01-1.414-1.414L10.586 10 5.879 5.707a1 1 0 011.414-1.414l4.001 4a1 1 0 010 1.414l-4.001 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
    </button>

    <div class="app-container">
        <!-- Sidebar -->
        @include('partials.profile._sidebar')

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <h1 class="page-title">{{ __('profile.edit.title') }}</h1>
                <p class="page-subtitle">{{ __('profile.edit.subtitle') }}</p>
            </div>

            <!-- Edit Profile Form -->
            <div class="content-body">
                @if (session('status') === 'profile-updated')
                    <div class="alert alert-success">
                        <svg class="alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('profile.edit.updated') }}
                    </div>
                @endif
                @if (session('status') === 'language-updated')
                    <div class="alert alert-success">
                        <svg class="alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('profile.settings.updated') }}
                    </div>
                @endif

                <div class="profile-card">
                    <form method="POST" action="{{ route('profile.update') }}" class="profile-form">
                        @csrf
                        @method('PATCH')

                        <!-- Nombre -->
                        <div class="form-group">
                            <label for="name" class="form-label">{{ __('profile.edit.full_name') }}</label>
                            <input type="text"
                                   class="form-control @error('name') error @enderror"
                                   id="name"
                                   name="name"
                                   value="{{ old('name', $user->name) }}"
                                   required>
                            @error('name')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label for="email" class="form-label">{{ __('profile.edit.email') }}</label>
                            <input type="email"
                                   class="form-control @error('email') error @enderror"
                                   id="email"
                                   name="email"
                                   value="{{ old('email', $user->email) }}"
                                   required>
                            @error('email')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Username -->
                        <div class="form-group">
                            <label for="username" class="form-label">{{ __('profile.edit.username') }}</label>
                            <input type="text"
                                   class="form-control @error('username') error @enderror"
                                   id="username"
                                   name="username"
                                   value="{{ old('username', $user->username) }}"
                                   required>
                            @error('username')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {{ __('common.save_changes') }}
                            </button>
                            <a href="{{ route('profile.show') }}" class="btn btn-secondary">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                {{ __('common.cancel') }}
                            </a>
                        </div>
                    </form>

                    <!-- Sección de eliminación de cuenta -->
                    <div class="danger-zone">
                        <h3 class="danger-title">{{ __('profile.danger_zone.title') }}</h3>
                        <p class="danger-description">{{ __('profile.danger_zone.description') }}</p>

                        <button type="button" class="btn btn-danger" onclick="showDeleteModal()">
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            {{ __('profile.danger_zone.delete_account') }}
                        </button>
                    </div>
                </div>

                <div class="profile-card">
                    <h2 class="card-title">{{ __('profile.settings.title') }}</h2>
                    <form method="POST" action="{{ route('profile.language') }}" class="profile-form" id="language-form">
                        @csrf
                        @method('PATCH')

                        <div class="form-group">
                            <label for="locale" class="form-label">{{ __('profile.settings.language_label') }}</label>
                            <select id="locale" name="locale" class="form-control" data-language-select>
                                <option value="es" @selected((old('locale') ?? $user->locale ?? app()->getLocale()) === 'es')>
                                    {{ __('common.spanish') }}
                                </option>
                                <option value="en" @selected((old('locale') ?? $user->locale ?? app()->getLocale()) === 'en')>
                                    {{ __('common.english') }}
                                </option>
                            </select>
                            <p class="form-hint">{{ __('profile.settings.language_help') }}</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {{ __('common.save') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="profile-card voice-enrollment-card" id="voice-enrollment-card" data-enroll-url="{{ route('profile.voice.enroll') }}">
                    <h2 class="card-title">{{ __('profile.voice.title') }}</h2>
                    <p class="card-subtitle">{{ __('profile.voice.subtitle') }}</p>

                    <div class="voice-enrollment-text">
                        {{ __('profile.voice.sample_text') }}
                    </div>

                    <div class="voice-enrollment-controls">
                        <button type="button" class="btn btn-primary" id="voice-record-start">
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a4.5 4.5 0 004.5-4.5v-3a4.5 4.5 0 10-9 0v3a4.5 4.5 0 004.5 4.5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 11.25v2.25a7.5 7.5 0 01-15 0v-2.25" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25v1.5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 22.5h7.5" />
                            </svg>
                            {{ __('profile.voice.record') }}
                        </button>
                        <button type="button" class="btn btn-secondary" id="voice-record-stop" disabled>
                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h12v12H6z" />
                            </svg>
                            {{ __('profile.voice.stop') }}
                        </button>
                        <span class="voice-recording-indicator" id="voice-recording-indicator">{{ __('profile.voice.recording') }}</span>
                        <span class="voice-recording-timer" id="voice-recording-timer">00:00</span>
                    </div>

                    <p class="voice-enrollment-status" id="voice-enrollment-status">{{ __('profile.voice.status_ready') }}</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de confirmación para eliminar cuenta -->
    <div class="modal-overlay" id="deleteModal" style="display: none;" onclick="hideDeleteModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title">{{ __('profile.danger_zone.confirm_title') }}</h3>
                <button type="button" class="modal-close" onclick="hideDeleteModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p>{{ __('profile.danger_zone.confirm_body') }}</p>

                <form method="POST" action="{{ route('profile.destroy') }}" id="deleteAccountForm">
                    @csrf
                    @method('DELETE')

                    <div class="form-group">
                        <label for="password" class="form-label">{{ __('profile.danger_zone.confirm_password') }}</label>
                        <input type="password"
                               class="form-control @error('password', 'userDeletion') error @enderror"
                               id="password"
                               name="password"
                               required>
                        @error('password', 'userDeletion')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">{{ __('common.cancel') }}</button>
                <button type="submit" form="deleteAccountForm" class="btn btn-danger">{{ __('profile.danger_zone.delete_account') }}</button>
            </div>
        </div>
    </div>

    <script>
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>

    <script>
        window.profileTranslations = @json([
            'day_singular' => __('common.day_singular'),
            'day_plural' => __('common.day_plural'),
            'drive_locked_message' => __('profile.drive.locked_message'),
            'main_folder_required' => __('profile.drive.main_folder_required'),
            'main_folder_change_confirm' => __('profile.drive.main_folder_change_confirm'),
            'main_folder_set' => __('profile.drive.main_folder_set'),
            'main_folder_custom_name' => __('profile.drive.main_folder_custom_name'),
            'main_folder_id_label' => __('profile.drive.main_folder_id_label'),
            'main_folder_error' => __('profile.drive.main_folder_error'),
            'voice.enroll_route_missing' => __('profile.voice.enroll_route_missing'),
            'voice.status_too_short' => __('profile.voice.status_too_short'),
            'voice.too_short' => __('profile.voice.too_short'),
            'voice.status_processing' => __('profile.voice.status_processing'),
            'voice.status_registered' => __('profile.voice.status_registered'),
            'voice.registered' => __('profile.voice.registered'),
            'voice.register_error' => __('profile.voice.register_error'),
            'voice.status_message' => __('profile.voice.status_message'),
            'voice.unsupported_browser' => __('profile.voice.unsupported_browser'),
            'voice.status_microphone_denied' => __('profile.voice.status_microphone_denied'),
            'voice.microphone_denied' => __('profile.voice.microphone_denied'),
            'voice.status_recording' => __('profile.voice.status_recording'),
            'voice.status_preparing' => __('profile.voice.status_preparing'),
            'subfolder.delete_confirm' => __('profile.subfolder.delete_confirm'),
            'subfolder.deleted' => __('profile.subfolder.deleted'),
            'subfolder.delete_error' => __('profile.subfolder.delete_error'),
            'notifications.empty' => __('profile.notifications.empty'),
            'session.expired' => __('profile.session.expired'),
            'plan.period.month' => __('profile.plan.period.month'),
            'plan.period.year' => __('profile.plan.period.year'),
            'plan.free' => __('profile.plan.free'),
            'plan.discount' => __('profile.plan.discount'),
            'plan.free_months' => __('profile.plan.free_months'),
            'plan.previous_price' => __('profile.plan.previous_price'),
            'plan.preference_error' => __('profile.plan.preference_error'),
            'plan.unknown_error' => __('profile.plan.unknown_error'),
            'plan.request_error' => __('profile.plan.request_error'),
            'account.delete_mismatch' => __('profile.account.delete_mismatch'),
        ]);
    </script>

    <!-- Global vars and functions -->
    @include('partials.global-vars')

</body>
</html>
