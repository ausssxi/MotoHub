<x-layout>
    <x-slot:title>愛車管理 - MotoHub</x-slot:title>
    <x-slot:navigation><x-navigation :showSearch="false" /></x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2">
                    <i data-lucide="wrench" class="w-6 h-6 text-blue-600"></i>
                    愛車ガレージ
                </h1>
                <a href="{{ route('dashboard') }}" class="text-xs font-bold text-gray-400 hover:text-black transition-colors">
                    マイページへ戻る
                </a>
            </div>

            {{-- 愛車リスト --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-12">
                @foreach($myBikes as $bike)
                    <a href="{{ route('mybikes.show', $bike->id) }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all group block">
                        <div class="aspect-video bg-gray-100 flex items-center justify-center relative">
                             {{-- 画像があれば表示（今回は簡易的にアイコン） --}}
                            <i data-lucide="bike" class="w-12 h-12 text-gray-300 group-hover:scale-110 transition-transform"></i>
                            <div class="absolute bottom-2 right-2 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded font-bold">
                                {{ number_format($bike->odometer) }} km
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-black text-lg text-gray-900 mb-1 group-hover:text-blue-600 transition-colors">{{ $bike->name }}</h3>
                            <p class="text-xs text-gray-500 font-bold">
                                @if($bike->bikeModel) {{ $bike->bikeModel->name }} @else 車種未設定 @endif
                            </p>
                        </div>
                    </a>
                @endforeach

                {{-- 新規登録カード --}}
                <div x-data="{ open: false }">
                    <button @click="open = true" class="w-full h-full min-h-[200px] bg-gray-50 border-2 border-dashed border-gray-300 rounded-2xl flex flex-col items-center justify-center text-gray-400 hover:text-blue-500 hover:border-blue-300 hover:bg-blue-50/30 transition-all gap-2">
                        <i data-lucide="plus-circle" class="w-8 h-8"></i>
                        <span class="font-bold text-sm">新しいバイクを登録</span>
                    </button>

                    {{-- 登録モーダル --}}
                    <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
                        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="open = false"></div>
                        <div class="relative bg-white rounded-3xl shadow-xl w-full max-w-md p-6 sm:p-8 animate-in zoom-in-95 duration-200">
                            <h2 class="text-xl font-black text-gray-900 mb-6 text-center">愛車を登録する</h2>
                            
                            <form action="{{ route('mybikes.store') }}" method="POST">
                                @csrf
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1 ml-1">愛車の名前 (必須)</label>
                                        {{-- 修正: パディングと枠線を調整 --}}
                                        <input type="text" name="name" required placeholder="例: 俺のPCX" 
                                            class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition-all">
                                    </div>
                                    <div>
                                        {{-- 修正: ラベルの誤記を修正し、フォームデザインを統一 --}}
                                        <label class="block text-xs font-bold text-gray-500 mb-1 ml-1">現在の走行距離 (km)</label>
                                        <input type="number" name="odometer" placeholder="0" 
                                            class="block w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 outline-none transition-all">
                                    </div>
                                    {{-- 車種選択は今回は省略（あとで紐付け可能にする） --}}
                                </div>
                                <div class="mt-8 flex gap-3">
                                    <button type="button" @click="open = false" class="flex-1 py-3 rounded-xl font-bold text-gray-500 hover:bg-gray-100 transition-colors">キャンセル</button>
                                    <button type="submit" class="flex-1 py-3 rounded-xl font-black bg-black text-white hover:bg-gray-800 shadow-lg transition-all transform active:scale-95">登録する</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>