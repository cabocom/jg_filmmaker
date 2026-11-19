<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        Factory::flushMacros();

        parent::setUp();

        $this->withoutVite();

        Http::preventStrayRequests();
        Config::set('services.jg_agente.mock', true);
        Config::set('services.llm.api_key', null);
    }

    protected function tearDown(): void
    {
        Factory::flushMacros();

        parent::tearDown();
    }
}
