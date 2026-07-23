<!doctype html>
<html lang="en"
      data-match-media:dark="prefers-color-scheme: dark"
      data-attr:data-theme="$dark ? 'dark' : 'light'"
>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Stardust Todo List Demo</title>
    <script type="module" src="/js/datastar-pro.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body data-init="{{ datastar()->action(['TodosController', 'updates'], [], ['retry' => 'always']) }}">
    @include('components.todo-items', ['todoItems' => $todoItems])
</body>

</html>
