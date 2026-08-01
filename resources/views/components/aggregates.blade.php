<div id="aggregates" class="flex flex-col gap-4">
    <div class="flex gap-2 items-center">
        <span class="text-gray-300">Total: {{ $aggregates->total }}</span>
        <span class="text-gray-300">Pending: {{ $aggregates->pending }}</span>
        <span class="text-gray-300">Done: {{ $aggregates->total - $aggregates->pending }}</span>
    </div>
</div>
