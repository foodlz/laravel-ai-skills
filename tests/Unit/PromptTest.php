<?php

namespace Foodlz\LaravelAiSkills\Tests\Unit;

use Foodlz\LaravelAiSkills\Support\Prompt;
use Foodlz\LaravelAiSkills\Tests\TestCase;

class PromptTest extends TestCase
{
    public function test_it_renders_text_with_safe_placeholders(): void
    {
        $prompt = Prompt::text('Role: %{role}. Blade example: {{ $name }}.', [
            'role' => 'Operator',
        ]);

        $this->assertSame('Role: Operator. Blade example: {{ $name }}.', (string) $prompt);
    }

    public function test_it_renders_file_with_variables(): void
    {
        $path = sys_get_temp_dir().'/ai-skills-prompt-test.md';
        file_put_contents($path, 'Hello %{name}');

        $this->assertSame('Hello Taylor', (string) Prompt::file($path, ['name' => 'Taylor']));

        unlink($path);
    }
}
