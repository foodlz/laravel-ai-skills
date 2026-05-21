<?php

namespace Foodlz\LaravelAiSkills\Tests\Fixtures;

use Foodlz\LaravelAiSkills\Attributes\WithSkills;
use Foodlz\LaravelAiSkills\Concerns\Skillable;
use Foodlz\LaravelAiSkills\Contracts\HasSkills;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[WithSkills(SigningSkill::class, FullSkill::class)]
class TestAgent implements Agent, HasTools, HasSkills
{
    use Promptable;
    use Skillable;

    public function instructions(): Stringable|string
    {
        return $this->withSkillInstructions(staticPrompt: 'Base instructions.');
    }

    public function tools(): iterable
    {
        return $this->withSkillTools();
    }

    public function skills(): iterable
    {
        return [
            LiteSkill::class,
        ];
    }
}
