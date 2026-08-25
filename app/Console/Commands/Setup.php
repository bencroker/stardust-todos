<?php

namespace App\Console\Commands;

use App\Services\StardustHelper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:setup')]
#[Description('Creates queries, mutations, and reactors.')]
class Setup extends Command
{
    private StardustHelper $stardustHelper;

    public function handle(): int
    {
        try {
            $this->stardustHelper = new StardustHelper;

            $this->createActiveCountEntity();
            $this->createActiveCountQuery();
            $this->createActiveCountMutation();
            $this->createActiveCountReactor();
            $this->createTodoItemsQuery();
            $this->createActivateAllMutation();
            $this->createCompleteAllMutation();
            $this->createClearCompletedMutation();

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }

    private function createActiveCountEntity(): void
    {
        $activeCountID = config('stardust.active_count_entity_id');

        $this->stardustHelper->transact([
            $activeCountID => [
                'title' => 'activeCount',
                'count' => 0,
            ],
        ]);

        $this->info('Active count entity created.');
    }

    private function createActiveCountQuery(): void
    {
        $entity = config('stardust.active_count_query_id');

        $this->stardustHelper->createQuery(
            data: [
                'find' => [
                    ['count', '?id', '?activeCount'],
                ],
                'where' => [
                    ['?id', 'completable', 'true'],
                    ['?id', 'status', 'active'],
                ],
            ],
            entity: $entity,
            title: 'activeCountQuery',
        );

        $this->info('Active count query created.');
    }

    private function createActiveCountMutation(): void
    {
        $entity = config('stardust.active_count_mutation_id');

        $activeCountID = config('stardust.active_count_entity_id');

        $this->stardustHelper->createMutation(
            data: [
                'query' => [
                    'find' => [
                        ['count', '?id', '?activeCount'],
                    ],
                    'where' => [
                        ['?id', 'completable', 'true'],
                        ['?id', 'status', 'active'],
                    ],
                ],
                'patch' => [
                    $activeCountID => [
                        'count' => '?activeCount',
                    ],
                ],
            ],
            entity: $entity,
            title: 'activeCountMutation',
        );

        $this->info('Active count mutation created.');
    }

    private function createActiveCountReactor(): void
    {
        $reactor = config('stardust.active_count_reactor_id');

        $mutation = config('stardust.active_count_mutation_id');

        $this->stardustHelper->createReactor(
            mutation: $mutation,
            title: 'activeCountReactor',
            entity: $reactor,
        );
        $this->stardustHelper->startReactor($reactor);

        $this->info('Active count reactor started.');
    }

    private function createTodoItemsQuery(): void
    {
        $entity = config('stardust.todo_items_query_id');

        $this->stardustHelper->createQuery(
            data: [
                'find' => [
                    '?id',
                    '?title',
                    '?status',
                    '?createdAt',
                ],
                'where' => [
                    ['?id', 'completable', 'true'],
                    ['?id', 'title', '?title'],
                    ['?id', 'status', '?status'],
                    ['!=', '?status', 'deleted'],
                    ['?id', 'createdAt', '?createdAt'],
                ],
                'orderBy' => [
                    ['?createdAt', 'asc'],
                ],
                'project' => [
                    'root' => '?id',
                    'fields' => [
                        'id' => '?id',
                        'title' => '?title',
                        'status' => '?status',
                    ],
                ],
            ],
            entity: $entity,
            title: 'todoItemsQuery',
        );

        $this->info('Todo items query created.');
    }

    private function createActivateAllMutation(): void
    {
        $entity = config('stardust.activate_all_mutation_id');

        $this->storeStatusMutation(
            entity: $entity,
            title: 'activateAllMutation',
            currentStatus: 'complete',
            newStatus: 'active',
        );

        $this->info('Activate all mutation created.');
    }

    private function createCompleteAllMutation(): void
    {
        $entity = config('stardust.complete_all_mutation_id');

        $this->storeStatusMutation(
            entity: $entity,
            title: 'completeAllMutation',
            currentStatus: 'active',
            newStatus: 'complete',
        );

        $this->info('Complete all mutation created.');
    }

    private function createClearCompletedMutation(): void
    {
        $entity = config('stardust.clear_completed_mutation_id');

        $this->storeStatusMutation(
            entity: $entity,
            title: 'clearCompletedMutation',
            currentStatus: 'complete',
            newStatus: 'deleted',
        );

        $this->info('Clear completed mutation created.');
    }

    private function storeStatusMutation(
        int $entity,
        string $title,
        string $currentStatus,
        string $newStatus,
    ): void {
        $this->stardustHelper->createMutation(
            data: [
                'query' => [
                    'find' => [
                        '?id',
                    ],
                    'where' => [
                        ['?id', 'completable', 'true'],
                        ['?id', 'status', $currentStatus],
                    ],
                ],
                'patch' => [
                    '?id' => [
                        'status' => $newStatus,
                    ],
                ],
            ],
            entity: $entity,
            title: $title,
        );
    }
}
