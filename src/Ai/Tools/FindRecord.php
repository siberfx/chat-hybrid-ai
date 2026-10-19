<?php

namespace Siberfx\ChatHybridAi\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Tools\Request;
use Siberfx\ChatHybridAi\Ai\ChatContext;

/**
 * Look up one record by a key the visitor gives, e.g. an order number.
 *
 * FindRecord::model(Order::class)
 *     ->by('number')
 *     ->ownedBy('user_id')       // signed-in visitors: only their own orders
 *     ->confirmWith('email')     // guests: must also give the order's email
 *     ->show(['number', 'status', 'shipped_at']);
 *
 * Without ownedBy() or confirmWith(), anyone can look records up by key
 * (fine for public data such as a product SKU). A failed ownership or
 * confirmation check answers "not found", so it never reveals that a
 * record exists.
 */
class FindRecord extends ModelTool
{
    protected string $key = 'id';

    protected ?string $owner = null;

    /** @var list<string> */
    protected array $confirm = [];

    /** The column the visitor searches by. */
    public function by(string $column): static
    {
        $this->key = $column;

        return $this;
    }

    /** Signed-in visitors only see records whose $column equals their user id. */
    public function ownedBy(string $column): static
    {
        $this->owner = $column;

        return $this;
    }

    /** Columns the visitor must also give, matching the record (case-insensitive). */
    public function confirmWith(string ...$columns): static
    {
        $this->confirm = $columns;

        return $this;
    }

    protected function defaultName(): string
    {
        return 'find_'.$this->noun();
    }

    public function description(): string
    {
        if ($this->description) {
            return $this->description;
        }

        $noun = str_replace('_', ' ', $this->noun());
        $description = "Look up one {$noun} by its {$this->key}.";

        if ($this->owner && $this->confirm) {
            $description .= ' Signed-in visitors can only see their own. Guests must also give: '.implode(', ', $this->confirm).'.';
        } elseif ($this->owner) {
            $description .= ' Only works for signed-in visitors, and only for their own.';
        } elseif ($this->confirm) {
            $description .= ' The visitor must also give: '.implode(', ', $this->confirm).'.';
        }

        return $description;
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = [$this->key => $schema->string()->required()];

        foreach ($this->confirm as $column) {
            $fields[$column] = $schema->string()->description('As given by the visitor, to confirm the record is theirs.');
        }

        return $fields;
    }

    public function handle(Request $request): string
    {
        $value = $this->argument($request, $this->key);
        $context = ChatContext::current();
        $identified = (bool) $context?->isIdentified();

        if ($value === '') {
            return $this->json(['found' => false]);
        }

        if ($this->owner && ! $identified && $this->confirm === []) {
            return $this->json(['found' => false, 'reason' => 'The visitor must sign in to look this up.']);
        }

        $query = $this->query()->where($this->key, $value);

        if ($this->owner && $identified) {
            $query->where($this->owner, $context->visitorId());
        }

        /** @var Model|null $record */
        $record = $query->first();

        if (! $record || (! ($this->owner && $identified) && ! $this->confirmed($record, $request))) {
            return $this->json(['found' => false]);
        }

        return $this->json(['found' => true, $this->noun() => $this->present($record)]);
    }

    protected function confirmed(Model $record, Request $request): bool
    {
        foreach ($this->confirm as $column) {
            $given = mb_strtolower($this->argument($request, $column));

            if ($given === '' || $given !== mb_strtolower(trim((string) $record->getAttribute($column)))) {
                return false;
            }
        }

        return true;
    }
}
