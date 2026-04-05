        </main>
        
        <footer class="bg-white border-t border-gray-100 py-6 px-10 flex flex-col sm:flex-row items-center justify-between text-gray-500 text-sm">
            <div class="flex items-center space-x-3 mb-2 sm:mb-0">
                <i class="fa-solid fa-scale-balanced text-prcgold opacity-70"></i>
                <span>&copy; <?php echo date('Y'); ?> <strong class="text-prcnavy">Professional Regulation Commission</strong>. All rights reserved.</span>
            </div>
            <div class="text-xs tracking-wider uppercase text-gray-400 font-medium">
                Admin Portal v2.1
            </div>
        </footer>
    </div>
    <div id="appAlertModal" class="fixed inset-0 z-[110] hidden bg-prcnavy/70 p-4">
        <div class="flex min-h-screen items-center justify-center">
            <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-soft-lg">
                <div class="flex items-start justify-between border-b border-gray-100 px-6 py-5">
                    <div class="flex items-center">
                        <div id="appAlertIcon" class="mr-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                            <i class="fa-solid fa-circle-info text-xl"></i>
                        </div>
                        <div>
                            <h3 id="appAlertTitle" class="text-lg font-bold text-prcnavy">Notice</h3>
                            <p id="appAlertSubtitle" class="text-xs uppercase tracking-widest text-gray-400">System message</p>
                        </div>
                    </div>
                    <button id="appAlertClose" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-900">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                <div class="px-6 py-5">
                    <div id="appAlertMessage" class="whitespace-pre-line text-sm leading-relaxed text-gray-600"></div>
                </div>
                <div class="flex justify-end border-t border-gray-100 px-6 py-4">
                    <button id="appAlertOk" type="button" class="inline-flex items-center rounded-xl bg-prcnavy px-5 py-2.5 text-sm font-bold uppercase tracking-widest text-white transition hover:bg-prcaccent">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo BASE_URL; ?>/assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('mobile-menu-toggle');
            const menu = document.getElementById('mobile-menu');

            if (!toggle || !menu) {
                return;
            }

            const closeMenu = () => {
                menu.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            };

            toggle.addEventListener('click', () => {
                const isHidden = menu.classList.toggle('hidden');
                toggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            });

            menu.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', closeMenu);
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    closeMenu();
                }
            });
        });
    </script>
</body>
</html>
