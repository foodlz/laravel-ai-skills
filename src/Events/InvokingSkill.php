<?php

namespace Foodlz\LaravelAiSkills\Events;

use Laravel\Ai\Contracts\Agent;
use Foodlz\LaravelAiSkills\Skills\Skill;

class InvokingSkill
{
    public function __construct(
        public readonly ?Agent $agent,
        public readonly Skill $skill,
        public readonly array $arguments = [],
    ) {
    }
}
