<?php

namespace Tests\Unit;

use App\Services\McpHelper;
use App\Services\StardustHelper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mcp\Client;
use ReflectionClass;
use Tests\TestCase;

final class StreamingTest extends TestCase
{
    public function test_it_streams_complete_query_replacements(): void
    {
        $uri = 'stardust://streams/query/44?bindings=%7B%22name%22%3A%22Ada%22%7D&maximumCost=10&revision=-7';
        $messages = [
            [
                'jsonrpc' => '2.0',
                'method' => 'notifications/subscriptions/acknowledged',
                'params' => [
                    'notifications' => [
                        'resourceSubscriptions' => [$uri],
                    ],
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'notifications/stardust/stream',
                'params' => [
                    'sequence' => 1,
                    'type' => 'query',
                    'uri' => $uri,
                    'value' => ['rows' => [['name' => 'Ada']]],
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'notifications/stardust/stream',
                'params' => [
                    'sequence' => 2,
                    'type' => 'query',
                    'uri' => $uri,
                    'value' => ['rows' => [['name' => 'Grace']]],
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['resultType' => 'complete'],
            ],
        ];

        $sse = implode('', array_map(
            fn (array $message): string => 'data: '.json_encode($message, JSON_THROW_ON_ERROR)."\r\n\r\n",
            $messages,
        ));

        Http::fake([
            'http://stardust.test/mcp' => Http::response(
                $sse,
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $results = iterator_to_array($this->helper()->streamQuery(44, [
            'revision' => -7,
            'bindings' => ['name' => 'Ada'],
            'maximumCost' => 10,
        ], true));

        $this->assertSame([
            ['rows' => [['name' => 'Ada']]],
            ['rows' => [['name' => 'Grace']]],
        ], $results);

        Http::assertSent(function (Request $request) use ($uri): bool {
            $payload = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);

            return $request->url() === 'http://stardust.test/mcp'
                && $request->hasHeader('MCP-Protocol-Version', '2026-07-28')
                && $request->hasHeader('Mcp-Method', 'subscriptions/listen')
                && $payload['method'] === 'subscriptions/listen'
                && $payload['params']['notifications']['resourceSubscriptions'] === [$uri];
        });
    }

    private function helper(): StardustHelper
    {
        $reflection = new ReflectionClass(StardustHelper::class);
        $helper = $reflection->newInstanceWithoutConstructor();
        $client = $this->createMock(Client::class);
        $client->method('isConnected')->willReturn(false);
        $reflection->getProperty('client')->setValue($helper, $client);
        $reflection->getProperty('mcp')->setValue(
            $helper,
            new McpHelper('http://stardust.test/mcp'),
        );

        return $helper;
    }
}
