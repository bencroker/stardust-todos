<?php

namespace App\Http\Controllers;

use App\Services\StardustHelper;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TodosController extends Controller
{
    private readonly StardustHelper $stardust;

    public function __construct()
    {
        $this->stardust = new StardustHelper(config('stardust.base_url'));
    }

    public function index(): View
    {
        return view('index', [
            'activeCount' => $this->getActiveCount(),
            'todoItems' => $this->getTodoItems(),
            'minutesAgo' => 0,
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

                $newTodoItems = $this->getTodoItems();
                if (json_encode($newTodoItems) !== json_encode($todoItems)) {
                    $todoItems = $newTodoItems;

                    sse()->patchElements(view('index', [
                        'activeCount' => $this->getActiveCount(),
                        'todoItems' => $this->getTodoItems(),
                        'minutesAgo' => 0,
                    ])->render());
                }

                // Sleep for 100ms
                usleep(1000 * 100);
            }
        });
    }

    public function timeTravel(?int $minutesAgo = null): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $minutesAgo = $minutesAgo ?? $signals['minutesAgo'] ?? 0;

        $bindings = [];
        if ($minutesAgo > 0) {
            $datetime = $this->stardust->formatDateTime(now()->subMinutes($minutesAgo));
            $bindings = [
                'with' => [
                    'db' => [
                        'asOf' => ['#utc' => $datetime],
                    ],
                ],
            ];
        }

        $todoItems = $this->stardust->runQuery(
            config('stardust.todo_items_query_id'),
            body: $bindings,
        )->results;

        $html = view('index', [
            'activeCount' => $this->getActiveCount(),
            'todoItems' => $todoItems,
            'minutesAgo' => $minutesAgo,
        ])->render();

        return sse()->getEventStream(function () use ($html) {
            sse()->patchElements($html);
        });
    }

    public function create(): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['newTitle'] ?? null;
        if ($title) {
            $datetime = $this->stardust->formatDateTime(now());
            $this->stardust->transact([
                '#_entity' => [
                    'completable' => 'true',
                    'title' => $title,
                    'status' => 'active',
                    'createdAt' => ['#utc' => $datetime],
                ],
            ]);
        }

        return sse()->getEventStream(function () {
            sse()->patchSignals(['newTitle' => '']);
        });
    }

    public function updateStatus(int $id, string $status): Response
    {
        $this->stardust->transact([
            $id => [
                'status' => $status,
            ],
        ]);

        return response()->noContent();
    }

    public function updateTitle(int $id): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['title'] ?? null;
        if ($title) {
            $this->stardust->transact([
                $id => [
                    'title' => $title,
                ],
            ]);
        }

        return sse()->getEventStream(function () {
            sse()->patchSignals(['editing' => 0]);
        });
    }

    public function delete(int $id): Response
    {
        $this->stardust->transact([
            $id => [
                'status' => 'deleted',
            ],
        ]);

        return response()->noContent();
    }

    public function activateAll(): Response
    {
        $this->stardust->runMutation(config('stardust.activate_all_mutation_id'));

        return response()->noContent();
    }

    public function completeAll(): Response
    {
        $this->stardust->runMutation(config('stardust.complete_all_mutation_id'));

        return response()->noContent();
    }

    public function clearCompleted(): Response
    {
        $this->stardust->runMutation(config('stardust.clear_completed_mutation_id'));

        return response()->noContent();
    }

    private function getActiveCount(): int
    {
//        dd($this->stardust->getEntityById(config('stardust.active_count_entity_id'))->fields[0]);
        return $this->stardust->getEntityById(config('stardust.active_count_entity_id'))->fields[0]->value ?? 0;
    }

    private function getTodoItems(): array
    {
        return $this->stardust->runQuery(config('stardust.todo_items_query_id'))->results;
    }
}
