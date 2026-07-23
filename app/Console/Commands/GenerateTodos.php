<?php

namespace App\Console\Commands;

use App\Services\StardustFormatter;
use App\Services\StardustHelper;
use Faker\Factory as Faker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-todos {n=1}')]
#[Description('Generates one or more todos.')]
class GenerateTodos extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $faker = Faker::create();

        $body = [
            'todoListId' => ['#' => 120],
            'status' => 'pending',
        ];

        $n = $this->argument('n');
        $startTime = microtime(true);

        for ($i = 0; $i < $n; $i++) {
            $body['title'] = $faker->sentence();

            $iterationStartTime = microtime(true);
            $todoId = StardustHelper::generateEntity($body);
            $iterationTime = (microtime(true) - $iterationStartTime) * 1000;
            $this->info('Todo generated successfully: ' . $todoId . ' (' . round($iterationTime, 2) . 'ms)');

            // Random delay between 100ms and 1s
            //usleep(rand(100, 1000) * 1000);
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $this->info('');
        $this->info('Total time: ' . round($totalTime / 1000, 2) . 's');
        $this->info('Average time per todo: ' . round($totalTime / $n, 2) . 'ms');
    }
}
