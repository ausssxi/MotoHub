document.addEventListener('DOMContentLoaded', () => {
    const btnDrop = document.getElementById('tab-drop');
    const btnRise = document.getElementById('tab-rise');
    const contentDrop = document.getElementById('content-drop');
    const contentRise = document.getElementById('content-rise');

    if (!btnDrop || !btnRise || !contentDrop || !contentRise) return;

    btnDrop.addEventListener('click', () => {
        btnDrop.classList.add('bg-blue-600', 'text-white', 'shadow-md');
        btnDrop.classList.remove('text-gray-400', 'hover:text-gray-800');
        
        btnRise.classList.remove('bg-red-600', 'text-white', 'shadow-md');
        btnRise.classList.add('text-gray-400', 'hover:text-gray-800');

        contentDrop.classList.remove('hidden');
        contentDrop.classList.add('animate-in', 'fade-in', 'slide-in-from-bottom-2');
        contentRise.classList.add('hidden');
    });

    btnRise.addEventListener('click', () => {
        btnRise.classList.add('bg-red-600', 'text-white', 'shadow-md');
        btnRise.classList.remove('text-gray-400', 'hover:text-gray-800');
        
        btnDrop.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
        btnDrop.classList.add('text-gray-400', 'hover:text-gray-800');

        contentRise.classList.remove('hidden');
        contentRise.classList.add('animate-in', 'fade-in', 'slide-in-from-bottom-2');
        contentDrop.classList.add('hidden');
    });
});