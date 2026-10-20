// Navbar: transparent over the hero, solid once the page is scrolled.
(function () {
    var navbar = document.querySelector('[data-navbar]');
    if (!navbar) {
        return;
    }

    function update() {
        navbar.classList.toggle('is-scrolled', window.scrollY > 40);
    }

    update();
    window.addEventListener('scroll', update, { passive: true });
})();

// Cart: items are kept in localStorage so the navbar count follows the visitor between pages.
(function () {
    var KEY = 'kdesigns_cart';
    var link = document.querySelector('[data-cart-link]');
    var badge = document.querySelector('[data-cart-count]');

    function read() {
        try {
            return JSON.parse(localStorage.getItem(KEY)) || {};
        } catch (e) {
            return {};
        }
    }

    function write(cart) {
        try {
            localStorage.setItem(KEY, JSON.stringify(cart));
        } catch (e) { /* storage unavailable: the count simply won't persist */ }
    }

    function total(cart) {
        return Object.keys(cart).reduce(function (sum, id) { return sum + cart[id].qty; }, 0);
    }

    function render(cart) {
        var count = total(cart);
        if (badge) {
            badge.textContent = count > 0 ? String(count) : '';
        }
        if (link) {
            link.setAttribute('aria-label', 'Cart, ' + count + (count === 1 ? ' item' : ' items'));
        }
    }

    var cart = read();
    render(cart);

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-add-to-cart]');
        if (!button) {
            return;
        }

        var id = button.dataset.productId;
        cart[id] = { name: button.dataset.productName, qty: (cart[id] ? cart[id].qty : 0) + 1 };
        write(cart);
        render(cart);

        var label = button.textContent;
        button.textContent = 'Added';
        button.classList.add('is-added');
        setTimeout(function () {
            button.textContent = label;
            button.classList.remove('is-added');
        }, 1200);
    });

    if (link) {
        link.addEventListener('click', function (event) { event.preventDefault(); });
    }
})();
