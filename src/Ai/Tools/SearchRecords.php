<?php

namespace Siberfx\ChatHybridAi\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Tools\Request;

/**
 * Keyword search over a model, for "do you have…", "find me…" questions.
 *
 * SearchRecords::model(Product::class)
 *     ->in(['name', 'description'])
 *     ->show(['name', 'price', 'url'])
 *     ->where(fn ($query) => $query->where('published', true))
 *     ->limit(5);
 *
 * Uses Laravel Scout when the model is searchable and usingScout() is called,
 * otherwise a LIKE search on the given columns.
 */
class SearchRecords extends ModelTool
{
    /** @var list<string> */
    protected array $columns = [];

    protected int $limit = 5;

    protected bool $scout = false;

    /** The columns to search in (defaults to the show() fields). */
    public function in(array $columns): static
    {
        $this->columns = array_values($columns);

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = max(1, min($limit, 50));

        return $this;
    }

    public function usingScout(bool $scout = true): static
    {
        $this->scout = $scout;

        return $this;
    }

    protected function defaultName(): string
    {
        return 'search_'.$this->noun(plural: true);
    }

    public function description(): string
    {
        return $this->description
            ?? 'Search the site\'s '.str_replace('_', ' ', $this->noun(plural: true))." by keywords. Returns up to {$this->limit} matches.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keywords to search for.')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $term = $this->argument($request, 'query');
        $columns = $this->columns ?: $this->show;

        if ($term === '' || $columns === []) {
            return $this->json(['results' => []]);
        }

        $results = $this->scout && method_exists($this->model, 'search')
            ? $this->model::search($term)->query(fn ($query) => $this->applyScopes($query))->take($this->limit)->get()
            : $this->query()
                ->where(function ($query) use ($term, $columns) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%'.$term.'%');
                    }
                })
                ->limit($this->limit)
                ->get();

        return $this->json([
            'results' => $results->map(fn (Model $record) => $this->present($record))->values()->all(),
        ]);
    }
}
