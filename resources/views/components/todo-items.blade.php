<div id="todoItems" class="mb-10">
    <ul class="-mx-1">
        @foreach($todoItems as $item)
            <li class="px-2 py-2 rounded transition-all flex text-md">
                <div class="flex-none w-10 leading-none">
                    <input data-on:change="{{ datastar()->action(['TodosController', 'updateStatus'], ['id' => $item->id, 'status' => $item->status === 'pending' ? 'done' : 'pending']) }}"
                           type="checkbox"
                           @if ($item->status === 'done') checked @endif
                    />
                </div>
                <div class="flex-grow max-w-full">
                    <div class="w-full leading-none">
                        <h3 class="text-md leading-none truncate w-full pr-10 @if ($item->status === 'done')text-gray-300@else text-gray-500 @endif" >
                            @if ($item->status === 'done')
                                <s>{{ $item->title }}</s>
                            @else
                                {{ $item->title }}
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
