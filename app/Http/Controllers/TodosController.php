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
            'secondsAgo' => 0,
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
                        'secondsAgo' => 0,
                    ])->render());
                }

                // Sleep for 100ms
                usleep(1000 * 100);
            }
        });
    }

    public function timeTravel(?int $secondsAgo = null): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $secondsAgo = $secondsAgo ?? $signals['secondsAgo'] ?? 0;

        $bindings = [];
        if ($secondsAgo > 0) {
            $datetime = $this->stardust->formatDateTime(now()->subSeconds($secondsAgo));
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
            'secondsAgo' => $secondsAgo,
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
        $entity = $this->stardust->getEntityById(config('stardust.active_count_entity_id'));
        foreach ($entity->fields as $field) {
            if ($field->field === 'count') {
                return $field->value;
            }
        }

        return 0;
    }

    private function getTodoItems(): array
    {
        return $this->stardust->runQuery(config('stardust.todo_items_query_id'))->results;
    }
}
