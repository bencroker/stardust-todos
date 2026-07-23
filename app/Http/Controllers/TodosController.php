<?php

namespace App\Http\Controllers;

use Generator;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TodosController extends Controller
{
    public function index(): View
    {
        $result = $this->getReactorResponse([
            'query' => [
                'mode' => 'object',
                'max' => 1,
            ],
        ])->json();

        return view('todos', ['todoItems' => $result]);
    }

    public function updates(): StreamedResponse
    {
        return sse()->getEventStream(function () {
            foreach ($this->parseReactorResults() as $result) {
                sse()->patchElements(view('components.todo-items', ['todoItems' => $result])->render());
            }
        });
    }

    public function create(): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['title'] ?? null;
        if ($title) {
            $this->transact(['#_entity' => [
                'title' => $title,
                'status' => 'pending',
            ]]);
        }

        return sse()->getEventStream(function() {
            sse()->patchSignals(['title' => '']);
        });
    }

    public function toggleStatus(int $id, string $currentStatus): StreamedResponse
    {
        $status = $currentStatus === 'pending' ? 'done' : 'pending';
        $this->transact([$id => [
            'status' => $status,
        ]]);

        return sse()->getEventStream();
    }


    private function parseReactorResults(): Generator
    {
        $stream = $this->getReactorResponse([
            'stream' => true,
            'query' => [
                'mode' => 'object',
            ],
        ])->getBody();

        while (!$stream->eof()) {
            $line = trim(Utils::readLine($stream));
            if ($line !== '') {
                yield json_decode($line, true);
            }
        }
        $stream->close();
    }

    private function getReactorResponse(array $options = []): Response
    {
        return Http::withOptions($options)
            ->withHeaders(['Accept' => 'application/x-ndjson'])
            ->get(config('stardust.base_url') . '/reactors/' . config('stardust.todo_items_reactor_id') . '/results');
    }

    private function transact(array $data): void
    {
        Http::withBody(json_encode($data))
            ->post(config('stardust.base_url') . '/commands/transact');
    }
}
