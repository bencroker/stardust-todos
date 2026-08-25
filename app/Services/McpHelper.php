<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Http;
use Mcp\Schema\Enum\ProtocolVersion;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

final readonly class McpHelper
{
    public function __construct(private string $endpoint) {}

    /**
     * Subscribe to notifications for one MCP resource.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function subscribe(string $uri): Generator
    {
        $requestId = 1;
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => 'subscriptions/listen',
            'params' => [
                'notifications' => [
                    'resourceSubscriptions' => [$uri],
                ],
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => ProtocolVersion::V2026_07_28->value,
                    'io.modelcontextprotocol/clientCapabilities' => new stdClass,
                    'io.modelcontextprotocol/clientInfo' => [
                        'name' => 'Laravel Stardust Client',
                        'version' => '1.0.0',
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => ProtocolVersion::V2026_07_28->value,
            'Mcp-Method' => 'subscriptions/listen',
        ])
            ->withBody(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json')
            ->withOptions([
                'stream' => true,
                'timeout' => 0,
                'connect_timeout' => 30,
            ])
            ->post($this->endpoint);

        $response->throw();

        $acknowledged = false;
        foreach ((new SseHelper)->data($response->toPsrResponse()) as $data) {
            $message = $this->decodeMessage($data);

            if (isset($message['error'])) {
                $error = is_array($message['error']) ? $message['error'] : [];

                throw new RuntimeException(
                    is_string($error['message'] ?? null)
                        ? $error['message']
                        : 'The MCP subscription failed.',
                );
            }

            $method = $message['method'] ?? null;
            $params = is_array($message['params'] ?? null)
                ? $message['params']
                : [];

            if ($method === 'notifications/subscriptions/acknowledged') {
                $subscriptions = $params['notifications']['resourceSubscriptions'] ?? null;
                if (! is_array($subscriptions) || ! in_array($uri, $subscriptions, true)) {
                    throw new RuntimeException(
                        "The MCP server did not accept resource subscription {$uri}.",
                    );
                }

                $acknowledged = true;

                continue;
            }

            if (array_key_exists('id', $message) && $message['id'] === $requestId) {
                return;
            }

            if (! $acknowledged) {
                throw new UnexpectedValueException(
                    'The MCP server sent a message before acknowledging the subscription.',
                );
            }

            yield $message;
        }

        throw new RuntimeException(
            'The MCP subscription closed without a terminal response.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMessage(string $data): array
    {
        $message = json_decode(
            $data,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($message)) {
            throw new UnexpectedValueException(
                'The MCP subscription sent an invalid JSON-RPC message.',
            );
        }

        return $message;
    }
}
