<?php

namespace Foodlz\LaravelAiSkills\Events;

use Laravel\Ai\Contracts\Agent;
use Foodlz\LaravelAiSkills\Skills\Skill;

class SkillInvoked
{
    public function __construct(
        public readonly ?Agent $agent,
        public readonly Skill $skill,
        public readonly array $arguments,
        public readonly mixed $result,
    ) {
    }
}
