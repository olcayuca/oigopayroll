<?php

namespace Tests;

use App\Enums\Portal;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Host-agnostic routes (auth, settings) are exercised on the client panel by default.
        $this->onPortal(Portal::Panel);
    }

    /**
     * Send subsequent requests to host-agnostic routes on the given portal.
     */
    protected function onPortal(Portal $portal): static
    {
        URL::forceRootUrl('https://'.$portal->domain());

        return $this;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
