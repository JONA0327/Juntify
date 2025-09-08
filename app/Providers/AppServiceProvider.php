<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

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
        // Custom Blade directive for CORS-compatible font links
        Blade::directive('corsFont', function ($expression) {
            return "<?php echo '<link rel=\"stylesheet\" href=\"' . $expression . '\" crossorigin=\"anonymous\">'; ?>";
        });
    }
}
