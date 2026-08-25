<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="description" content="A TodoMVC written in Datastar + Stardust." />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge" />
        <title>TodoMVC: Datastar + Stardust</title>
        <link rel="stylesheet" href="/css/index.css" />
        <link rel="stylesheet" href="/css/custom.css" />
    </head>
    <body
        @if ($minutesAgo == 0)
            data-init="{{ datastar()->action(['TodosController', 'updates'], [], ['retry' => 'always']) }}"
        @endif
    >
        <section class="todoapp">
            <header class="header">
                <h1>
                    <a href="/" target="_blank" rel="noopener noreferrer" title="Open app in a separate tab">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24">
                            <path d="M0 0h24v24H0z" fill="none" />
                            <path fill="currentColor" d="M13.07 10.41a5 5 0 0 0 0-5.82A3.4 3.4 0 0 1 15 4a3.5 3.5 0 0 1 0 7a3.4 3.4 0 0 1-1.93-.59M5.5 7.5A3.5 3.5 0 1 1 9 11a3.5 3.5 0 0 1-3.5-3.5m2 0A1.5 1.5 0 1 0 9 6a1.5 1.5 0 0 0-1.5 1.5M16 17v2H2v-2s0-4 7-4s7 4 7 4m-2 0c-.14-.78-1.33-2-5-2s-4.93 1.31-5 2m11.95-4A5.32 5.32 0 0 1 18 17v2h4v-2s0-3.63-6.06-4Z" />
                        </svg>
                    </a>
                    todos
                    <button
                        @if ($minutesAgo)
                            data-on:click="{{ datastar()->action(['TodosController', 'timeTravel'], ['minutesAgo' => 0]) }}"
                            class="active"
                        @else
                            data-on:click="{{ datastar()->action(['TodosController', 'timeTravel'], ['minutesAgo' => 15]) }}"
                        @endif
                        title="Time travel"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 100 100">
                            <path d="M0 0h100v100H0z" fill="none" />
                            <path fill="currentColor" d="M33.523 34.986a20 20 0 0 0-20 20a20 20 0 0 0 20 20a20 20 0 0 0 20-20a20 20 0 0 0-20-20m6.204 4.088a2.5 2.5 0 0 1 2.127 3.781l-6.9 11.952l7.247 4.672a2.5 2.5 0 1 1-2.709 4.203l-9.25-5.961a2.5 2.5 0 0 1-.81-3.352l8.091-14.014a2.5 2.5 0 0 1 2.204-1.28" />
                            <path fill="currentColor" fill-rule="evenodd" d="M2.523 5A2.5 2.5 0 0 0 0 7.5v70.29a2.5 2.5 0 0 0 1.447 2.267l31.666 14.71A2.5 2.5 0 0 0 34.19 95a2.5 2.5 0 0 0 1.032-.232l30.613-14.221l30.613 14.22A2.5 2.5 0 0 0 100 92.5V22.21a2.5 2.5 0 0 0-1.447-2.267L66.887 5.233A2.5 2.5 0 0 0 65.809 5a2.5 2.5 0 0 0-1.03.232L34.166 19.453L3.553 5.233A2.5 2.5 0 0 0 2.523 5m64.428 5.775L95 23.805v64.777L67.322 75.725Zm-2.998.354l.37 64.605l-28.677 13.323l-.062-10.871q-1.498.118-3 .033l.062 10.818L5 76.193V11.418l27.275 12.67l.045 7.908a22 22 0 0 1 3.002.182l-.045-7.727z" color="currentColor" />
                        </svg>
                    </button>
                </h1>
                @if ($minutesAgo)
                    <div class="timeTravel">
                        <select
                            data-bind:minutes-ago
                            data-on:change="{{ datastar()->action(['TodosController', 'timeTravel']) }}"
                        >
                            <option value="1">1 minute ago</option>
                            <option value="15">15 minutes ago</option>
                            <option value="60">1 hour ago</option>
                            <option value="1440">1 day ago</option>
                            <option value="10080">1 week ago</option>
                        </select>
                    </div>
                @endif
                <input
                    data-bind:new-title
                    data-on:keydown="evt.key === 'Enter' && {{ datastar()->action(['TodosController', 'create']) }}"
                    class="new-todo"
                    placeholder="What needs to be done?"
                    autofocus
                />
            </header>
            <main class="main">
                <div class="toggle-all-container">
                    <input
                        @if ($activeCount > 0)
                            data-on:click="evt.preventDefault(); {{ datastar()->action(['TodosController', 'activateAll'], ['status' => 'active']) }}"
                        @else
                            data-on:click="evt.preventDefault(); {{ datastar()->action(['TodosController', 'completeAll']) }}"
                            checked
                        @endif
                        class="toggle-all"
                        id="toggle-all"
                        type="checkbox"
                    />
                    @if ($activeCount > 0)
                        <label data-on:click="{{ datastar()->action(['TodosController', 'completeAll']) }}" for="toggle-all">
                            Mark all as complete
                        </label>
                    @else
                        <label data-on:click="{{ datastar()->action(['TodosController', 'activateAll']) }}" for="toggle-all">
                            Mark all as active
                        </label>
                    @endif
                </div>
                <ul class="todo-list">
                    @foreach($todoItems as $item)
                        <li
                            data-show="$filter === 'all' || $filter === '{{ $item->status }}'"
                            data-class:editing="$editing == {{ $item->id->{'#'} }}"
                            id="todo-{{ $item->id->{'#'} }}"
                            class="@if ($item->status === 'complete') completed @endif"
                        >
                            <div class="view">
                                <input
                                    data-on:click="evt.preventDefault(); {{ datastar()->action(['TodosController', 'updateStatus'], ['id' => $item->id->{'#'}, 'status' => $item->status === 'active' ? 'complete' : 'active']) }}"
                                    @if ($item->status === 'complete') checked @endif
                                    class="toggle"
                                    type="checkbox"
                                >
                                <label data-on:dblclick="$editing = {{ $item->id->{'#'} }}; document.getElementById('edit-{{ $item->id->{'#'} }}').focus()">
                                    {{ $item->title }}
                                </label>
                                <button
                                    data-on:click="{{ datastar()->action(['TodosController', 'delete'], ['id' => $item->id->{'#'}]) }}"
                                    class="destroy"
                                    title="Delete"
                                ></button>
                            </div>
                            <input
                                data-on:click__outside="if ($editing == {{ $item->id->{'#'} }}) { $title = el.value; {{ datastar()->action(['TodosController', 'updateTitle'], ['id' => $item->id->{'#'}]) }} }"
                                data-on:keydown="if (evt.key === 'Enter') { $title = el.value; {{ datastar()->action(['TodosController', 'updateTitle'], ['id' => $item->id->{'#'}]) }} }"
                                value="{{ $item->title }}"
                                id="edit-{{ $item->id->{'#'} }}"
                                class="edit"
                            >
                        </li>
                    @endforeach
                </ul>
            </main>
            <footer class="footer" style="">
                <span class="todo-count">
                    <strong>{{ $activeCount }}</strong>
                    @if ($activeCount == 1) item @else items @endif left
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
                            data-on:click="$filter = 'active'; el.blur()"
                            data-class:selected="$filter === 'active'"
                            href="#"
                        >
                            Active
                        </a>
                    </li>
                    <li>
                        <a
                            data-on:click="$filter = 'complete'; el.blur()"
                            data-class:selected="$filter === 'complete'"
                            href="#"
                        >
                            Completed
                        </a>
                    </li>
                </ul>
                @if ($activeCount > 0)
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
			<p>
                Built with
                <a href="https://data-star.dev/" target="_blank" rel="noopener">Datastar</a> +
                <a href="https://stardustdb.com/" target="_blank" rel="noopener">Stardust</a>
            </p>
            <p>Based on <a href="http://todomvc.com" target="_blank" rel="noopener noreferrer">TodoMVC</a></p>
        </footer>
    </body>
</html>
