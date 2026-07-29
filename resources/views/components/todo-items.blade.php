<div id="main" class="min-w-screen min-h-screen bg-gray-800 flex items-center justify-center px-5 py-5">
    <div class="w-full mx-auto rounded-lg border border-gray-700 p-8 lg:py-12 lg:px-14 text-gray-300" style="max-width: 800px">
        <div class="mb-10">
            <h1 class="text-2xl font-bold"><i class="mdi mdi-star text-yellow-300 text-3xl leading-none align-bottom"></i>Stardust Todos</h1>
        </div>
        <div class="mb-10">
            <ul class="-mx-1">
                @foreach($todoItems as $item)
                    <li class="px-2 py-2 rounded transition-all flex text-md">
                        <div class="flex-none w-10 leading-none">
                            <input data-on:change="{{ datastar()->action(['TodosController', 'toggleStatus'], ['id' => $item[0], 'currentStatus' => $item[2]]) }}"
                                   type="checkbox"
                                   @if ($item[2] === 'done') checked @endif
                            />
                        </div>
                        <div class="flex-grow max-w-full">
                            <div class="w-full leading-none">
                                <h3 class="text-md leading-none truncate w-full pr-10 @if ($item[2] === 'done')text-gray-300@else text-gray-500 @endif" >
                                    @if ($item[2] === 'done')
                                        <s>{{ $item[1] }}</s>
                                    @else
                                        {{ $item[1] }}
                                    @endif
                                </h3>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <input data-on:change="{{ datastar()->action(['TodosController', 'create']) }}"
                   data-bind:title
                   type="text"
                   class="text-md w-full bg-transparent text-gray-300 leading-none focus:outline-none mb-2"
                   placeholder="New todo..."/>
        </div>
        <div class="flex justify-center">
            <button class="py-1 px-10 border border-gray-800 hover:border-gray-700 rounded leading-none focus:outline-none text-xl" ><i class="mdi mdi-plus"></i></button>
        </div>
    </div>
</div>
