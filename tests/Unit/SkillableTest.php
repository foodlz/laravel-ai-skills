<?php

namespace Foodlz\LaravelAiSkills\Tests\Unit;

use Foodlz\LaravelAiSkills\Tests\Fixtures\SigningSkill;
use Foodlz\LaravelAiSkills\Tests\Fixtures\TestAgent;
use Foodlz\LaravelAiSkills\Tests\TestCase;
use Foodlz\LaravelAiSkills\Tools\FetchSkillTool;
use Foodlz\LaravelAiSkills\Tools\ListSkillsTool;
use Foodlz\LaravelAiSkills\Tools\SkillTool;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SkillableTest extends TestCase
{
    public function test_it_merges_attribute_and_method_skills(): void
    {
        $agent = new TestAgent();

        $tools = $agent->tools();

        $this->assertCount(3, $tools);
        $this->assertContainsOnlyInstancesOf(Tool::class, $tools);
        $this->assertInstanceOf(SkillTool::class, $tools[0]);
        $this->assertInstanceOf(ListSkillsTool::class, $tools[1]);
        $this->assertInstanceOf(FetchSkillTool::class, $tools[2]);
    }

    public function test_full_skills_are_appended_to_instructions(): void
    {
        $instructions = (new TestAgent())->instructions();

        $this->assertStringContainsString('Base instructions.', $instructions);
        $this->assertStringContainsString('## Skill: full_rules', $instructions);
        $this->assertStringContainsString('Full mode guidance.', $instructions);
    }

    public function test_skill_tool_invokes_guide_and_records_assertions(): void
    {
        SigningSkill::fake('Fake signing guidance.');

        $tool = new SkillTool(new SigningSkill());

        $this->assertSame('Fake signing guidance.', $tool->handle(new Request([])));

        SigningSkill::assertInvoked();
        SigningSkill::clearFake();
    }
}
