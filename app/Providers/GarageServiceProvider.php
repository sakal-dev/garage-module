<?php

namespace Modules\Garage\Providers;

use App\Services\Entitlements\FeatureRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Modules\Garage\Http\Middleware\DashboardMiddleware;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GarageServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Garage';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'garage';

    /**
     * Bootstrap the module and register its polymorphic morph aliases.
     */
    public function boot(): void
    {
        $this->registerEntitlementFeatures();
        parent::boot();

        Relation::morphMap([
            'garage_vehicle' => \Modules\Garage\Models\Vehicle::class,
            'garage_vehicle_size' => \Modules\Garage\Models\VehicleSize::class,
            'garage_service_job' => \Modules\Garage\Models\ServiceJob::class,
            'garage_service_job_line' => \Modules\Garage\Models\ServiceJobLine::class,
            'garage_service_job_media' => \Modules\Garage\Models\ServiceJobMedia::class,
        ]);

        // Register the Garage sidebar menu on dashboard requests.
        $this->app->make(Router::class)
            ->pushMiddlewareToGroup('dashboard', DashboardMiddleware::class);
    }

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }

    /**
     * LARA-227A — Garage registers the feature IT OWNS.
     *
     * Core holds no feature list. A module that is not installed never boots,
     * so it never registers, so no route pattern claims its pages and nothing
     * can gate on its key — module independence made structural rather than
     * conventional. The key is BARE because this capability is not owned by
     * the product that happens to sell it (the LARA-227A naming rule).
     */
    protected function registerEntitlementFeatures(): void
    {
        FeatureRegistry::register(
            key: 'garage',
            module: 'Garage',
            label: __('Garage'),
            floor: 'Add-on',
            policy: FeatureRegistry::POLICY_HIDE,
            routes: ['dashboard.garage.*'],
        );
    }

}
