<?php

namespace Foodlz\LaravelAiSkills\Attributes;

use Attribute;
use Foodlz\LaravelAiSkills\Skills\Skill;

#[Attribute(Attribute::TARGET_CLASS)]
class WithSkills
{
    /**
     * @var array<int, class-string<Skill>|Skill|string>
     */
    public readonly array $skills;

    /**
     * @param  class-string<Skill>|Skill|string  ...$skills
     */
    public function __construct(string|Skill ...$skills)
    {
        $this->skills = $skills;
    }
}
