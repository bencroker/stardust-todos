<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Stardust Todos</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body data-init="
    {{ datastar()->action(['TodosController', 'aggregates'], [], ['retry' => 'always']) }};
    {{ datastar()->action(['TodosController', 'todoItems'], [], ['retry' => 'always']) }}
">
    <div class="min-w-screen min-h-screen bg-gray-800 flex items-center justify-center px-5 py-5">
        <div class="w-full mx-auto rounded-lg border border-gray-700 p-8 lg:py-12 lg:px-14 text-gray-300" style="max-width: 800px">
            <div class="mb-10">
                <h1 class="text-2xl font-bold"><i class="mdi mdi-star text-yellow-300 text-3xl leading-none align-bottom"></i>Stardust Todos</h1>
            </div>
            @include('components.aggregates', ['aggregates' => $aggregates])
            @include('components.todo-items', ['todoItems' => $todoItems])
        </div>
    </div>
</body>
</html>
