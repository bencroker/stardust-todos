<?php

namespace App\Services;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use stdClass;

readonly class StardustHelper
{
    // Record Separator for JSON-seq format
    private const string RS = "\x1E";

    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?? config('stardust.base_url');
    }

    /**
     * Send a transact command.
     */
    public function transact(string|array $data): Response
    {
        return $this->getRequest($data)
            ->post("$this->baseUrl/commands/transact");
    }

    /**
     * Creates a query.
     */
    public function createQuery(string|array $data, ?int $id = null): Response
    {
        $url = $id ? "$this->baseUrl/queries/$id" : "$this->baseUrl/queries";

        return $this->getRequest($data)
            ->post($url);
    }

    /**
     * Run a query and return the results as an object.
     */
    public function runQuery(int $id, array $options = []): StdClass|array
    {
        $options = array_merge(
            ['query' => ['mode' => 'object']],
            $options
        );
        $associative = ($options['query']['mode'] ?? null) !== 'object';

        $result = $this->getRequest()
            ->withHeader('Accept', 'application/json')
            ->withOptions($options)
            ->post("$this->baseUrl/queries/$id/run");

        return json_decode($result->getBody(), $associative);
    }

    /**
     * Stream query results as JSON-seq format (RFC 7464).
     */
    public function streamQuery(int $id, array $options = []): Generator
    {
        $options = array_merge(
            ['query' => ['mode' => 'object'],
            'stream' => true,
        ], $options);
        $associative = ($options['query']['mode'] ?? null) !== 'object';

        $stream = $this->getRequest()
            ->withHeader('Accept', 'application/json-seq')
            ->withOptions($options)
            ->post("$this->baseUrl/queries/$id/run")
            ->getBody();

        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(128);

            // Strip leading RS if present (RFC 7464 format)
            if (str_starts_with($buffer, self::RS)) {
                $buffer = substr($buffer, 1);
            }

            // Process all complete records in the buffer
            // Records end with newlines
            while (str_contains($buffer, "\n")) {
                $pos = strpos($buffer, "\n");
                $chunk = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (!empty($chunk)) {
                    yield json_decode($chunk, $associative);
                }

                // Strip leading RS if present after processing a record
                if (str_starts_with($buffer, self::RS)) {
                    $buffer = substr($buffer, 1);
                }
            }
        }

        // Handle any remaining data in the buffer
        if (!empty($buffer)) {
            yield json_decode($buffer, $associative);
        }
    }

    /**
     * Creates a mutation.
     */
    public function createMutation(string|array $data, ?int $id = null): Response
    {
        $url = $id ? "$this->baseUrl/mutations/$id" : "$this->baseUrl/mutations";

        return $this->getRequest($data)
            ->post($url);
    }

    /**
     * Creates a reactor.
     */
    public function createReactor(int $mutationId, int $revision = 1, string $title = '', ?int $id = null): Response
    {
        $url = $id ? "$this->baseUrl/reactors/$id" : "$this->baseUrl/reactors";

        return $this->getRequest("
            title '$title'
            mutation {# $mutationId}
            revision $revision
        ")->post($url);
    }

    /**
     * Starts a reactor.
     */
    public function startReactor(int $reactorId): Response
    {
        $url = "$this->baseUrl/reactors/$reactorId/start";

        return $this->getRequest()->post($url);
    }

    /**
     * Stops a reactor.
     */
    public function stopReactor(int $reactorId): Response
    {
        $url = "$this->baseUrl/reactors/$reactorId/stop";

        return $this->getRequest()->post($url);
    }

    /**
     * Restarts a reactor.
     */
    public function restartReactor(int $reactorId): Response
    {
        $url = "$this->baseUrl/reactors/$reactorId/restart";

        return $this->getRequest()->post($url);
    }

    /**
     * Returns a PendingRequest with the appropriate content type based on the data type.
     */
    private function getRequest(string|array|null $data = null): PendingRequest
    {
        $contentType = 'application/ron';
        if (is_array($data)) {
            $data = json_encode($data);
            $contentType = 'application/json';
        }

        $request = Http::withHeader('Accept', 'application/json-seq');
        if ($data !== null) {
            $request = $request->withBody($data, $contentType);
        }

        return $request;
    }
}
