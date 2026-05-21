<?php

namespace Foodlz\LaravelAiSkills\Contracts;

use Foodlz\LaravelAiSkills\Skills\Skill;

interface HasSkills
{
    /**
     * Get the skills available to the agent.
     *
     * @return iterable<int, class-string<Skill>|Skill|string>
     */
    public function skills(): iterable;
}
