<?php

namespace App\Http\Controllers;

use App\Services\StardustHelper;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TodosController extends Controller
{
    private readonly StardustHelper $stardustHelper;

    public function __construct()
    {
        $this->stardustHelper = new StardustHelper(config('stardust.base_url'));
    }

    public function index(): View
    {
        $aggregates = $this->stardustHelper->runQuery(config('stardust.aggregates_query_id'))
            ->results[0];

        $todoItems = $this->stardustHelper->runQuery(config('stardust.todo_items_query_id'))
            ->results;

        return view('todos', [
            'aggregates' => $aggregates,
            'todoItems' => $todoItems,
        ]);
    }

    public function aggregates(): StreamedResponse
    {
        return sse()->getEventStream(function () {
            foreach ($this->stardustHelper->streamQuery(config('stardust.aggregates_query_id')) as $result) {
                $aggregates = $result->results[0];
                sse()->patchElements(view('components.aggregates', ['aggregates' => $aggregates])->render());
            }
        });
    }

    public function todoItems(): StreamedResponse
    {
        return sse()->getEventStream(function () {
            foreach ($this->stardustHelper->streamQuery(config('stardust.todo_items_query_id')) as $result) {
                $todoItems = $result->results;
                sse()->patchElements(view('components.todo-items', ['todoItems' => $todoItems])->render());
            }
        });
    }

    public function create(): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['title'] ?? null;
        if ($title) {
            $this->stardustHelper->transact("
                #_entity {
                    title $title
                    status pending
                    counted false
                }
            ");
        }

        return sse()->getEventStream(function() {
            sse()->patchSignals(['title' => '']);
        });
    }

    public function toggleStatus(int $id, string $currentStatus): StreamedResponse
    {
        $status = $currentStatus === 'pending' ? 'done' : 'pending';
        $response = $this->stardustHelper->transact([
            $id => [
                'status' => $status,
            ]
        ]);

        if (!$response->successful()) {
            dd($response->getReasonPhrase());
        }
        return sse()->getEventStream();
    }
}
