<?php

namespace Foodlz\LaravelAiSkills\Tools;

use Foodlz\LaravelAiSkills\Skills\Skill;
use Foodlz\LaravelAiSkills\Support\SkillInvoker;
use Foodlz\LaravelAiSkills\Support\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SkillTool implements Tool
{
    public function __construct(
        protected readonly Skill $skill,
        protected readonly ?Agent $agent = null,
    ) {
    }

    public function name(): string
    {
        return $this->skill->name();
    }

    public function description(): Stringable|string
    {
        return $this->skill->description();
    }

    public function handle(Request $request): Stringable|string
    {
        return app(SkillInvoker::class)->invoke($this->skill, $this->agent, []);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function skill(): Skill
    {
        return $this->skill;
    }

    /**
     * Resolve multiple skill names to tool adapters.
     *
     * @param  string[]  $names
     * @return list<self>
     */
    public static function fromNames(array $names, ?Agent $agent = null): array
    {
        return array_map(
            fn (Skill $skill) => new static($skill, $agent),
            app(SkillRegistry::class)->resolveMany($names)
        );
    }

    public static function resolve(string $name, ?Agent $agent = null): ?self
    {
        $skill = app(SkillRegistry::class)->resolve($name);

        return $skill === null ? null : new static($skill, $agent);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function all(): array
    {
        return array_map(fn (array $skill) => [
            'value' => $skill['name'],
            'label' => $skill['name'].' - '.$skill['description'],
        ], app(SkillRegistry::class)->discovered());
    }
}
