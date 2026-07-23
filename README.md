# Stardust Todos

Minimal Laravel-Datastar-Stardust todo app.

Install composer dependencies and publish the public assets:

```bash
composer install

php artisan vendor:publish --tag=public
```

The app expects Stardust to be running at `http://localhost:1981`. You can change this in the `.env` file.

```
STARDUST_BASE_URL=http://localhost:1981
```

Run Stardust at port `1981` with the following command:

```
./stardust --port 1981 --db data/todo.stardust
```

The app is configured to run at `http://stardust-todos.test`. You can change this in the `.env` file.

```
APP_URL=http://stardust-todos.test
```
