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

// Auth modal: opened by the navbar's Log In button; Log In and Sign Up share one dialog.
// There is no authentication backend yet, so submitting only validates and explains that.
(function () {
    var dialog = document.querySelector('[data-auth]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    var title = dialog.querySelector('[data-auth-title]');
    var tabs = dialog.querySelectorAll('[data-auth-tab]');
    var panels = dialog.querySelectorAll('[data-auth-panel]');

    function clearMessages() {
        dialog.querySelectorAll('[data-auth-message]').forEach(function (el) { el.hidden = true; });
        dialog.querySelectorAll('input').forEach(function (el) { el.removeAttribute('aria-invalid'); });
    }

    function select(name) {
        tabs.forEach(function (tab) {
            var active = tab.dataset.authTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                title.textContent = tab.dataset.title;
            }
        });
        panels.forEach(function (panel) { panel.hidden = panel.dataset.authPanel !== name; });
        clearMessages();
    }

    function open(name) {
        select(name || 'login');
        if (!dialog.open) {
            dialog.showModal();
        }
        var field = dialog.querySelector('[data-auth-panel]:not([hidden]) input');
        if (field) {
            field.focus();
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-auth-open]');
        if (trigger) {
            open(trigger.dataset.authOpen);
        }
    });

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { select(tab.dataset.authTab); });
    });

    dialog.querySelector('[data-auth-close]').addEventListener('click', function () { dialog.close(); });

    // Clicking the dimmed backdrop (the dialog element itself, outside its content) dismisses it.
    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    panels.forEach(function (panel) {
        panel.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessages();

            var message = panel.querySelector('[data-auth-message]');
            var invalid = Array.prototype.find.call(panel.querySelectorAll('input'), function (input) {
                return !input.checkValidity();
            });

            if (invalid) {
                invalid.setAttribute('aria-invalid', 'true');
                invalid.focus();
                message.className = 'auth__message';
                message.textContent = invalid.validity.valueMissing
                    ? 'Please fill in all fields.'
                    : invalid.type === 'email'
                        ? 'Please enter a valid email address.'
                        : 'Password must be at least 6 characters.';
            } else {
                message.className = 'auth__message auth__message--info';
                message.textContent = 'Accounts are not available yet. This form is not connected to a sign-in system.';
            }
            message.hidden = false;
        });
    });
})();
