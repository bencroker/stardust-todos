<!doctype html>
<html lang="en" data-framework="datastar">
    <head>
        <meta charset="UTF-8" />
        <meta name="description" content="A TodoMVC written in Datastar + Stardust." />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge" />
        <title>TodoMVC: Datastar + Stardust</title>
        <link rel="stylesheet" href="/css/index.css" />
    </head>
    <body data-init="{{ datastar()->action(['TodosController', 'updates'], [], ['retry' => 'always']) }}">
        <section class="todoapp">
            <header class="header">
                <h1>todos</h1>
                <input
                    data-bind:new-title
                    data-on:keydown__window="evt.key === 'Enter' && {{ datastar()->action(['TodosController', 'create']) }}"
                    class="new-todo"
                    placeholder="What needs to be done?"
                    autofocus
                />
            </header>
            <main class="main">
                <div class="toggle-all-container">
                    <input class="toggle-all" id="toggle-all" type="checkbox" />
                    <label for="toggle-all">Mark all as complete</label>
                </div>
                <ul class="todo-list">
                    @foreach($todoItems as $item)
                        <li
                            data-show="$filter === 'all' || $filter === '{{ $item->status }}'"
                            data-class:editing="$editing == {{ $item->id }}"
                            class="@if ($item->status === 'done') completed @endif"
                        >
                            <div class="view">
                                <input
                                    data-on:click="evt.preventDefault(); {{ datastar()->action(['TodosController', 'updateStatus'], ['id' => $item->id, 'status' => $item->status === 'pending' ? 'done' : 'pending']) }}"
                                    @if ($item->status === 'done') checked @endif
                                    class="toggle"
                                    type="checkbox"
                                >
                                <label data-on:dblclick="$editing = {{ $item->id }}; document.getElementById('edit-{{ $item->id }}').focus()">
                                    {{ $item->title }}
                                </label>
                                <button
                                    data-on:click="{{ datastar()->action(['TodosController', 'delete'], ['id' => $item->id]) }}"
                                    class="destroy"
                                    title="Delete"
                                ></button>
                            </div>
                            <input
                                data-on:click__outside="if ($editing == {{ $item->id }}) { $editing = 0; $title = el.value; {{ datastar()->action(['TodosController', 'updateTitle'], ['id' => $item->id]) }} }"
                                value="{{ $item->title }}"
                                id="edit-{{ $item->id }}"
                                class="edit"
                            >
                        </li>
                    @endforeach
                </ul>
            </main>
            <footer class="footer" style="">
                <span class="todo-count">
                    <strong>{{ $pendingCount->count }}</strong>
                    @if ($pendingCount->count == 1) item @else items @endif left
                </span>
                <ul
                    data-signals:filter="'all'"
                    class="filters"
                >
                    <li>
                        <a
                            data-on:click="$filter = 'all'; el.blur()"
                            data-class:selected="$filter === 'all'"
                            class="selected"
                            href="#"
                        >
                            All
                        </a>
                    </li>
                    <li>
                        <a
                            data-on:click="$filter = 'pending'; el.blur()"
                            data-class:selected="$filter === 'pending'"
                            href="#"
                        >
                            Active
                        </a>
                    </li>
                    <li>
                        <a
                            data-on:click="$filter = 'done'; el.blur()"
                            data-class:selected="$filter === 'done'"
                            href="#"
                        >
                            Completed
                        </a>
                    </li>
                </ul>
                @if ($pendingCount->count > 0)
                    <button
                        data-on:click="{{ datastar()->action(['TodosController', 'clearCompleted']) }}"
                        class="clear-completed"
                    >
                        Clear completed
                    </button>
                @endif
            </footer>
        </section>
        <footer class="info">
            <p>Double-click to edit a todo</p>
			<p>Created by Ben Croker</p>
			<p>Built with <a href="https://data-star.dev/">Datastar + Stardust</a></p>
        </footer>
    </body>
</html>
