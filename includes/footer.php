<footer class="mt-auto py-8 bg-gray-900 border-t border-gray-800 text-center text-gray-500 text-sm">
    <div class="max-w-7xl mx-auto px-4">
        <p>&copy; <?= date('Y') ?> Readmanga</p>
        <p class="mt-2 text-xs text-gray-600">Baca Manga Gratis</p>
    </div>
</footer>

<script>
    // 1. SMART NAVBAR LOGIC
    let lastScrollTop = 0;
    const navbar = document.getElementById('mainNav');
    
    window.addEventListener('scroll', function() {
        let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const mobileSearch = document.getElementById('mobileSearchBar');
        
        if(!document.getElementById('mobileMenuSidebar').classList.contains('translate-x-full')) return;

        if (scrollTop > lastScrollTop && scrollTop > 60) {
            navbar.style.transform = 'translateY(-100%)';
            if(!mobileSearch.classList.contains('hidden')) {
                mobileSearch.classList.add('hidden');
            }
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        lastScrollTop = scrollTop;
    });

    // 2. MOBILE MENU TOGGLE
    function toggleMobileMenu() {
        const sidebar = document.getElementById('mobileMenuSidebar');
        const overlay = document.getElementById('mobileMenuOverlay');
        const mobileSearch = document.getElementById('mobileSearchBar');
        
        if(!mobileSearch.classList.contains('hidden')) mobileSearch.classList.add('hidden');

        if (sidebar.classList.contains('translate-x-full')) {
            sidebar.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.add('translate-x-full');
            overlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    // 3. MOBILE SEARCH TOGGLE
    function toggleMobileSearch() {
        const searchBar = document.getElementById('mobileSearchBar');
        const input = document.getElementById('mobileSearchInput');
        
        if (searchBar.classList.contains('hidden')) {
            searchBar.classList.remove('hidden');
            input.focus();
        } else {
            searchBar.classList.add('hidden');
        }
    }

    // 4. LIVE SEARCH ENGINE
    let debounceTimer;
    function doLiveSearch(keyword, targetId) {
        const resultsDiv = document.getElementById(targetId);
        
        clearTimeout(debounceTimer);

        if (keyword.length < 2) {
            resultsDiv.classList.add('hidden');
            resultsDiv.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetchResults(keyword, resultsDiv);
        }, 300);
    }

    async function fetchResults(keyword, container) {
        try {
            container.classList.remove('hidden');
            container.innerHTML = '<div class="p-4 text-center text-gray-500 text-xs"><i class="fas fa-spinner fa-spin mr-2"></i>Mencari...</div>';

            const response = await fetch(`api_search.php?q=${encodeURIComponent(keyword)}`);
            const data = await response.json();

            container.innerHTML = '';

            if (data.length > 0) {
                data.forEach(comic => {
                    let statusColor = 'text-gray-400';
                    if(comic.status === 'Ongoing') statusColor = 'text-green-400';
                    if(comic.status === 'Completed') statusColor = 'text-blue-400';

                    // --- PERBAIKAN LOGIKA GAMBAR ---
                    let coverSrc = comic.cover_image;
                    if (!coverSrc.startsWith('http')) {
                        coverSrc = 'uploads/covers/' + coverSrc;
                    }

                    // --- PERBAIKAN TAMPILAN: HAPUS RATING, TAMBAH GENRE ---
                    const item = `
                        <a href="comic.php?slug=${comic.slug}" class="flex items-start gap-3 p-3 hover:bg-gray-700/50 border-b border-gray-700/50 transition last:border-0">
                            <img src="${coverSrc}" class="w-10 h-14 object-cover rounded shadow-sm bg-gray-800 flex-shrink-0" onerror="this.src='https://via.placeholder.com/50'">
                            <div class="overflow-hidden flex-1">
                                <h4 class="text-sm font-bold text-gray-200 truncate leading-tight">${comic.title}</h4>
                                <div class="flex items-center gap-2 mt-1 text-[10px]">
                                    <span class="bg-indigo-600 px-1.5 rounded text-white font-bold">${comic.type || 'Manga'}</span>
                                    <span class="${statusColor}">${comic.status}</span>
                                </div>
                                <div class="text-[10px] text-gray-500 mt-1 truncate">
                                    ${comic.genres || '-'}
                                </div>
                            </div>
                        </a>
                    `;
                    container.innerHTML += item;
                });
            } else {
                container.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">Tidak ditemukan.</div>';
            }
        } catch (error) {
            console.error(error);
            container.innerHTML = '<div class="p-2 text-center text-red-400 text-xs">Error mengambil data.</div>';
        }
    }

    document.addEventListener('click', function(e) {
        const dContainer = document.getElementById('desktopSearchContainer');
        if (dContainer && !dContainer.contains(e.target)) {
            document.getElementById('desktopSearchResults').classList.add('hidden');
        }
    });
</script>

</body>
</html>