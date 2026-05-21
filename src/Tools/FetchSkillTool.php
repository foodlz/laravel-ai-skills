<?php

namespace Foodlz\LaravelAiSkills\Tools;

use Foodlz\LaravelAiSkills\Skills\Skill;
use Foodlz\LaravelAiSkills\Support\SkillInvoker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FetchSkillTool implements Tool
{
    /**
     * @param  list<Skill>  $skills
     */
    public function __construct(
        protected readonly array $skills,
        protected readonly ?Agent $agent = null,
    ) {
    }

    public function name(): string
    {
        return 'skill';
    }

    public function description(): Stringable|string
    {
        return 'Fetch the full guidance text for one available skill by name.';
    }

    public function handle(Request $request): Stringable|string
    {
        $name = (string) $request->string('name');

        foreach ($this->skills as $skill) {
            if ($skill->name() === $name) {
                return app(SkillInvoker::class)->invoke($skill, $this->agent, ['name' => $name]);
            }
        }

        return "Skill [{$name}] was not found.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The exact skill name returned by list_skills.'),
        ];
    }
}
