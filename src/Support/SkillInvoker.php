<?php

namespace Foodlz\LaravelAiSkills\Support;

use Foodlz\LaravelAiSkills\Events\InvokingSkill;
use Foodlz\LaravelAiSkills\Events\SkillInvoked;
use Foodlz\LaravelAiSkills\Skills\Skill;
use Laravel\Ai\Contracts\Agent;

class SkillInvoker
{
    public function invoke(Skill $skill, ?Agent $agent = null, array $arguments = []): string
    {
        event(new InvokingSkill($agent, $skill, $arguments));

        $result = $skill->resolveGuide();
        $resolved = (string) $result;

        event(new SkillInvoked($agent, $skill, $arguments, $resolved));

        return $resolved;
    }
}
