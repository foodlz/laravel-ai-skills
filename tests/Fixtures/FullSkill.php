<?php

namespace Foodlz\LaravelAiSkills\Tests\Fixtures;

use Foodlz\LaravelAiSkills\Attributes\AsSkill;
use Foodlz\LaravelAiSkills\Enums\SkillMode;
use Foodlz\LaravelAiSkills\Skills\Skill;

#[AsSkill(name: 'full_rules', description: 'Always-loaded rules.', mode: SkillMode::Full)]
class FullSkill extends Skill
{
    public function guide(): string
    {
        return 'Full mode guidance.';
    }
}
