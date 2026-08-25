<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Enum\ProtocolVersion;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

final class StardustHelper
{
    private Client $client;

    private string $endpoint;

    public function __construct(?string $baseUrl = null)
    {
        $baseUrl ??= config('stardust.base_url');
        $this->endpoint = rtrim($baseUrl, '/').'/mcp';

        $this->client = Client::builder()
            ->setClientInfo('Laravel Stardust Client', '1.0.0')
            ->setProtocolVersion(ProtocolVersion::V2026_07_28)
            ->setInitTimeout(30)
            ->setRequestTimeout(120)
            ->build();

        $this->client->connect(new HttpTransport(endpoint: $this->endpoint));
    }

    public function __destruct()
    {
        if ($this->client->isConnected()) {
            $this->client->disconnect();
        }
    }

    /**
     * Return an entity by its ID.
     */
    public function getEntityById(
        int $entity,
        array $options = [],
        bool $associative = false,
    ): stdClass|array {
        $result = $this->client->readResource("stardust://entities/{$entity}");

        foreach ($result->contents as $content) {
            if ($content instanceof TextResourceContents) {
                return json_decode(
                    $content->text,
                    $associative,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            }
        }

        throw new UnexpectedValueException(
            "Stardust entity {$entity} did not contain JSON text.",
        );
    }

    /**
     * Commit one transaction.
     */
    public function transact(array $data): array
    {
        $arguments = array_key_exists('patch', $data)
            ? $data
            : ['patch' => $data];

        return $this->callTool('transact', $arguments);
    }

    /**
     * Create or replace one stored query.
     */
    public function createQuery(
        array $data,
        ?int $entity = null,
        ?string $title = null,
    ): array {
        $arguments = ['query' => $data];

        if ($entity !== null) {
            $arguments['entity'] = $entity;
        }
        if ($title !== null) {
            $arguments['title'] = $title;
        }

        return $this->callTool('patch_query', $arguments);
    }

    /**
     * Run one stored query.
     */
    public function runQuery(
        int $entity,
        array $options = [],
        array $body = [],
        bool $associative = false,
    ): stdClass|array {
        $arguments = [
            'query' => $entity,
            'bindings' => $this->bindings($body),
        ];

        foreach (['revision', 'explain', 'maximumCost', 'page'] as $name) {
            if (array_key_exists($name, $options)) {
                $arguments[$name] = $options[$name];
            }
        }

        return $this->convertResult(
            $this->callTool('query', $arguments),
            $associative,
        );
    }

    /**
     * Stream complete replacements of one stored query result.
     *
     * Supported options are revision, bindings, and maximumCost.
     */
    public function streamQuery(
        int $entity,
        array $options = [],
        bool $associative = false,
    ): Generator {
        $uri = $this->queryStreamUri($entity, $options);
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
            $message = $this->decodeJsonRpcMessage($data);

            if (isset($message['error'])) {
                $error = is_array($message['error']) ? $message['error'] : [];

                throw new RuntimeException(
                    is_string($error['message'] ?? null)
                        ? $error['message']
                        : 'The Stardust query stream request failed.',
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
                        "Stardust did not accept query stream {$uri}.",
                    );
                }

                $acknowledged = true;

                continue;
            }

            if ($method === 'notifications/stardust/stream') {
                if (! $acknowledged) {
                    throw new UnexpectedValueException(
                        'Stardust sent a query replacement before acknowledging the stream.',
                    );
                }

                if (($params['uri'] ?? null) !== $uri || ($params['type'] ?? null) !== 'query') {
                    continue;
                }

                if (is_array($params['error'] ?? null)) {
                    throw new RuntimeException(
                        is_string($params['error']['message'] ?? null)
                            ? $params['error']['message']
                            : 'The Stardust query stream failed.',
                    );
                }

                if (! is_array($params['value'] ?? null)) {
                    throw new UnexpectedValueException(
                        'The Stardust query stream replacement did not contain a JSON object.',
                    );
                }

                yield $this->convertResult($params['value'], $associative);

                continue;
            }

            if (array_key_exists('id', $message) && $message['id'] === $requestId) {
                return;
            }
        }

