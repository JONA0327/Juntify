<?php

namespace App\Console\Commands;

use App\Services\UserPlans\UserPlanService;
use Illuminate\Console\Command;

class ExpireUserPlans extends Command
{
    protected $signature = 'plans:expire';

    protected $description = 'Marca las suscripciones vencidas y degrada usuarios al plan gratuito cuando corresponde.';

    public function handle(UserPlanService $service): int
    {
        $processed = $service->downgradeExpiredPlans();

        $this->info(sprintf('Se procesaron %d suscripciones.', $processed));

        return self::SUCCESS;
    }
}
