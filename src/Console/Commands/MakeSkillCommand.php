<?php

namespace Foodlz\LaravelAiSkills\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:skill')]
class MakeSkillCommand extends GeneratorCommand
{
    protected $name = 'make:skill';

    protected $description = 'Create a new AI skill';

    protected $type = 'Skill';

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Ai\Skills';
    }

    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/skill.stub');
    }

    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.'/../../../'.$stub;
    }

    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the skill even if the skill already exists'],
            ['markdown', 'm', InputOption::VALUE_NONE, 'Create a matching Markdown guide in resources/skills'],
        ];
    }

    protected function buildClass($name)
    {
        $class = parent::buildClass($name);

        $skillName = Str::snake(Str::replaceEnd('Skill', '', class_basename($name)));

        return str_replace(
            ['{{ markdown_path }}', '{{ name }}'],
            [$this->markdownPath($name), $skillName],
            $class
        );
    }

    public function handle()
    {
        $result = parent::handle();

        if ($result !== false && $this->option('markdown')) {
            $path = resource_path('skills/'.$this->markdownFilename($this->qualifyClass($this->getNameInput())));

            if (! $this->files->isDirectory(dirname($path))) {
                $this->files->makeDirectory(dirname($path), 0755, true);
            }

            if (! $this->files->exists($path) || $this->option('force')) {
                $this->files->put($path, "# {$this->skillTitle($this->qualifyClass($this->getNameInput()))}\n\nWrite the guidance for this skill here.\n");
                $this->components->info("Markdown guide created successfully: [{$path}]");
            }
        }

        return $result;
    }

    protected function markdownPath(string $class): string
    {
        return "skills/".$this->markdownFilename($class);
    }

    protected function markdownFilename(string $class): string
    {
        return Str::kebab(Str::replaceEnd('Skill', '', class_basename($class))).'.md';
    }

    protected function skillTitle(string $class): string
    {
        return Str::headline(Str::replaceEnd('Skill', '', class_basename($class))).' Skill';
    }
}
