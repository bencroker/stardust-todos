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
        $result = $this->stardustHelper->runQuery(config('stardust.query_id'));

        return view('todos', ['todoItems' => $result]);
    }

    public function updates(): StreamedResponse
    {
        return sse()->getEventStream(function () {
            foreach ($this->stardustHelper->streamQuery(config('stardust.query_id')) as $result) {
                sse()->patchElements(view('components.todo-items', ['todoItems' => $result])->render());
            }
        });
    }

    public function create(): StreamedResponse
    {
        $signals = datastar()->readSignals();
        $title = $signals['title'] ?? null;
        if ($title) {
            $this->stardustHelper->transact(['#_entity' => [
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
