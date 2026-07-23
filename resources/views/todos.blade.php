<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Stardust Todos</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body data-init="{{ datastar()->action(['TodosController', 'updates'], [], ['retry' => 'always']) }}">
    @include('components.todo-items', ['todoItems' => $todoItems])
</body>
</html>
