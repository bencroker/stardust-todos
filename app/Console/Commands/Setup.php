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
        $aggregatesId = config('stardust.aggregates_id');

        $response = $this->stardustHelper->transact("
            $aggregatesId { title aggregates pending 0 total 0 }
        ");
        if ($response->successful()) {
            $this->info('Aggregates setup successful!');
        } else {
            $this->error('Aggregates setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createQuery("
            title aggregatesQuery
            query {
                find [?pending ?total]
                where [
                    [$aggregatesId pending ?pending]
                    [$aggregatesId total ?total]
                ]
            }
        ", config('stardust.aggregates_query_id'));
        if ($response->successful()) {
            $this->info('Aggregates query setup successful!');
        } else {
            $this->error('Aggregates query setup failed! ' . $response->getReasonPhrase());
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
            title aggregatesDoneMutation
            query {
                find [?status]
                where [
                  [?id status ?status]
                ]
            }
            patch {
                $aggregatesId {
                    pending [incr [if [= ?status pending] 1 -1]]
                }
            }
        ", config('stardust.aggregates_pending_mutation_id'));
        if ($response->successful()) {
            $this->info('Aggregates pending mutation setup successful!');
        } else {
            $this->error('Aggregates pending mutation setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createMutation("
            title aggregatesTotalMutation
            query {
                find [?id]
                where [
                  [?id createdAt ?createdAt]
                ]
            }
            patch {
                $aggregatesId {
                    total [incr 1]
                }
            }
        ", config('stardust.aggregates_total_mutation_id'));
        if ($response->successful()) {
            $this->info('Aggregates total mutation setup successful!');
        } else {
            $this->error('Aggregates total mutation setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createReactor(config('stardust.aggregates_pending_mutation_id'), config('stardust.aggregates_pending_mutation_revision'), 'aggregatesPendingReactor', config('stardust.aggregates_pending_reactor_id'));
        if ($response->successful()) {
            $this->info('Aggregates pending reactor setup successful!');
        } else {
            $this->error('Aggregates pending reactor setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->createReactor(config('stardust.aggregates_total_mutation_id'), config('stardust.aggregates_total_mutation_revision'), 'aggregatesTotalReactor', config('stardust.aggregates_total_reactor_id'));
        if ($response->successful()) {
            $this->info('Aggregates total reactor setup successful!');
        } else {
            $this->error('Aggregates total reactor setup failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->startReactor(config('stardust.aggregates_pending_reactor_id'));
        if ($response->successful()) {
            $this->info('Aggregates pending reactor started successfully!');
        } else {
            $this->error('Aggregates pending reactor start failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));

        $response = $this->stardustHelper->startReactor(config('stardust.aggregates_total_reactor_id'));
        if ($response->successful()) {
            $this->info('Aggregates total reactor started successfully!');
        } else {
            $this->error('Aggregates total reactor start failed! ' . $response->getReasonPhrase());
        }
        $this->line(trim($response->body()));
    }
}
