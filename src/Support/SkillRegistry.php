<?php

namespace Foodlz\LaravelAiSkills\Support;

use Foodlz\LaravelAiSkills\Attributes\WithSkills;
use Foodlz\LaravelAiSkills\Contracts\HasSkills;
use Foodlz\LaravelAiSkills\Skills\Skill;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

class SkillRegistry
{
    /**
     * Resolve skills declared by attributes and the HasSkills contract.
     *
     * @return list<Skill>
     */
    public function skillsFor(object $agent): array
    {
        $skills = [];

        $attributes = (new ReflectionClass($agent))->getAttributes(WithSkills::class);
        foreach ($attributes as $attribute) {
            foreach ($attribute->newInstance()->skills as $skill) {
                $resolved = $this->resolve($skill);
                if ($resolved !== null) {
                    $skills[] = $resolved;
                }
            }
        }

        if ($agent instanceof HasSkills) {
            foreach ($agent->skills() as $skill) {
                $resolved = $this->resolve($skill);
                if ($resolved !== null) {
                    $skills[] = $resolved;
                }
            }
        }

        return $this->unique($skills);
    }

    /**
     * @param  iterable<int, class-string<Skill>|Skill|string>  $skills
     * @return list<Skill>
     */
    public function resolveMany(iterable $skills): array
    {
        $resolved = [];

        foreach ($skills as $skill) {
            $instance = $this->resolve($skill);
            if ($instance !== null) {
                $resolved[] = $instance;
            }
        }

        return $this->unique($resolved);
    }

    /**
     * @param  class-string<Skill>|Skill|string  $skill
     */
    public function resolve(string|Skill $skill): ?Skill
    {
        if ($skill instanceof Skill) {
            return $skill;
        }

        if (class_exists($skill) && is_subclass_of($skill, Skill::class)) {
            return app($skill);
        }

        foreach ($this->discovered() as $metadata) {
            if (in_array($skill, $metadata['aliases'], true)) {
                return app($metadata['class']);
            }
        }

        return null;
    }

    /**
     * @return list<array{class: class-string<Skill>, name: string, description: string, mode: string, aliases: list<string>}>
     */
    public function discovered(): array
    {
        if (! $this->cacheEnabled()) {
            return $this->discoverFresh();
        }

        return Cache::store(config('ai-skills.cache.store'))->remember(
            $this->cacheKey('discovery'),
            (int) config('ai-skills.cache.discovery_ttl', 86400),
            fn () => $this->discoverFresh()
        );
    }

    public function clearCache(): void
    {
        Cache::store(config('ai-skills.cache.store'))->forget($this->cacheKey('discovery'));
    }

    /**
     * @return list<array{class: class-string<Skill>, name: string, description: string, mode: string, aliases: list<string>}>
     */
    protected function discoverFresh(): array
    {
        $skills = [];
        $paths = config('ai-skills.discovery.paths', []);
        $namespaces = config('ai-skills.discovery.namespaces', []);

        foreach ($paths as $index => $path) {
            if (! is_string($path) || ! File::isDirectory($path)) {
                continue;
            }

            $namespace = trim((string) ($namespaces[$index] ?? $namespaces[0] ?? 'App\\Ai\\Skills'), '\\');

            foreach (File::allFiles($path) as $file) {
                $relative = Str::of($file->getRelativePathname())
                    ->replace(DIRECTORY_SEPARATOR, '\\')
                    ->replaceLast('.php', '');
                $class = $namespace.'\\'.$relative;

                if (! class_exists($class) || ! is_subclass_of($class, Skill::class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract()) {
                    continue;
                }

                try {
                    /** @var Skill $instance */
                    $instance = app($class);
                    $basename = class_basename($class);
                    $skills[] = [
                        'class' => $class,
                        'name' => $instance->name(),
                        'description' => (string) $instance->description(),
                        'mode' => $instance->mode()->value,
                        'aliases' => array_values(array_unique([
                            $class,
                            $instance->name(),
                            $basename,
                            Str::snake($basename),
                            Str::kebab($basename),
                        ])),
                    ];
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return $skills;
    }

    /**
     * @param  list<Skill>  $skills
     * @return list<Skill>
     */
    protected function unique(array $skills): array
    {
        $seen = [];

        return array_values(array_filter($skills, function (Skill $skill) use (&$seen) {
            $key = $skill::class.'|'.$skill->name();

            if (isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;

            return true;
        }));
    }

    protected function cacheEnabled(): bool
    {
        return ! app()->environment('testing') && (bool) config('ai-skills.cache.enabled', false);
    }

    protected function cacheKey(string $key): string
    {
        $prefix = trim((string) config('ai-skills.cache.prefix', 'ai-skills'), ':');

        return "{$prefix}:{$key}";
    }
}
