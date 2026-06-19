document.addEventListener('DOMContentLoaded', () => {
    const burgerButton = document.getElementById('menu-toggle');
    const burgerMenu = document.getElementById('burger-menu');

    if (burgerButton && burgerMenu) {
        burgerButton.addEventListener('click', (e) => {
            e.stopPropagation();
            burgerMenu.classList.toggle('hidden');
        });

        // Fermer le menu si on clique ailleurs
        document.addEventListener('click', (e) => {
            if (!burgerMenu.contains(e.target) && !burgerButton.contains(e.target)) {
                burgerMenu.classList.add('hidden');
            }
        });
    }
});