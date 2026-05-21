<?php

namespace Foodlz\LaravelAiSkills\Console\Commands;

use Foodlz\LaravelAiSkills\Support\SkillRegistry;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'skill:list')]
class SkillListCommand extends Command
{
    protected $name = 'skill:list';

    protected $description = 'List discovered AI skills';

    public function handle(SkillRegistry $registry): int
    {
        $skills = $registry->discovered();

        if ($skills === []) {
            $this->components->warn('No skills discovered.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Mode', 'Class', 'Description'], array_map(fn (array $skill) => [
            $skill['name'],
            $skill['mode'],
            $skill['class'],
            $skill['description'],
        ], $skills));

        return self::SUCCESS;
    }
}
