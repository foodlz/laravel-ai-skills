<?php

namespace Foodlz\LaravelAiSkills;

use Foodlz\LaravelAiSkills\Console\Commands\MakeSkillCommand;
use Foodlz\LaravelAiSkills\Console\Commands\SkillClearCommand;
use Foodlz\LaravelAiSkills\Console\Commands\SkillListCommand;
use Foodlz\LaravelAiSkills\Support\SkillInvoker;
use Foodlz\LaravelAiSkills\Support\SkillRegistry;
use Illuminate\Support\ServiceProvider;

class SkillsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-skills.php', 'ai-skills');

        $this->app->singleton(SkillRegistry::class);
        $this->app->singleton(SkillInvoker::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSkillCommand::class,
                SkillListCommand::class,
                SkillClearCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/ai-skills.php' => config_path('ai-skills.php'),
            ], ['ai-skills', 'ai-skills-config']);

            $this->publishes([
                __DIR__.'/../stubs/skill.stub' => base_path('stubs/skill.stub'),
            ], ['ai-skills', 'ai-skills-stubs']);
        }
    }
}
