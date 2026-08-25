<?php

namespace App\Services;

use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use UnexpectedValueException;

final class SseHelper
{
    private const int MAX_BUFFER_BYTES = 8 * 1024 * 1024;

    /**
     * Yield the data payload from each SSE event.
     *
     * @return Generator<int, string>
     */
    public function data(ResponseInterface $response): Generator
    {
        if (! str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/event-stream')) {
            throw new UnexpectedValueException(
                'The response is not an SSE stream.',
            );
        }

        $stream = $response->getBody();

        try {
            yield from $this->streamData($stream);
        } finally {
            $stream->close();
        }
    }

    /**
     * @return Generator<int, string>
     */
    private function streamData(StreamInterface $stream): Generator
    {
        $buffer = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(4096);
            if ($chunk === '') {
                continue;
            }

            if (strlen($buffer) + strlen($chunk) > self::MAX_BUFFER_BYTES) {
                throw new RuntimeException(
                    'The SSE stream exceeded the buffer limit.',
                );
            }

            $buffer .= $chunk;

            while (($event = $this->extractEvent($buffer)) !== null) {
                $data = $this->eventData($event);
                if ($data !== null) {
                    yield $data;
                }
            }
        }

        if (trim($buffer) !== '') {
            $data = $this->eventData($buffer);
            if ($data !== null) {
                yield $data;
            }
        }
    }

    private function extractEvent(string &$buffer): ?string
    {
        $position = null;
        $delimiterLength = 0;

        foreach (["\r\n\r\n", "\n\n", "\r\r"] as $delimiter) {
            $found = strpos($buffer, $delimiter);
            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
                $delimiterLength = strlen($delimiter);
            }
        }

        if ($position === null) {
            return null;
        }

        $event = substr($buffer, 0, $position);
        $buffer = substr($buffer, $position + $delimiterLength);

        return $event;
    }

    private function eventData(string $event): ?string
    {
        $data = [];

        foreach (preg_split("/\r\n|\r|\n/", $event) ?: [] as $line) {
            if (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5), ' ');
            }
        }

        return $data === [] ? null : implode("\n", $data);
    }
}
