<?php

namespace App\Console\Commands;

use App\Services\StardustHelper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:setup')]
#[Description('Sets up queries, mutations, and reactors.')]
class Setup extends Command
{
    private readonly StardustHelper $stardustHelper;

    public function __construct()
    {
        parent::__construct();

        $this->stardustHelper = new StardustHelper(config('stardust.base_url'));
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
            $activeCountId = config('stardust.active_count_id');

        $response = $this->stardustHelper->transact("
            $activeCountId { title activeCount count 0 }
        ");
        if ($response->successful()) {
            $this->info('Active count setup successful!');
        } else {
            $this->error('Active count setup failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->createQuery("
            title activeCountQuery
            query {
                find [?active]
                where [
                    [$activeCountId count ?active]
                ]
            }
        ", config('stardust.active_count_query_id'));
        if ($response->successful()) {
            $this->info('Active count query setup successful!');
        } else {
            $this->error('Active count query setup failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->createMutation("
            title activeCountMutation
            query {
                find [[count ?id]]
                where [
                  [?id status active]
                ]
            }
            patch {
                $activeCountId {
                   count ?id
                }
            }
        ", config('stardust.active_count_mutation_id'));
        if ($response->successful()) {
            $this->info('Active count mutation setup successful!');
        } else {
            $this->error('Active count mutation setup failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->createReactor(config('stardust.active_count_mutation_id'), 1, 'activeCountReactor', config('stardust.active_count_reactor_id'));
        if ($response->successful()) {
            $this->info('Active count reactor setup successful!');
        } else {
            $this->error('Active count reactor setup failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->startReactor(config('stardust.active_count_reactor_id'));
        if ($response->successful()) {
            $this->info('Active count reactor started successfully!');
        } else {
            $this->error('Active count reactor start failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->createQuery("
            title todoItemsQuery
            query {
                find [?id ?title ?status ?createdAt]
                where [
                    [?id title ?title]
                    [?id status ?status]
                    [?id createdAt ?createdAt]
                ]
                orderBy [[?createdAt asc]] 
            }
        ", config('stardust.todo_items_query_id'));
        if ($response->successful()) {
            $this->info('Todo items query setup successful!');
        } else {
            $this->error('Todo items query setup failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->createMutation("
            title activateAllMutation
            query {
                find [?id]
                where [
                  [?id status complete]
                ]
            }
            patch {
                ?id {
                    status active
                }
            }
        ", config('stardust.activate_all_mutation_id'));
        if ($response->successful()) {
            $this->info('Activate all mutation setup successful!');
        } else {
            $this->error('Activate all mutation setup failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->createMutation("
            title completeAllMutation
            query {
                find [?id]
                where [
                  [?id status active]
                ]
            }
            patch {
                ?id {
                    status complete
                }
            }
        ", config('stardust.complete_all_mutation_id'));
        if ($response->successful()) {
            $this->info('Complete all mutation setup successful!');
        } else {
            $this->error('Complete all mutation setup failed! ' . $response->getReasonPhrase());
        }

        $response = $this->stardustHelper->createMutation("
            title clearCompletedMutation
            query {
                find [?id]
                where [
                  [?id status complete]
                ]
            }
            patch {
                ?id {
                    status deleted
                }
            }
        ", config('stardust.clear_completed_mutation_id'));
        if ($response->successful()) {
            $this->info('Clear completed mutation setup successful!');
        } else {
            $this->error('Clear completed mutation setup failed! ' . $response->getReasonPhrase());
        }
    }
}
