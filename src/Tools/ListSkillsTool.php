<?php

namespace Foodlz\LaravelAiSkills\Tools;

use Foodlz\LaravelAiSkills\Skills\Skill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListSkillsTool implements Tool
{
    /**
     * @param  list<Skill>  $skills
     */
    public function __construct(protected readonly array $skills)
    {
    }

    public function name(): string
    {
        return 'list_skills';
    }

    public function description(): Stringable|string
    {
        return 'List available knowledge skills and when to fetch them.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode(array_map(fn (Skill $skill) => [
            'name' => $skill->name(),
            'description' => (string) $skill->description(),
        ], $this->skills), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
