<?php

namespace Foodlz\LaravelAiSkills\Attributes;

use Attribute;
use Foodlz\LaravelAiSkills\Enums\SkillMode;

#[Attribute(Attribute::TARGET_CLASS)]
class AsSkill
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly SkillMode $mode = SkillMode::OnDemand,
        public readonly bool|int|null $cache = null,
    ) {
    }
}
