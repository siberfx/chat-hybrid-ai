<?php

namespace Siberfx\ChatHybridAi\Ai\Tools;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Siberfx\ChatHybridAi\Ai\ChatContext;

/**
 * A tool from a closure, for checks and answers that don't fit a single
 * model: "is this coupon valid?", "do you deliver to 34000?".
 *
 * Check::make(
 *     'check_coupon',
 *     'Check whether a discount code is valid right now.',
 *     ['code' => 'string'],
 *     fn (array $input, ?ChatContext $context) => Coupon::active()->where('code', $input['code'])->exists(),
 * );
 *
 * Parameter types: string, integer, number, boolean; prefix with "?" for
 * optional. Return a bool, string, number or array.
 */
class Check implements Tool
{
    /**
     * @param  array<string, string>  $parameters
     * @param  Closure(array, ?ChatContext): mixed  $callback
     */
    public function __construct(
        protected string $name,
        protected string $description,
        protected array $parameters,
        protected Closure $callback,
    ) {}

    public static function make(string $name, string $description, array $parameters, Closure $callback): static
    {
        return new static($name, $description, $parameters, $callback);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = [];

        foreach ($this->parameters as $name => $type) {
            $optional = str_starts_with($type, '?');

            $field = match (ltrim($type, '?')) {
                'string' => $schema->string(),
                'integer' => $schema->integer(),
                'number' => $schema->number(),
                'boolean' => $schema->boolean(),
                default => throw new InvalidArgumentException("Unsupported parameter type [{$type}] for [{$name}]."),
            };

            $fields[$name] = $optional ? $field : $field->required();
        }

        return $fields;
    }

    public function handle(Request $request): string
    {
        $result = ($this->callback)($request->all(array_keys($this->parameters)), ChatContext::current());

        if (is_string($result)) {
            return $result;
        }

        return json_encode(
            is_array($result) ? $result : ['result' => $result],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }
}
