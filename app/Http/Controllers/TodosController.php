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
        $activeCount = $this->stardustHelper->getEntity(config('stardust.active_count_id'));
        $todoItems = $this->stardustHelper->runQuery(config('stardust.todo_items_query_id'))->results;

        return view('index', [
            'activeCount' => $activeCount,
            'todoItems' => $todoItems,
        ]);
    }

    public function updates(): StreamedResponse
    {
        return sse()->getEventStream(function () {
            $todoItems = [];

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $newTodoItems = $this->stardustHelper->runQuery(config('stardust.todo_items_query_id'))->results;
                if (json_encode($newTodoItems) !== json_encode($todoItems)) {
                    $todoItems = $newTodoItems;
                    $activeCount = $this->stardustHelper->getEntity(config('stardust.active_count_id'));

                    sse()->patchElements(view('index', ['activeCount' => $activeCount, 'todoItems' => $todoItems])->render());
                }

                // Sleep for 100ms
                usleep(1000 * 100);
            }
        });
    }

    public function create(): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['newTitle'] ?? null;
        if ($title) {
            $datetime = $this->stardustHelper->formatDateTime(now());
            $this->stardustHelper->transact("
                #_entity {
                    title $title
                    status active
                    createdAt {#utc $datetime}
                }
            ");
        }

        return sse()->getEventStream(function() {
            sse()->patchSignals(['newTitle' => '']);
        });
    }

    public function updateStatus(int $id, string $status): StreamedResponse
    {
        $response = $this->stardustHelper->transact([
            $id => [
                'status' => $status,
            ],
        ]);

        if (!$response->successful()) {
            dd($response->getReasonPhrase());
        }
        return sse()->getEventStream();
    }

    public function updateTitle(int $id): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['title'] ?? null;
        if ($title) {
            $response = $this->stardustHelper->transact([
                $id => [
                    'title' => $title,
                ],
            ]);

            if (!$response->successful()) {
                dd($response->getReasonPhrase());
            }
        }

        return sse()->getEventStream();
    }

    public function delete(int $id): StreamedResponse
    {
        $response = $this->stardustHelper->transact([
            $id => [
                'status' => 'deleted',
            ],
        ]);

        if (!$response->successful()) {
            dd($response->getReasonPhrase());
        }
        return sse()->getEventStream();
    }

    public function activateAll(): StreamedResponse
    {
        $this->stardustHelper->runMutation(config('stardust.activate_all_mutation_id'), []);

        return sse()->getEventStream();
    }

    public function completeAll(): StreamedResponse
    {
        $this->stardustHelper->runMutation(config('stardust.complete_all_mutation_id'), []);

        return sse()->getEventStream();
    }

    public function clearCompleted(): StreamedResponse
    {
        $this->stardustHelper->runMutation(config('stardust.clear_completed_mutation_id'), []);

        return sse()->getEventStream();
    }
}
