# Stardust Todos

A minimal Laravel-Datastar-Stardust todo app. 

The purpose of this app is to show how little application logic is required in a stock Laravel app when using Stardust as the data engine. The [`Setup`](https://github.com/bencroker/stardust-todos/blob/main/app/Console/Commands/Setup.php) console command creates a reactor idempotently using a fixed entity ID. The [`TodosController`](https://github.com/bencroker/stardust-todos/blob/main/app/Http/Controllers/TodosController.php) contains the logic to fetch (current state on page load), stream, create and update todo items. The frontend is intentionally minimal.

PHP doesn’t have good support for concurrent requests, so requests intentionally time out after 30 seconds. Datastar reconnects automatically when the [`retry` option is set to `always`](https://github.com/bencroker/stardust-todos/blob/main/resources/views/todos.blade.php), which allows the app to stream indefinitely.

## Usage 

Install composer dependencies, publish the public assets, and run the setup command to create a reactor.

```bash
composer install

php artisan vendor:publish --tag=public

php artisan app:setup
```

Run Stardust at port `1980` with the following command.

```
./stardust --port 1980 --db data/todo.stardust
```

The app expects Stardust to be running at `http://localhost:1980`. You can change this in the `.env` file.

```
STARDUST_BASE_URL=http://localhost:1980
```

The app is configured to run at `http://stardust-todos.test`. You can change this in the `.env` file.

```
APP_URL=http://stardust-todos.test
```
