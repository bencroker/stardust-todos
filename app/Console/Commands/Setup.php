<?php

namespace App\Console\Commands;

use App\Services\StardustHelper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:setup')]
#[Description('Sets up a todo items query.')]
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
        $todoCountPendingId = config('stardust.count_pending_id');
        $todoCountDoneId = config('stardust.count_done_id');

        $response = $this->stardustHelper->transact([
            $todoCountPendingId => ['title' => 'todoCountPending', 'count' => 0],
            $todoCountDoneId => ['title' => 'todoCountDone', 'count' => 0],
        ]);
        if ($response->successful()) {
            $this->info('Counts setup successful!');
        } else {
            $this->error('Counts setup failed! Status: ' . $response->status());
        }
        $this->line($response->body());

        $response = $this->stardustHelper->createQuery("
            find [?id ?title ?status]
            where [
              [?id status ?status]
            ]
        ", config('stardust.mutation_id'));
        if ($response->successful()) {
            $this->info('Mutation setup successful!');
        } else {
            $this->error('Mutation setup failed! Status: ' . $response->status());
        }

        $response = $this->stardustHelper->createMutation("
            query {
                find [?id ?status]
                where [
                  [?id status ?status]
                ]
            patch {
                $todoCountPendingId {
                    count [incr [if [= ?status \"pending\"] 1 -1]]
                }
                $todoCountDoneId {
                    count [incr [if [= ?status \"done\"] 1 -1]]
                }
            }
        ", config('stardust.mutation_id'));
        if ($response->successful()) {
            $this->info('Mutation setup successful!');
        } else {
            $this->error('Mutation setup failed! Status: ' . $response->status());
        }

        $response = $this->stardustHelper->createReactor(config('stardust.mutation_id'), 1, 'todosReactor', config('stardust.reactor_id'));
        if ($response->successful()) {
            $this->info('Reactor setup successful!');
        } else {
            $this->error('Reactor setup failed! Status: ' . $response->status());
        }
    }
}
