<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use App\Auth\BcryptjsUserProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register bcryptjs user provider to support both $2y$ and $2b$ bcrypt hashes
        Auth::provider('bcryptjs', function ($app, array $config) {
            return new BcryptjsUserProvider($app['hash'], $config['model']);
        });

        // Custom Blade directive for CORS-compatible font links
        Blade::directive('corsFont', function ($expression) {
            return "<?php echo '<link rel=\"stylesheet\" href=\"' . $expression . '\" crossorigin=\"anonymous\">'; ?>";
        });
    }
}
