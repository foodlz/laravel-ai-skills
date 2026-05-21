<?php

namespace Foodlz\LaravelAiSkills\Tests\Fixtures;

use Foodlz\LaravelAiSkills\Attributes\AsSkill;
use Foodlz\LaravelAiSkills\Enums\SkillMode;
use Foodlz\LaravelAiSkills\Skills\Skill;

#[AsSkill(name: 'lite_rules', description: 'Fetch this only when needed.', mode: SkillMode::Lite)]
class LiteSkill extends Skill
{
    public function guide(): string
    {
        return 'Lite mode guidance.';
    }
}
