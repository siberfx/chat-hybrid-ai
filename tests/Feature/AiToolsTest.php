<?php

namespace Siberfx\ChatHybridAi\Tests\Feature;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Siberfx\ChatHybridAi\Ai\ChatContext;
use Siberfx\ChatHybridAi\Ai\Tools\Check;
use Siberfx\ChatHybridAi\Ai\Tools\FindRecord;
use Siberfx\ChatHybridAi\Ai\Tools\SearchRecords;
use Siberfx\ChatHybridAi\Facades\HybridChat;
use Siberfx\ChatHybridAi\Tests\Fixtures\Order;
use Siberfx\ChatHybridAi\Tests\Fixtures\Product;
use Siberfx\ChatHybridAi\Tests\TestCase;

class AiToolsTest extends TestCase
{
    protected function asVisitor(?string $visitorId): void
    {
        $conversation = HybridChat::start(['visitor_id' => $visitorId]);
        app()->instance(ChatContext::class, new ChatContext($conversation));
    }

    protected function runTool($tool, array $arguments): array
    {
        return json_decode($tool->handle(new Request($arguments)), true);
    }

    protected function orders(): FindRecord
    {
        return FindRecord::model(Order::class)->by('number')->ownedBy('user_id')->confirmWith('email')->show(['number', 'status']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Product::create(['name' => 'Red running shoes', 'price' => 89, 'cost' => 30]);
        Product::create(['name' => 'Red hiking boots', 'description' => 'Waterproof', 'price' => 140, 'cost' => 60]);
        Product::create(['name' => 'Red prototype', 'price' => 1, 'published' => false]);
        Order::create(['number' => 'A-1', 'user_id' => 5, 'email' => 'vera@example.com', 'status' => 'shipped']);
        Order::create(['number' => 'B-2', 'user_id' => 6, 'email' => 'bob@example.com', 'status' => 'pending']);
    }

    public function test_search_returns_only_shown_fields_within_scope(): void
    {
        $this->asVisitor(null);

        $tool = SearchRecords::model(Product::class)
            ->in(['name', 'description'])
            ->show(['name', 'price'])
            ->where(fn ($query) => $query->where('published', true));

        $this->assertSame('search_products', $tool->name());

        $results = $this->runTool($tool, ['query' => 'red'])['results'];
        $this->assertCount(2, $results);
        $this->assertSame(['name', 'price'], array_keys($results[0]));

        $this->assertSame('Red hiking boots', $this->runTool($tool, ['query' => 'waterproof'])['results'][0]['name']);
        $this->assertCount(1, $this->runTool($tool->limit(1), ['query' => 'red'])['results']);
        $this->assertSame([], $this->runTool($tool, ['query' => '  '])['results']);
    }

    public function test_signed_in_visitor_finds_only_their_own_records(): void
    {
        $this->asVisitor('5');

        $this->assertSame(['number' => 'A-1', 'status' => 'shipped'], $this->runTool($this->orders(), ['number' => 'A-1'])['order']);
        $this->assertFalse($this->runTool($this->orders(), ['number' => 'B-2'])['found']);
        $this->assertFalse($this->runTool($this->orders(), ['number' => 'B-2', 'email' => 'bob@example.com'])['found']);
    }

    public function test_guest_must_confirm_the_record(): void
    {
        $this->asVisitor(null);

        $this->assertFalse($this->runTool($this->orders(), ['number' => 'A-1'])['found']);
        $this->assertFalse($this->runTool($this->orders(), ['number' => 'A-1', 'email' => 'bob@example.com'])['found']);
        $this->assertTrue($this->runTool($this->orders(), ['number' => 'A-1', 'email' => ' Vera@Example.com '])['found']);

        $ownerOnly = FindRecord::model(Order::class)->by('number')->ownedBy('user_id');
        $this->assertStringContainsString('sign in', $this->runTool($ownerOnly, ['number' => 'A-1'])['reason']);
    }

    public function test_find_without_restrictions_is_a_public_lookup(): void
    {
        $this->asVisitor(null);

        $tool = FindRecord::model(Product::class)->by('name')->show(['name', 'price']);

        $this->assertSame('find_product', $tool->name());
        $this->assertSame('Red running shoes', $this->runTool($tool, ['name' => 'Red running shoes'])['product']['name']);
    }

    public function test_check_tool_runs_the_closure(): void
    {
        $this->asVisitor('5');

        $tool = Check::make('check_coupon', 'Check a coupon.', ['code' => 'string', 'cart_total' => '?number'],
            fn (array $input, ?ChatContext $context) => $input['code'] === 'WELCOME10' && $context->visitorId() === '5');

        $schema = $tool->schema(new JsonSchemaTypeFactory);
        $this->assertSame(['code', 'cart_total'], array_keys($schema));

        $this->assertSame(['result' => true], $this->runTool($tool, ['code' => 'WELCOME10']));
        $this->assertSame(['result' => false], $this->runTool($tool, ['code' => 'NOPE']));

        $text = Check::make('delivery', 'Delivery time.', ['zip' => 'string'], fn () => 'Two days.');
        $this->assertSame('Two days.', $text->handle(new Request(['zip' => '34000'])));
    }
}
