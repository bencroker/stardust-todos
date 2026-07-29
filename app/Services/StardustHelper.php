<?php

namespace App\Services;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

readonly class StardustHelper
{
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
    public function runQuery(int $id): array
    {
        $result = $this->getRequest()
            ->withOptions(['query' => ['mode' => 'object']])
            ->post("$this->baseUrl/queries/$id/run");

        return $result['results'] ?? [];
    }

    /**
     * Stream query results as JSON-seq format (RFC 7464).
     */
    public function streamQuery(int $id): Generator
    {
        $stream = $this->getRequest()
            ->withHeader('Accept', 'application/json-seq')
            ->withOptions([
                'query' => ['mode' => 'object'],
                'stream' => true,
            ])
            ->post("$this->baseUrl/queries/$id/run")
            ->getBody();

        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(8192);

            foreach ($this->decodeJsonSeqBuffer($buffer, false) as $result) {
                yield $result;
            }
        }

        // Process any remaining data in buffer before closing
        foreach ($this->decodeJsonSeqBuffer($buffer, true) as $result) {
            yield $result;
        }

        $stream->close();
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

        return $this->getRequest([
            'mutationId' => $mutationId,
            'revision' => $revision,
            'title' => $title,
        ])->post($url);
    }

    /**
     * Returns a PendingRequest with the appropriate content type based on the data type (string or array).
     */
    private function getRequest(string|array|null $data = null): PendingRequest
    {
        $contentType = 'application/ron';
        if (is_array($data)) {
            $data = json_encode($data);
            $contentType = 'application/json';
        }

        $request = Http::withHeader('Accept', 'application/json');
        if ($data !== null) {
            $request = $request->withBody($data, $contentType);
        }

        return $request;
    }

    /**
     * Decode JSON-seq formatted buffer and yield results.
     * RFC 7464 compliant JSON Text Sequence parser.
     * Based on https://github.com/networkteam/json-seq/blob/master/src/StringDecoder.php
     */
    private function decodeJsonSeqBuffer(string &$buffer, bool $isFinal): Generator
    {
        $RS = "\x1E";
        $lastPos = 0;
        $length = strlen($buffer);

        while ($lastPos < $length && ($nextPos = strpos($buffer, $RS, $lastPos)) !== false) {
            $nextNextPos = strpos($buffer, $RS, $nextPos + 1);

            if ($nextNextPos === false) {
                if (!$isFinal) {
                    // Incomplete record, keep in buffer
                    break;
                }
                $nextNextPos = $length;
            }

            // RFC7464 2.1: Multiple consecutive RS octets are ignored
            if ($nextNextPos === $nextPos + 1) {
                $lastPos = $nextNextPos;
                continue;
            }

            $jsonText = substr($buffer, $nextPos + 1, $nextNextPos - ($nextPos + 1));
            $data = json_decode($jsonText, true);

            if ($data !== null && isset($data['results'])) {
                yield $data['results'];
            }

            $lastPos = $nextNextPos;
        }

        $buffer = substr($buffer, $lastPos);
    }
}


