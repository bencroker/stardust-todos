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
        $aggregates = $this->stardustHelper->runQuery(config('stardust.aggregates_query_id'))->results[0];
        $todoItems = $this->stardustHelper->runQuery(config('stardust.todo_items_query_id'))->results;

        return view('todos', [
            'aggregates' => $aggregates,
            'todoItems' => $todoItems,
        ]);
    }

    public function updates(): StreamedResponse
    {
        return sse()->getEventStream(function () {
            $aggregates = [];
            $todoItems = [];

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $result = $this->stardustHelper->runQuery(config('stardust.aggregates_query_id'));
                if (json_encode($result->results[0]) !== json_encode($aggregates)) {
                    $aggregates = $result->results[0];
                    sse()->patchElements(view('components.aggregates', ['aggregates' => $aggregates])->render());
                }

                $result = $this->stardustHelper->runQuery(config('stardust.todo_items_query_id'));
                if (json_encode($result->results) !== json_encode($todoItems)) {
                    $todoItems = $result->results;
                    sse()->patchElements(view('components.todo-items', ['todoItems' => $todoItems])->render());
                }

                usleep(1000 * 100);
            }
        });
    }

    public function create(): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['title'] ?? null;
        if ($title) {
            $datetime = $this->stardustHelper->formatDateTime(now());
            $this->stardustHelper->transact("
                #_entity {
                    title $title
                    status pending
                    createdAt {#utc $datetime}
                }
            ");
        }

        return sse()->getEventStream(function() {
            sse()->patchSignals(['title' => '']);
        });
    }

    public function updateStatus(int $id, string $status): StreamedResponse
    {
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
