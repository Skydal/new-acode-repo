<?php
namespace VMP\Modules\Vendor;

defined('ABSPATH') || exit;

use VMP\Providers\ServiceProvider;

/**
 * Class VendorServiceProvider
 *
 * Description of administrative platform component VendorServiceProvider.
 *
 * @package vendor-marketplace
 */
class VendorServiceProvider extends ServiceProvider
{
    /**
     * Register functionality helper.
     *
     * @return void Output payload.
     */
    public function register(): void
    {
        $this->container->singleton(VendorModule::class, fn (): VendorModule => new VendorModule($this->container));
    }
}
