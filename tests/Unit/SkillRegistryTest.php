<?php

namespace Foodlz\LaravelAiSkills\Tests\Unit;

use Foodlz\LaravelAiSkills\Support\SkillRegistry;
use Foodlz\LaravelAiSkills\Tests\Fixtures\SigningSkill;
use Foodlz\LaravelAiSkills\Tests\TestCase;

class SkillRegistryTest extends TestCase
{
    public function test_it_discovers_and_resolves_skills_by_alias(): void
    {
        $registry = app(SkillRegistry::class);

        $this->assertNotEmpty($registry->discovered());
        $this->assertInstanceOf(SigningSkill::class, $registry->resolve('signing'));
        $this->assertInstanceOf(SigningSkill::class, $registry->resolve('SigningSkill'));
    }
}
