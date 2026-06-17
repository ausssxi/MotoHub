{{-- 記録(整備/カスタム)の添付写真（private・owner-only配信）。$myBike, $record を渡す。 --}}
@if(config('garage.record_photos_enabled'))
<div class="mt-2 flex flex-wrap items-center gap-2">
    @foreach($record->images as $img)
    <div class="relative group/photo">
        <a href="{{ route('mybikes.records.images.show', [$myBike->id, $record->id, $img->id]) }}" target="_blank" rel="noopener">
            <img src="{{ route('mybikes.records.images.show', [$myBike->id, $record->id, $img->id]) }}" alt="記録写真" class="w-14 h-14 object-cover rounded-lg border border-gray-100 bg-gray-50" loading="lazy" decoding="async">
        </a>
        <form action="{{ route('mybikes.records.images.destroy', [$myBike->id, $record->id, $img->id]) }}" method="POST" onsubmit="return confirm('この写真を削除しますか？');" class="absolute -top-1.5 -right-1.5 opacity-0 group-hover/photo:opacity-100 transition-opacity">
            @csrf @method('DELETE')
            <button type="submit" class="w-5 h-5 bg-black/60 hover:bg-red-600 text-white rounded-full flex items-center justify-center" aria-label="写真を削除"><i data-lucide="x" class="w-3 h-3"></i></button>
        </form>
    </div>
    @endforeach
    @if($record->images->count() < (int) config('garage.max_record_images'))
    <form action="{{ route('mybikes.records.images.store', [$myBike->id, $record->id]) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label class="w-14 h-14 rounded-lg border-2 border-dashed border-gray-200 hover:border-orange-300 flex items-center justify-center cursor-pointer text-gray-300 hover:text-orange-400" title="写真を追加">
            <i data-lucide="plus" class="w-5 h-5"></i>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.submit()">
        </label>
    </form>
    @endif
</div>
@endif
