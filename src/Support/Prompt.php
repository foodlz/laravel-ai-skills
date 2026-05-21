<?php

namespace Foodlz\LaravelAiSkills\Support;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Stringable;

class Prompt implements Stringable
{
    protected bool $shouldCache = false;

    protected ?int $cacheTtl = null;

    protected ?string $cacheKey = null;

    protected function __construct(
        protected readonly string $type,
        protected readonly string $source,
        protected readonly array $variables = [],
    ) {
    }

    public static function text(string $text, array $variables = []): static
    {
        return new static('text', $text, $variables);
    }

    public static function file(string $path, array $variables = []): static
    {
        return new static('file', $path, $variables);
    }

    public static function view(string $view, array $variables = []): static
    {
        return new static('view', $view, $variables);
    }

    public function cache(?int $seconds = null, ?string $key = null): static
    {
        $this->shouldCache = true;
        $this->cacheTtl = $seconds;
        $this->cacheKey = $key;

        return $this;
    }

    public function render(): string
    {
        if (! $this->cacheEnabled()) {
            return $this->renderFresh();
        }

        $key = $this->resolvedCacheKey();
        $this->rememberPromptCacheKey($key);

        return Cache::store(config('ai-skills.cache.store'))->remember(
            $key,
            $this->cacheTtl ?? (int) config('ai-skills.cache.ttl', 3600),
            fn () => $this->renderFresh()
        );
    }

    public function __toString(): string
    {
        return $this->render();
    }

    protected function renderFresh(): string
    {
        $content = match ($this->type) {
            'text' => $this->source,
            'file' => $this->readFile(),
            'view' => view($this->source, $this->variables)->render(),
            default => throw new InvalidArgumentException("Unsupported prompt source [{$this->type}]."),
        };

        return $this->type === 'view' ? $content : $this->substitute($content);
    }

    protected function readFile(): string
    {
        if (! File::exists($this->source)) {
            throw new InvalidArgumentException("Prompt file [{$this->source}] does not exist.");
        }

        return File::get($this->source);
    }

    protected function substitute(string $content): string
    {
        // Supports both %{key} and {{ key }} (without $) for plain text/file substitution.
        // {{ $key }} in Blade views is handled natively by the Blade engine via Prompt::view().
        return preg_replace_callback(
            '/(?:%\{\s*([A-Za-z0-9_.-]+)\s*\}|\{\{\s*([A-Za-z0-9_.-]+)\s*\}\})/',
            function (array $matches) {
                $key = $matches[1] !== '' ? $matches[1] : $matches[2];
                $value = data_get($this->variables, $key, $matches[0]);

            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $content) ?? $content;
    }

    protected function cacheEnabled(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return $this->shouldCache || (bool) config('ai-skills.cache.enabled', false);
    }

    protected function resolvedCacheKey(): string
    {
        if ($this->cacheKey !== null) {
            return $this->cacheKey;
        }

        $prefix = trim((string) config('ai-skills.cache.prefix', 'ai-skills'), ':');

        return $prefix.':prompt:'.sha1(json_encode([
            $this->type,
            $this->source,
            $this->variables,
            $this->type === 'file' && File::exists($this->source) ? File::lastModified($this->source) : null,
        ]));
    }

    protected function rememberPromptCacheKey(string $key): void
    {
        $indexKey = trim((string) config('ai-skills.cache.prefix', 'ai-skills'), ':').':prompt:index';
        $store = Cache::store(config('ai-skills.cache.store'));
        $keys = $store->get($indexKey, []);
        $keys[] = $key;

        $store->put($indexKey, array_values(array_unique($keys)), $this->cacheTtl ?? (int) config('ai-skills.cache.ttl', 3600));
    }
}
