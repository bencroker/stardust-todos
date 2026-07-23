<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:setup')]
#[Description('Sets up a todo items reactor.')]
class Setup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Http::withBody("
            title todoItemsReactor
            find [?id ?title ?status]
            where [
              [?id title ?title]
              [?id status ?status]
            ]
        ", 'application/ron')
            ->post(config('stardust.base_url') . '/reactors');
    }
}