        throw new RuntimeException(
            'The Stardust query stream closed without a terminal response.',
        );
    }

    /**
     * Create or replace one stored mutation.
     */
    public function createMutation(
        array $data,
        ?int $entity = null,
        ?string $title = null,
    ): array {
        $arguments = [
            'definition' => $data,
        ];

        if ($entity !== null) {
            $arguments['entity'] = $entity;
        }
        if ($title !== null) {
            $arguments['title'] = $title;
        }

        return $this->callTool('patch_mutation', $arguments);
    }

    /**
     * Run one stored mutation.
     */
    public function runMutation(
        int $entity,
        array $bindings = [],
        ?int $revision = null,
    ): array {
        $arguments = [
            'mutation' => $entity,
            'bindings' => $this->bindings($bindings),
            'commit' => true,
        ];

        if ($revision !== null) {
            $arguments['revision'] = $revision;
        }

        return $this->callTool('mutate', $arguments);
    }

    /**
     * Create or replace one stopped reactor.
     */
    public function createReactor(
        int $mutation,
        ?int $revision = null,
        string $title = '',
        ?int $entity = null,
        ?array $bindings = null,
    ): array {
        $arguments = [
            'mutation' => $mutation,
            'title' => $title,
        ];

        if ($revision !== null) {
            $arguments['revision'] = $revision;
        }
        if ($entity !== null) {
            $arguments['entity'] = $entity;
        }
        if ($bindings !== null) {
            $arguments['bindings'] = $this->bindings($bindings);
        }

        return $this->callTool('put_reactor', $arguments);
    }

    public function startReactor(int $reactor): array
    {
        return $this->callTool('start_reactor', [
            'reactor' => $reactor,
        ]);
    }

    public function stopReactor(int $reactor): array
    {
        return $this->callTool('stop_reactor', [
            'reactor' => $reactor,
        ]);
    }

    public function restartReactor(int $reactor): array
    {
        $this->stopReactor($reactor);

        return $this->startReactor($reactor);
    }

    public function formatDateTime(CarbonInterface $dateTime): string
    {
        return $dateTime->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Call one Stardust MCP tool and return its structured JSON object.
     */
    public function callTool(string $name, array $arguments = []): array
    {
        $result = $this->client->callTool($name, $arguments);

        if ($result->isError) {
            throw new RuntimeException($this->toolMessage($result->content));
        }

        if (is_array($result->structuredContent)) {
            return $result->structuredContent;
        }

        if (is_object($result->structuredContent)) {
            return json_decode(
                json_encode($result->structuredContent, JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        }

        foreach ($result->content as $content) {
            if (! $content instanceof TextContent) {
                continue;
            }

            $decoded = json_decode($content->text, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new UnexpectedValueException(
            "Stardust tool {$name} did not return a JSON object.",
        );
    }

    private function queryStreamUri(int $entity, array $options): string
    {
        $parameters = [];

        if (array_key_exists('bindings', $options)) {
            if (! is_array($options['bindings'])) {
                throw new InvalidArgumentException(
                    'streamQuery bindings must be an array.',
                );
            }

            $parameters['bindings'] = json_encode(
                $this->bindings($options['bindings']),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        }

        if (array_key_exists('maximumCost', $options)) {
            if (! is_int($options['maximumCost']) || $options['maximumCost'] < 0) {
                throw new InvalidArgumentException(
                    'streamQuery maximumCost must be a non-negative integer.',
                );
            }

            $parameters['maximumCost'] = (string) $options['maximumCost'];
        }

        if (array_key_exists('revision', $options)) {
            if (! is_int($options['revision'])) {
                throw new InvalidArgumentException(
                    'streamQuery revision must be an integer.',
                );
            }

            $parameters['revision'] = (string) $options['revision'];
        }

        $uri = "stardust://streams/query/{$entity}";

        if ($parameters !== []) {
            $uri .= '?'.http_build_query(
                $parameters,
                '',
                '&',
                PHP_QUERY_RFC3986,
            );
        }

        return $uri;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonRpcMessage(string $data): array
    {
        $message = json_decode(
            $data,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($message)) {
            throw new UnexpectedValueException(
                'The Stardust query stream sent an invalid JSON-RPC message.',
            );
        }

        return $message;
    }

    private function bindings(array $bindings): stdClass
    {
        return (object) $bindings;
    }

    private function convertResult(array $result, bool $associative): stdClass|array
    {
        if ($associative) {
            return $result;
        }

        return json_decode(
            json_encode($result, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function toolMessage(array $content): string
    {
        $messages = [];

        foreach ($content as $item) {
            if ($item instanceof TextContent) {
                $messages[] = $item->text;
            }
        }

        return $messages === []
            ? 'The Stardust MCP tool returned an error.'
            : implode("\n", $messages);
    }
}
