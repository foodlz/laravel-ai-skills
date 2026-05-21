<?php

namespace Foodlz\LaravelAiSkills\Console\Commands;

use Foodlz\LaravelAiSkills\Support\SkillRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'skill:clear')]
class SkillClearCommand extends Command
{
    protected $name = 'skill:clear';

    protected $description = 'Clear AI skill discovery and content caches';

    public function handle(SkillRegistry $registry): int
    {
        $registry->clearCache();

        $prefix = trim((string) config('ai-skills.cache.prefix', 'ai-skills'), ':');
        $store = Cache::store(config('ai-skills.cache.store'));
        $indexKey = "{$prefix}:prompt:index";

        foreach ($store->get($indexKey, []) as $key) {
            $store->forget($key);
        }

        $store->forget($indexKey);

        $this->components->info('Skill caches cleared successfully.');

        return self::SUCCESS;
    }
}
