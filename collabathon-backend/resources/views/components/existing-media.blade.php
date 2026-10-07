@props([
    /** The saved PropertyMedia rows of one kind. Nothing renders when this is empty. */
    'items' => null,
    /** Heading above the grid, e.g. "In the gallery". The count is appended. */
    'title',
    /**
     * Footnote under the grid. Defaults to the line explaining that Remove is deferred,
     * which is true of every use — it is a prop only so a caller can say something more
     * specific.
     */
    'note' => 'Click Remove on an image to delete it when you save.',
])

{{--
    A saved multi-image field, shown back with per-image removal.

    A file input cannot be pre-filled, so without this an edit form looks like no images
    were ever uploaded — which is exactly what happened to unit plans: a listing with
    eighteen of them showed none here, and there was no way to delete one short of
    deleting the listing.

    Ticking the box posts the media id in `remove_media[]` and nothing is deleted until
    the form saves, so a mis-click is undone by leaving without saving.
--}}
@if($items?->isNotEmpty())
    <div>
        <p class="text-[12.5px] font-medium text-ink mb-2">
            {{ $title }}
            <span class="text-ink-3 font-normal">({{ $items->count() }})</span>
        </p>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-2.5">
            @foreach($items as $image)
                {{-- Clicking Remove hides the tile outright — the checkbox stays in the DOM
                     (just visually gone, via x-show) so remove_media[] still posts on Save,
                     which is when the file is actually deleted. --}}
                <div x-data="{ marked: false }" x-show="! marked" class="relative">
                    <input type="checkbox" name="remove_media[]" value="{{ $image->id }}"
                           x-model="marked" class="sr-only">
                    <img src="{{ $image->url ?: \App\Support\FileStorage::url($image->path) }}" alt=""
                         class="w-full aspect-[4/3] object-cover rounded-lg border border-line">
                    {{-- Solid pill, not text floating on the photo — plain red text over a
                         light sky or a white wall was unreadable. --}}
                    <button type="button" @click="marked = true"
                            class="absolute inset-x-1.5 bottom-1.5 py-1 text-[11px] font-medium text-center
                                   text-danger bg-panel rounded-md shadow-card hover:bg-danger-soft transition-colors">
                        Remove
                    </button>
                </div>
            @endforeach
        </div>

        <p class="text-[11.5px] text-ink-3 mt-2">{{ $note }}</p>
    </div>
@endif
