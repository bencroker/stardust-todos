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
            $pendingCountId = config('stardust.pending_count_id');

        $response = $this->stardustHelper->transact("
            $pendingCountId { title pendingCount count 0 }
        ");
        if ($response->successful()) {
            $this->info('Pending count setup successful!');
        } else {
            $this->error('Pending count setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createQuery("
            title pendingCountQuery
            query {
                find [?pending]
                where [
                    [$pendingCountId count ?pending]
                ]
            }
        ", config('stardust.pending_count_query_id'));
        if ($response->successful()) {
            $this->info('Pending count query setup successful!');
        } else {
            $this->error('Pending count query setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

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
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createMutation("
            title clearCompletedMutation
            query {
                find [?id]
                where [
                  [?id status done]
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
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createMutation("
            title pendingCountMutation
            query {
                find [?id ?status]
                where [
                  [?id status ?status]
                ]
            }
            patch {
                $pendingCountId {
                   count [incr [if [= ?status pending] 1 -1]]
                }
            }
        ", config('stardust.pending_count_mutation_id'));
        if ($response->successful()) {
            $this->info('Pending count mutation setup successful!');
        } else {
            $this->error('Pending count mutation setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createReactor(config('stardust.pending_count_mutation_id'), 1, 'pendingCountReactor', config('stardust.pending_count_reactor_id'));
        if ($response->successful()) {
            $this->info('Pending count reactor setup successful!');
        } else {
            $this->error('Pending count reactor setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->startReactor(config('stardust.pending_count_reactor_id'));
        if ($response->successful()) {
            $this->info('Pending count reactor started successfully!');
        } else {
            $this->error('Pending count reactor start failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));
    }
}
