<?php

namespace Siberfx\ChatHybridAi\Ai\Tools;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Siberfx\ChatHybridAi\Ai\ChatContext;

/**
 * Shared builder for tools that read an Eloquent model. Only the fields
 * passed to show() ever reach the AI.
 */
abstract class ModelTool implements Tool
{
    protected ?string $name = null;

    protected ?string $description = null;

    /** @var list<string> */
    protected array $show = [];

    /** @var list<Closure(Builder, ChatContext): mixed> */
    protected array $scopes = [];

    /** @param class-string<Model> $model */
    public function __construct(protected string $model) {}

    /** @param class-string<Model> $model */
    public static function model(string $model): static
    {
        return new static($model);
    }

    /** The tool name the AI sees, e.g. "search_products". */
    public function as(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /** Tell the AI what the tool is for and when to use it. */
    public function describedAs(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** The only fields (attributes or accessors) returned to the AI. */
    public function show(array $fields): static
    {
        $this->show = array_values($fields);

        return $this;
    }

    /** Limit the query: fn (Builder $query, ChatContext $context) => $query->where('published', true). */
    public function where(Closure $scope): static
    {
        $this->scopes[] = $scope;

        return $this;
    }

    public function name(): string
    {
        return $this->name ?? $this->defaultName();
    }

    abstract protected function defaultName(): string;

    protected function query(): Builder
    {
        return $this->applyScopes($this->model::query());
    }

    protected function applyScopes(Builder $query): Builder
    {
        $context = ChatContext::current();

        foreach ($this->scopes as $scope) {
            $scope($query, $context);
        }

        return $query;
    }

    protected function present(Model $record): array
    {
        return $record->only($this->show ?: [$record->getKeyName()]);
    }

    /** "order_items" style name of the model. */
    protected function noun(bool $plural = false): string
    {
        $base = class_basename($this->model);

        return Str::snake($plural ? Str::pluralStudly($base) : $base);
    }

    /** A trimmed argument; models may leave optional ones out. */
    protected function argument(Request $request, string $key): string
    {
        return trim((string) ($request->toArray()[$key] ?? ''));
    }

    protected function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
