(function() {
    "use strict";

    document.addEventListener('DOMContentLoaded', () => {
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        const navItems = document.querySelectorAll('.nav-item'); 
        const contentPages = document.querySelectorAll('.page-content');
        
        const defaultPageId = "dashboard-page";

        // --- 1. Navigation Switch Logic ---
        function switchPage(clickedItem) {
            const pageId = clickedItem.getAttribute('data-page');
            const targetPage = document.getElementById(pageId);

            if (!targetPage) {
                console.warn(`Target page container "#${pageId}" not found.`);
                return;
            }

            // A) Update Sidebar Active Styles
            navItems.forEach(nav => {
                nav.classList.remove('bg-indigo-600', 'text-white', 'shadow-md');
                nav.classList.add('hover:bg-slate-800', 'text-slate-400');
                const icon = nav.querySelector('svg');
                if (icon) icon.classList.replace('text-white', 'text-slate-400');
            });

            clickedItem.classList.add('bg-indigo-600', 'text-white', 'shadow-md');
            clickedItem.classList.remove('hover:bg-slate-800', 'text-slate-400');
            const activeIcon = clickedItem.querySelector('svg');
            if (activeIcon) activeIcon.classList.replace('text-slate-400', 'text-white');

            // B) Toggle Page Visibility
            contentPages.forEach(page => page.classList.add('hidden'));
            targetPage.classList.remove('hidden');

            // C) Custom Page Logic
            if (pageId === "saved-tests-page") {
                handleSavedTestsLogic();
            }

            // D) Re-init Icons
            if (typeof lucide !== 'undefined') lucide.createIcons();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function handleSavedTestsLogic() {
            const testBadge = document.getElementById("testBadge");
            if (testBadge) testBadge.style.display = "none";

            fetch('/api/notifications/dismiss', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}', // Laravel requires this for POST
                'Content-Type': 'application/json'
            }
        })
                .then(() => console.log("Notifications reset."))
                .catch(err => console.error("Notification reset error:", err));

            if (typeof window.refreshSavedTests === "function") {
                window.refreshSavedTests();
            }
        }

        // --- 2. Mobile Menu Logic ---
        function toggleMenu() {
            sidebar.classList.toggle('-translate-x-full'); 
            overlay.classList.toggle('hidden'); 
        }

        if (mobileBtn) mobileBtn.addEventListener('click', toggleMenu);
        if (overlay) overlay.addEventListener('click', toggleMenu);

        // --- 3. Initial Load Routing ---
        const urlParams = new URLSearchParams(window.location.search);
        const target = urlParams.get('page');
        let initialItem = target ? document.querySelector(`.nav-item[data-page="${target}-page"]`) : null;

        if (!initialItem) initialItem = document.querySelector(`.nav-item[data-page="${defaultPageId}"]`);
        
        if (initialItem) switchPage(initialItem);

        // --- 4. Global Click Listeners ---
        navItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault(); 
                switchPage(this);
                if (!sidebar.classList.contains('-translate-x-full')) toggleMenu();
            });
        });
    });

    // --- 5. Utility Functions ---
   
})();