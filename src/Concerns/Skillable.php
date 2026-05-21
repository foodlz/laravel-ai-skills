<?php

namespace Foodlz\LaravelAiSkills\Concerns;

use Foodlz\LaravelAiSkills\Enums\SkillMode;
use Foodlz\LaravelAiSkills\Support\SkillInvoker;
use Foodlz\LaravelAiSkills\Support\SkillRegistry;
use Foodlz\LaravelAiSkills\Tools\FetchSkillTool;
use Foodlz\LaravelAiSkills\Tools\ListSkillsTool;
use Foodlz\LaravelAiSkills\Tools\SkillTool;
use Stringable;

trait Skillable
{
    /**
     * Dynamic skill instances for this agent. Override to provide skills at runtime.
     * Static skills should use the #[WithSkills] attribute on the class instead.
     *
     * @return iterable<class-string<\Foodlz\LaravelAiSkills\Skills\Skill>|\Foodlz\LaravelAiSkills\Skills\Skill>
     */
    public function skills(): iterable
    {
        return [];
    }

    /**
     * @return list<\Foodlz\LaravelAiSkills\Skills\Skill>
     */
    public function resolvedSkills(): array
    {
        return app(SkillRegistry::class)->skillsFor($this);
    }

    /**
     * @return list<\Laravel\Ai\Contracts\Tool>
     */
    public function skillTools(): array
    {
        $tools = [];
        $liteSkills = [];

        foreach ($this->resolvedSkills() as $skill) {
            match ($skill->mode()) {
                SkillMode::OnDemand => $tools[] = new SkillTool($skill, $this),
                SkillMode::Lite => $liteSkills[] = $skill,
                SkillMode::Full => null,
            };
        }

        if ($liteSkills !== []) {
            $tools[] = new ListSkillsTool($liteSkills);
            $tools[] = new FetchSkillTool($liteSkills, $this);
        }

        return $tools;
    }

    /**
     * Default tools() implementation — returns skill tools only.
     * Override tools() in your agent and call withSkillTools() to mix in regular action tools.
     */
    public function tools(): iterable
    {
        return $this->skillTools();
    }

    /**
     * Merge your regular action tools with skill tools.
     * Use this inside your own tools() when you have both.
     *
     * @example return $this->withSkillTools([new MyActionTool()]);
     */
    public function withSkillTools(iterable $tools = []): array
    {
        return [
            ...(is_array($tools) ? $tools : iterator_to_array($tools)),
            ...$this->skillTools(),
        ];
    }

    /**
     * Structure your instructions with explicit Static → Skills → Dynamic ordering for
     * optimal prefix cache performance. Static content (never changes) goes first so
     * providers can cache it across all conversations. Skill content (stable per deployment)
     * goes in the middle. Dynamic/per-request content goes last.
     *
     * Only Full-mode skills are injected here. OnDemand and Lite skills are exposed as tools
     * and do not need to appear in instructions().
     *
     * @example
     *   return $this->withSkillInstructions(
     *       staticPrompt:  'You are a support assistant.',
     *       dynamicPrompt: "Current user plan: {$this->user->plan}",
     *   );
     */
    public function withSkillInstructions(
        Stringable|string $staticPrompt = '',
        Stringable|string $dynamicPrompt = '',
    ): string {
        $sections = [];

        if ((string) $staticPrompt !== '') {
            $sections[] = (string) $staticPrompt;
        }

        foreach ($this->resolvedSkills() as $skill) {
            if ($skill->mode() !== SkillMode::Full) {
                continue;
            }

            $sections[] = "## Skill: {$skill->name()}\n\n".app(SkillInvoker::class)->invoke($skill, $this);
        }

        if ((string) $dynamicPrompt !== '') {
            $sections[] = (string) $dynamicPrompt;
        }

        return implode("\n\n", array_filter($sections, fn (string $s) => trim($s) !== ''));
    }
}
