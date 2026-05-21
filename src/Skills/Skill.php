<?php

namespace Foodlz\LaravelAiSkills\Skills;

use Closure;
use Foodlz\LaravelAiSkills\Attributes\AsSkill;
use Foodlz\LaravelAiSkills\Enums\SkillMode;
use Foodlz\LaravelAiSkills\Support\Prompt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use ReflectionClass;
use RuntimeException;
use Stringable;

abstract class Skill
{
    /**
     * Runtime context injected by the agent before the skill is called.
     */
    protected array $context = [];

    /**
     * @var array<class-string<self>, Stringable|string|Closure(self): (Stringable|string)>
     */
    protected static array $fakes = [];

    /**
     * @var array<class-string<self>, list<self>>
     */
    protected static array $invocations = [];

    /**
     * The tool name exposed to the LLM.
     */
    public function name(): string
    {
        return $this->attribute()?->name
            ?? Str::snake(class_basename(static::class));
    }

    /**
     * When the LLM should call this skill.
     */
    public function description(): Stringable|string
    {
        return $this->attribute()?->description
            ?? 'Get guidance for '.Str::headline(class_basename(static::class)).'.';
    }

    /**
     * How this skill should be delivered to the model.
     */
    public function mode(): SkillMode
    {
        return $this->attribute()?->mode ?? SkillMode::OnDemand;
    }

    /**
     * The procedural knowledge returned to the LLM when this skill is invoked.
     */
    abstract public function guide(): Prompt|Stringable|string;

    /**
     * Inject runtime context before the skill is resolved.
     */
    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get runtime context.
     */
    public function context(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->context : data_get($this->context, $key, $default);
    }

    /**
     * Resolve guide text, honoring active fakes.
     */
    public function resolveGuide(): Prompt|Stringable|string
    {
        static::recordInvocation($this);

        $fake = static::$fakes[static::class] ?? null;

        if ($fake instanceof Closure) {
            return $fake($this);
        }

        if ($fake !== null) {
            return $fake;
        }

        $guide = $this->guide();
        $cache = $this->attribute()?->cache;

        if ($guide instanceof Prompt && $cache !== null) {
            return $guide->cache(is_int($cache) ? $cache : null);
        }

        return $guide;
    }

    /**
     * Fake this skill's guide response.
     */
    public static function fake(Stringable|string|Closure $response): void
    {
        static::$fakes[static::class] = $response;
        static::$invocations[static::class] = [];
    }

    /**
     * Clear fakes and invocation records.
     */
    public static function clearFake(): void
    {
        unset(static::$fakes[static::class]);
        static::$invocations[static::class] = [];
    }

    /**
     * Clear all skill fakes and invocation records.
     */
    public static function clearFakes(): void
    {
        static::$fakes = [];
        static::$invocations = [];
    }

    /**
     * Assert this skill was invoked.
     */
    public static function assertInvoked(?Closure $callback = null): void
    {
        $invocations = static::$invocations[static::class] ?? [];

        if ($callback !== null) {
            $invocations = array_values(array_filter($invocations, $callback));
        }

        static::assertThat(count($invocations) > 0, static::class.' was not invoked.');
    }

    /**
     * Assert this skill was not invoked.
     */
    public static function assertNotInvoked(): void
    {
        static::assertThat(count(static::$invocations[static::class] ?? []) === 0, static::class.' was invoked.');
    }

    protected static function recordInvocation(self $skill): void
    {
        static::$invocations[$skill::class] ??= [];
        static::$invocations[$skill::class][] = $skill;
    }

    protected static function assertThat(bool $condition, string $message): void
    {
        if (class_exists(PHPUnit::class)) {
            PHPUnit::assertTrue($condition, $message);

            return;
        }

        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    protected function attribute(): ?AsSkill
    {
        $attributes = (new ReflectionClass($this))->getAttributes(AsSkill::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
