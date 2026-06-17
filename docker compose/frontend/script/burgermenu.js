const burgerButton = document.getElementById('menu-toggle');
const burgermenu = document.getElementById('burger-menu');

burgerButton.addEventListener('click', function() {
    burgermenu.classList.toggle('open');
});
