<?php

namespace Foodlz\LaravelAiSkills\Tests;

use Foodlz\LaravelAiSkills\SkillsServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            SkillsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai-skills.discovery.paths', [__DIR__.'/Fixtures']);
        $app['config']->set('ai-skills.discovery.namespaces', ['Foodlz\\LaravelAiSkills\\Tests\\Fixtures']);
        $app['config']->set('ai-skills.cache.enabled', false);
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
    }
}
