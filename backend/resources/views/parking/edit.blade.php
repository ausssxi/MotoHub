<x-layout>
    <x-slot:title>{{ $parking->name }} を編集 | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイク駐車場「{{ $parking->name }}」の情報を編集します。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.breadcrumb-parking :parking="$parking" currentName="編集" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #create-map { height: 350px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ asset('js/parking/create.js') }}?v={{ filemtime(public_path('js/parking/create.js')) }}"></script>
        <script>
            function markImageForDeletion(btn, imageId) {
                const wrapper = btn.closest('[data-image-id]');
                const container = document.getElementById('delete-images-container');

                // すでに削除マーク済みなら解除
                const existing = container.querySelector(`input[value="${imageId}"]`);
                if (existing) {
                    existing.remove();
                    wrapper.style.opacity = '1';
                    wrapper.querySelector('button').innerHTML = '&times;';
                    updateImageCount();
                    return;
                }

                // 削除マーク
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_images[]';
                input.value = imageId;
                container.appendChild(input);
                wrapper.style.opacity = '0.3';
                wrapper.querySelector('button').innerHTML = '↩';
                wrapper.querySelector('button').classList.add('opacity-100');
                updateImageCount();
            }

            function updateImageCount() {
                const existingCount = document.querySelectorAll('#existing-images [data-image-id]').length;
                const deletedCount = document.querySelectorAll('#delete-images-container input').length;
                const newCount = document.getElementById('image-input')?.files?.length || 0;
                const total = existingCount - deletedCount + newCount;
                const countEl = document.getElementById('image-count');
                if (countEl) {
                    countEl.textContent = `${total}/5`;
                    countEl.classList.toggle('text-red-500', total >= 5);
                }
            }
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="overflow-x-auto text-xs font-bold text-gray-400 mb-6 scrollbar-hide" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('parking.show', $parking) }}" class="hover:text-gray-600 transition-colors">{{ Str::limit($parking->name, 20) }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">編集</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2 flex items-center gap-2">
                    <i data-lucide="pencil" class="w-5 h-5 text-green-600"></i>
                    駐車場情報を編集
                </h1>
                <p class="text-xs text-gray-500 mb-6">「{{ $parking->name }}」の情報を更新します。</p>

                @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-6">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form action="{{ route('parking.update', $parking) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('parking._form', ['parking' => $parking])

                    <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded-xl hover:bg-green-700 transition text-sm">
                        変更を保存する
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layout>
