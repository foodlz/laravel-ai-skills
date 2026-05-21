# Laravel AI Skills

**Procedural knowledge skills for the [Laravel AI SDK](https://github.com/laravel/ai).** Teach your AI agents *how to behave* — not just *what to do*.

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://php.net)
[![Laravel 12+](https://img.shields.io/badge/Laravel-12%2B-red)](https://laravel.com)
[![Laravel AI SDK 0.6+](https://img.shields.io/badge/laravel%2Fai-0.6%2B-orange)](https://github.com/laravel/ai)
[![License MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)

> **Experimental RFC infrastructure.** This package validates the Skills concept in real production use before it is proposed upstream to [`laravel/ai`](https://github.com/laravel/ai). The goal is to gather real-world feedback and edge cases that make the proposal persuasive to maintainers.

---

## The Problem: Knowledge vs. Action

The Laravel AI SDK models agent capabilities as **tools** — PHP classes that perform an action: look up a record, send a message, call an API. This works perfectly for actions.

But many agents also need **procedural knowledge** — guidance on *how to behave* in a given situation:

- *"Here is how to handle a refund request."*
- *"Here is the tone guide for customer support replies."*
- *"Here is the escalation policy when a user reports data loss."*

Today you have two bad options:

| Option | Problem |
|---|---|
| Bake it into `instructions()` | System prompt grows unboundedly. Every token is charged on every request, even guidance that's only relevant in one scenario out of ten. |
| Use a regular `Tool` with empty schema | Works mechanically, but it's an abuse of the `Tool` abstraction. Tools are for actions, not knowledge retrieval. No convention, no scaffold command, no first-class type. |

**A Skill is the missing first-class concept:** a callable knowledge object with no input schema, whose sole purpose is to return guidance to the model when it decides that guidance is relevant.

---

## Features

- **Three delivery modes** — OnDemand (one tool per skill), Full (injected into instructions), and Lite (meta-tool pair for high-skill-count agents)
- **`Prompt` value object** — load skill content from inline text or Markdown files (`%{key}` or `{{ key }}` substitution) or Blade views (native Blade syntax: `{{ $key }}`)
- **Runtime context injection** — pass job-specific or conversation-specific data into skills via `withContext()`
- **Database-driven skills** — `guide()` is plain PHP; return an Eloquent query result for fully editable, admin-managed content
- **Content caching** — per-prompt or global config; disabled automatically in tests
- **Full testing support** — `Skill::fake()`, `Skill::assertInvoked()`, `Skill::assertNotInvoked()`
- **`php artisan make:skill`** — scaffolding that mirrors `make:tool` and `make:agent`
- **`#[WithSkills]` attribute** — declarative, IDE-navigable skill registration on agent classes
- **`#[AsSkill]` attribute** — name, description, mode, and cache in one place without override methods
- **`withSkillInstructions(static, dynamic)`** — prefix-cache-friendly Static → Skills → Dynamic prompt ordering
- **Events** — `InvokingSkill` and `SkillInvoked` for observability

---

## Requirements

- PHP 8.3+
- Laravel 12+
- `laravel/ai ^0.6`

---

## Installation

```bash
composer require foodlz/laravel-ai-skills
```

The service provider is auto-discovered. Publish the config file if you need to customise discovery paths or cache settings:

```bash
php artisan vendor:publish --tag=ai-skills-config
```

---

## Quick Start

### 1. Scaffold a skill

```bash
php artisan make:skill SigningSkill
# or with a companion Markdown file:
php artisan make:skill SigningSkill --markdown
```

This creates `app/Ai/Skills/SigningSkill.php` (and optionally `resources/skills/signing.md`):

```php
<?php

namespace App\Ai\Skills;

use Foodlz\LaravelAiSkills\Attributes\AsSkill;
use Foodlz\LaravelAiSkills\Skills\Skill;
use Foodlz\LaravelAiSkills\Support\Prompt;

#[AsSkill(
    name: 'signing',
    description: 'Describe when the LLM should call this skill.',
)]
class SigningSkill extends Skill
{
    public function guide(): Prompt|string
    {
        return Prompt::file(resource_path('skills/signing.md'));
    }
}
```

### 2. Attach it to an agent

The simplest case — OnDemand skills only, no other action tools:

```php
use App\Ai\Skills\SigningSkill;
use Foodlz\LaravelAiSkills\Attributes\WithSkills;
use Foodlz\LaravelAiSkills\Concerns\Skillable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[WithSkills(ToneGuideSkill::class)]
class SupportAgent implements Agent, HasTools
{
    use Promptable;
    use Skillable; // provides a default tools() that returns skill tools

    public function instructions(): Stringable|string
    {
        return 'You are a customer support assistant. Use your available skills to follow the correct tone, policies, and procedures before responding.';
    }
}
```

That's it. The `Skillable` trait provides a default `tools()` implementation that exposes all attached skills to the LLM automatically.

> **Both are required.** `implements HasTools` is the SDK's gate — it only calls `tools()` when the agent declares that interface. `use Skillable` provides the actual implementation. One without the other means no skills are loaded.

---

## Usage

### Skill Modes

Set the mode on the `#[AsSkill]` attribute (or override `mode()` on the class):

```php
use Foodlz\LaravelAiSkills\Enums\SkillMode;

#[AsSkill(name: 'signing', description: '...', mode: SkillMode::Full)]
class SigningSkill extends Skill { ... }
```

| Mode | Behaviour | When to use |
|---|---|---|
| `OnDemand` *(default)* | One zero-argument tool per skill; LLM calls it when needed | Situational knowledge |
| `Full` | Guide text injected directly into `instructions()` on every request | Rules that always apply (e.g. signing policy) |
| `Lite` | Exposes two meta-tools — `list_skills` (returns all Lite skill names + descriptions) and `skill` (fetches full content by name). Requires **two LLM round-trips** before the model has the knowledge. | Agents with 8+ skills — reduces tool list size at the cost of latency |

### Full Mode: `withSkillInstructions()`

Use `withSkillInstructions()` when any skill uses `SkillMode::Full`. It structures your system prompt in the order providers recommend for prefix cache performance: **Static → Skills → Dynamic**.

```php
public function instructions(): Stringable|string
{
    return $this->withSkillInstructions(
        staticPrompt:  'You are a customer support assistant. Follow all policies and procedures when responding.',
        dynamicPrompt: "Current user plan: {$this->user->plan}",
    );
}
```

- `staticPrompt` — content that never changes across requests (base persona, global rules). Put this first for maximum cache reuse.
- Skills — Full-mode skill content goes here automatically.
- `dynamicPrompt` — per-request or per-user content (context, locale, user data). Goes last to keep the cached prefix as long as possible.

Both parameters are optional. Omit `dynamicPrompt` if you have no per-request content:

```php
return $this->withSkillInstructions(
    staticPrompt: 'You are a customer support assistant.'
);
```

OnDemand and Lite skills are unaffected — they are exposed as tools and do not appear in `instructions()`.

### Mixing Action Tools with Skill Tools

When your agent has both action tools and skills, override `tools()` and use `withSkillTools()`:

```php
use App\Ai\Tools\LookupOrder;
use App\Ai\Tools\SendReply;

public function tools(): iterable
{
    return $this->withSkillTools([
        new LookupOrder,
        new SendReply,
    ]);
}
```

`withSkillTools()` merges your action tools with all skill tools and returns the combined array.

### Dynamic Skills: `HasSkills` + `skills()`

For skills that need runtime data — the current user's plan, account type, locale, or any other request-time value — implement `HasSkills` and override `skills()`:

```php
use Foodlz\LaravelAiSkills\Contracts\HasSkills;

#[WithSkills(ToneGuideSkill::class)]               // static skills via attribute
class SupportAgent implements Agent, HasTools, HasSkills
{
    use Promptable;
    use Skillable;

    public function skills(): iterable             // dynamic skills with injected context
    {
        return [
            (new RefundPolicySkill)->withContext([
                'plan'     => $this->user->plan,
                'currency' => $this->user->currency,
            ]),
        ];
    }
}
```

The registry merges attribute skills and method skills automatically. Class strings and instances are both valid.

> **`HasSkills` is optional.** Only implement it when you need to pass runtime context into skills via `skills()`. For static skill sets, `#[WithSkills]` on its own is sufficient.

## The `Prompt` Value Object

`Prompt` implements `Stringable` and works anywhere a string is expected.

```php
use Foodlz\LaravelAiSkills\Support\Prompt;

// Inline text — %{key} and {{ key }} both work
Prompt::text('You are assisting a %{plan} plan user in {{ locale }}.', [
    'plan'   => $this->context['plan'],
    'locale' => $this->context['locale'],
]);

// Markdown or plain text file
Prompt::file(resource_path('skills/signing.md'));

// File with variable substitution — both syntaxes work
Prompt::file(resource_path('skills/refund-policy.md'), [
    'plan'     => $this->context['plan']     ?? 'standard',
    'currency' => $this->context['currency'] ?? 'USD',
]);

// Blade view — use normal Blade syntax ({{ $plan }}, @if, components, etc.)
Prompt::view('skills.refund-policy', [
    'plan' => $this->context['plan'],
]);

// Cache the resolved content
Prompt::file(resource_path('skills/signing.md'))->cache();
Prompt::file(resource_path('skills/signing.md'))->cache(seconds: 3600);
```

The `%{key}` and `{{ key }}` syntaxes both work for `Prompt::text()` and `Prompt::file()` — use whichever you prefer. Double-curly with a `$` (`{{ $key }}`) is Blade syntax and applies only to `Prompt::view()`.

`Prompt` is also usable in `instructions()` outside of skills:

```php
public function instructions(): Stringable|string
{
    return Prompt::file(resource_path('prompts/support-agent.md'));
}
```

### Database-driven skill content

`guide()` is plain PHP — you are not limited to static files:

```php
public function guide(): string
{
    // Content managed via an admin panel; no deployment needed to update it
    return AiSkillContent::where('key', 'email-writing')->value('body')
        ?? 'Write clearly and concisely. Lead with value.';
}
```

---

## Skill Examples

### Static skill — Markdown file, editable by non-developers

```php
#[AsSkill(
    name: 'tone_guide',
    description: 'Get the tone and style guide. Always call before composing any outbound message.',
    mode: SkillMode::Full,
)]
class ToneGuideSkill extends Skill
{
    public function guide(): Prompt|string
    {
        return Prompt::file(resource_path('skills/tone-guide.md'))->cache();
    }
}
```

```markdown
<!-- resources/skills/tone-guide.md -->
## Tone Guide

Always be friendly, concise, and professional.
Avoid jargon. Use plain language that any user can understand.
Never use passive-aggressive phrasing. When in doubt, be warmer.
Sign off support emails with "The Support Team" — never a personal name.
```

### Dynamic skill — context injected at runtime

```php
#[AsSkill(
    name: 'refund_policy',
    description: 'Get the refund policy. Call before handling any refund or billing question.',
)]
class RefundPolicySkill extends Skill
{
    public function guide(): Prompt|string
    {
        return Prompt::file(resource_path('skills/refund-policy.md'), [
            'plan'     => $this->context['plan']     ?? 'standard',
            'currency' => $this->context['currency'] ?? 'USD',
        ]);
    }
}
```

---

## Caching

### Per-prompt caching

Call `.cache()` on any `Prompt` instance to cache the resolved string output:

```php
Prompt::file(resource_path('skills/signing.md'))->cache();          // uses config TTL
Prompt::file(resource_path('skills/signing.md'))->cache(3600);      // 1 hour
```

### Global caching via config

```php
// config/ai-skills.php
'cache' => [
    'enabled'       => env('AI_SKILLS_CACHE', app()->isProduction()),
    'store'         => env('AI_SKILLS_CACHE_STORE', null),
    'ttl'           => env('AI_SKILLS_CACHE_TTL', 3600),
    'discovery_ttl' => env('AI_SKILLS_DISCOVERY_TTL', 86400),
    'prefix'        => env('AI_SKILLS_CACHE_PREFIX', 'ai-skills'),
],
```

Caching is **disabled automatically in `testing` environments** regardless of config.

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan make:skill ToneGuideSkill` | Scaffold a skill class in `app/Ai/Skills/` |
| `php artisan make:skill ToneGuideSkill --markdown` | Scaffold class + `resources/skills/tone-guide.md` |
| `php artisan skill:list` | List all discovered skills with names, descriptions, and modes |
| `php artisan skill:clear` | Clear skill content and discovery caches |

---

## Testing

### Fake skill responses

```php
use App\Ai\Skills\ToneGuideSkill;
use App\Ai\Skills\RefundPolicySkill;

// Return a fixed string for all invocations
ToneGuideSkill::fake('Be friendly and concise.');

// Dynamically return based on injected context
RefundPolicySkill::fake(function (RefundPolicySkill $skill): string {
    return 'Refund policy for plan: ' . ($skill->context('plan') ?? 'standard');
});
```

### Assert skills were (or were not) invoked

```php
RefundPolicySkill::assertInvoked();

RefundPolicySkill::assertInvoked(function (RefundPolicySkill $skill): bool {
    return $skill->context('plan') === 'pro';
});

ToneGuideSkill::assertNotInvoked();
```

### Unit test skill content directly

Skills are plain PHP objects — test `guide()` output without spinning up an agent:

```php
public function test_includes_plan_in_refund_policy(): void
{
    $skill = (new RefundPolicySkill)->withContext([
        'plan'     => 'pro',
        'currency' => 'EUR',
    ]);

    $guide = (string) $skill->guide();

    $this->assertStringContainsString('pro', $guide);
    $this->assertStringContainsString('EUR', $guide);
}
```

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=ai-skills-config
```

```php
// config/ai-skills.php
return [
    'discovery' => [
        'paths'      => [app_path('Ai/Skills')],
        'namespaces' => ['App\\Ai\\Skills'],
    ],

    'cache' => [
        'enabled'       => env('AI_SKILLS_CACHE', app()->isProduction()),
        'store'         => env('AI_SKILLS_CACHE_STORE', null),
        'ttl'           => env('AI_SKILLS_CACHE_TTL', 3600),
        'discovery_ttl' => env('AI_SKILLS_DISCOVERY_TTL', 86400),
        'prefix'        => env('AI_SKILLS_CACHE_PREFIX', 'ai-skills'),
    ],
];
```

---

## API Reference

### `Skillable` trait

| Method | Description |
|---|---|
| `skills(): iterable` | Override to provide runtime skill instances. Returns `[]` by default. |
| `resolvedSkills(): array` | Returns all resolved `Skill` instances for this agent. |
| `skillTools(): array` | Returns `Tool`-compatible adapters for all non-Full skills (OnDemand and Lite). |
| `tools(): iterable` | Default implementation — returns `skillTools()`. Override if you have action tools. |
| `withSkillTools(iterable $tools): array` | Merges your action tools with skill tools. Use inside `tools()`. |
| `withSkillInstructions(static, dynamic): string` | Structures instructions as Static → Full skills → Dynamic for prefix cache optimization. |

### `Skill` abstract class

| Method | Description |
|---|---|
| `name(): string` | Tool name exposed to the LLM. Defaults to snake_case class basename. |
| `description(): string` | When the LLM should call this skill. |
| `mode(): SkillMode` | Delivery mode. Defaults to `OnDemand`. |
| `guide(): Prompt\|string` | The procedural knowledge returned when this skill is invoked. **Abstract.** |
| `withContext(array $context): static` | Inject runtime context before the skill is resolved. |
| `context(?string $key, mixed $default): mixed` | Read injected context. |
| `static fake(string\|Closure $response): void` | Stub guide output in tests. |
| `static assertInvoked(?Closure $callback): void` | Assert the skill was called. |
| `static assertNotInvoked(): void` | Assert the skill was not called. |
| `static clearFakes(): void` | Reset all fakes and invocation records. |

---

## Events

| Event | When |
|---|---|
| `Foodlz\LaravelAiSkills\Events\InvokingSkill` | Before `guide()` is called |
| `Foodlz\LaravelAiSkills\Events\SkillInvoked` | After `guide()` returns |

Both events carry the `Skill` instance and the agent.

---

## How It Works Internally

OnDemand and Lite skills are adapted to `Laravel\Ai\Contracts\Tool` at runtime via an internal `SkillTool` adapter — the LLM never knows the difference between a tool and a skill. Full-mode skills bypass the tool call entirely and have their content appended directly into the system prompt by `withSkillInstructions()`.

The `SkillRegistry` reads `#[WithSkills]` via reflection and merges those with any skills returned by `skills()` if the agent implements `HasSkills`. Discovery is scanned from the paths configured in `ai-skills.discovery`.

---

## Roadmap

- [x] `php artisan skill:list` — list all discovered skills with names, descriptions, and modes
- [x] `php artisan skill:clear` — clear skill content and discovery caches
- [x] Discovery caching
- [x] `withSkillInstructions(static, dynamic)`
- [ ] `skill_read` — LLM-accessible supplementary files per skill (via `resourcePath()`) — prefix-cache-friendly prompt ordering
- [ ] `Prompt::url()` — load skill content from a remote URL

---

## Credits

- Inspired by [anilcancakir/laravel-ai-sdk-skills](https://github.com/anilcancakir/laravel-ai-sdk-skills) — a community package that explored SKILL.md file-based skills and the meta-tool `list_skills` / `skill` pattern, which directly influenced the `Lite` mode and `Prompt` value object design in this package.
- Built on the [Laravel AI SDK](https://github.com/laravel/ai) by [Taylor Otwell](https://github.com/taylorotwell) and the Laravel team.

---

## License

MIT — see [LICENSE](LICENSE).
