<?php

namespace Foodlz\LaravelAiSkills\Tests\Fixtures;

use Foodlz\LaravelAiSkills\Attributes\AsSkill;
use Foodlz\LaravelAiSkills\Skills\Skill;

#[AsSkill(name: 'signing', description: 'Get signing guidance.')]
class SigningSkill extends Skill
{
    public function guide(): string
    {
        return 'Always sign as Recruiting Team.';
    }
}
