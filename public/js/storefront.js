// Navbar: transparent over the hero, solid once the page is scrolled.
(function () {
    var navbar = document.querySelector('[data-navbar]');
    if (!navbar) {
        return;
    }

    function update() {
        navbar.classList.toggle('is-scrolled', window.scrollY > 40);
    }

    // The navbar's height is fixed in CSS (--nav-height, per breakpoint). It used to be measured here and written
    // back into that same variable, which the navbar's own height is built from: any one-off oversized reading
    // (iOS Safari resizing as its toolbars collapse) was then locked in, leaving a bar hundreds of pixels tall.

    // Hamburger menu (below 960px the links and account buttons live in a dropdown).
    var toggle = navbar.querySelector('[data-nav-toggle]');
    var menu = navbar.querySelector('[data-nav-menu]');

    function setMenu(open) {
        navbar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        // Hidden menu items must not be reachable with the keyboard.
        if (open || window.innerWidth > 960) { menu.removeAttribute('inert'); } else { menu.setAttribute('inert', ''); }
    }

    if (toggle && menu) {
        setMenu(false);
        toggle.addEventListener('click', function () { setMenu(!navbar.classList.contains('is-open')); });
        // Choosing a link or button (including Log In) closes the menu.
        menu.addEventListener('click', function (event) {
            if (event.target.closest('a, button')) { setMenu(false); }
        });
        document.addEventListener('click', function (event) {
            if (navbar.classList.contains('is-open') && !navbar.contains(event.target)) { setMenu(false); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && navbar.classList.contains('is-open')) { setMenu(false); toggle.focus(); }
        });
        window.addEventListener('resize', function () { setMenu(navbar.classList.contains('is-open') && window.innerWidth <= 960); });
    }

    update();
    window.addEventListener('scroll', update, { passive: true });
})();

// Cart: lists the signed-in customer's orders that are still in progress (Pending / Ready for Pick up) with their
// live status. It is read-only: only an admin can change a status. Once an order is Successful, Delivered or
// Cancelled it leaves the cart and is kept under My Orders, so the cart empties when everything is done.
(function () {
    // The cart button exists twice (navbar bar on desktop, hamburger menu on phones); both stay in sync.
    var links = document.querySelectorAll('[data-cart-link]');
    var badges = document.querySelectorAll('[data-cart-count]');
    var drawer = document.querySelector('[data-cart]');
    var canDrawer = !!drawer && typeof drawer.showModal === 'function';
    var signedIn = document.body.hasAttribute('data-authenticated');
    var timer = null;

    var IN_PROGRESS = ['pending', 'ready'];

    var STATUSES = {
        pending: { label: 'Pending', classes: 'bg-[#fdf0d5] text-[#9a6700]' },
        ready: { label: 'Ready for Pick up', classes: 'bg-[#f1e6fa] text-[#7a3fb0]' },
        successful: { label: 'Successful', classes: 'bg-[#e4ecfb] text-[#2f5fd0]' },
        delivered: { label: 'Delivered', classes: 'bg-[#e3f4ee] text-[#1e8a68]' },
        cancelled: { label: 'Cancelled', classes: 'bg-[#fbeceb] text-[#b42318]' }
    };

    function peso(amount) {
        return '₱' + amount.toLocaleString('en-US');
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function formatDate(value) {
        var d = new Date(value + 'T00:00:00');
        return isNaN(d) ? value : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatTime(value) {
        var parts = value.split(':');
        var h = parseInt(parts[0], 10);
        return (h > 12 ? h - 12 : h) + ':' + parts[1] + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    function renderDrawer(orders) {
        if (!canDrawer) {
            return;
        }

        var list = drawer.querySelector('[data-cart-items]');
        drawer.querySelector('[data-cart-empty]').hidden = orders.length > 0;
        list.hidden = orders.length === 0;
        list.textContent = '';

        orders.forEach(function (order) {
            var status = STATUSES[order.status] || { label: order.status, classes: 'bg-cream-dark text-muted' };

            var row = el('li', 'flex gap-4 border-b border-[#e3d9cb] px-7 py-5');
            var img = el('img', 'h-16 w-16 flex-none rounded-xl bg-cream-dark object-cover');
            img.src = order.image;
            img.alt = '';
            img.onerror = function () { img.hidden = true; };
            row.appendChild(img);

            var body = el('div', 'min-w-0 flex-1');
            body.appendChild(el('p', 'm-0 font-serif text-base font-bold leading-snug', order.name + (order.quantity > 1 ? ' × ' + order.quantity : '')));
            body.appendChild(el('p', 'm-0 mt-0.5 text-sm text-burgundy', peso(order.total)));
            if (order.date) {
                body.appendChild(el('p', 'm-0 mt-1 text-xs leading-normal text-muted',
                    formatDate(order.date) + (order.time ? ' · ' + formatTime(order.time) : '') + ' · ' + order.fulfillment));
            }

            var foot = el('div', 'mt-3 flex items-center justify-between gap-2');
            foot.appendChild(el('span', 'rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[.08em] ' + status.classes, status.label));
            foot.appendChild(el('span', 'text-[11px] text-muted', order.reference));
            body.appendChild(foot);

            row.appendChild(body);
            list.appendChild(row);
        });
    }

    function render(orders) {
        var pending = orders.filter(function (o) { return o.status === 'pending'; }).length;
        badges.forEach(function (badge) {
            badge.textContent = pending > 0 ? String(pending) : '';
        });
        links.forEach(function (link) {
            link.setAttribute('aria-label', 'Orders, ' + pending + ' pending');
        });
        renderDrawer(orders);
    }

    function load() {
        if (!signedIn) {
            render([]);
            return;
        }
        fetch('/orders', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : { orders: [] }; })
            .then(function (data) {
                render((data.orders || []).filter(function (o) { return IN_PROGRESS.indexOf(o.status) !== -1; }));
            })
            .catch(function () { /* keep what is on screen; the next refresh will retry */ });
    }

    function startPolling() {
        stopPolling();
        timer = setInterval(load, 15000);
    }

    function stopPolling() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    load();

    // A freshly confirmed order shows up in the list straight away.
    document.addEventListener('order:confirmed', load);

    links.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            if (canDrawer && !drawer.open) {
                load();
                drawer.showModal();
                startPolling();
            }
        });
    });

    if (canDrawer) {
        drawer.addEventListener('close', stopPolling);
        drawer.querySelector('[data-cart-close]').addEventListener('click', function () { drawer.close(); });
        drawer.addEventListener('click', function (event) {
            if (event.target === drawer) {
                drawer.close();
            }
        });
    }
})();

// Auth modal: opened by the navbar's Log In button; Log In and Sign Up share one dialog.
// Log In and Sign Up post JSON to the server (AuthController); a successful response reloads the page.
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

    // Show / hide password. Re-hides whenever the dialog closes so it never stays revealed.
    function setReveal(btn, show) {
        var input = btn.parentNode.querySelector('input');
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    }
    dialog.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () { setReveal(btn, btn.getAttribute('aria-pressed') !== 'true'); });
    });
    dialog.addEventListener('close', function () {
        dialog.querySelectorAll('[data-pw-toggle]').forEach(function (btn) { setReveal(btn, false); });
    });

    // Clicking the dimmed backdrop (the dialog element itself, outside its content) dismisses it.
    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        });
    }

    panels.forEach(function (panel) {
        panel.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessages();

            var message = panel.querySelector('[data-auth-message]');
            var submitButton = panel.querySelector('[type="submit"]');
            var invalid = Array.prototype.find.call(panel.querySelectorAll('input'), function (input) {
                return !input.checkValidity();
            });

            function show(text) {
                message.className = 'auth__message';
                message.textContent = text;
                message.hidden = false;
            }

            if (invalid) {
                invalid.setAttribute('aria-invalid', 'true');
                invalid.focus();
                show(invalid.validity.valueMissing
                    ? 'Please fill in all fields.'
                    : invalid.type === 'email'
                        ? 'Please enter a valid email address.'
                        : 'Password must be at least 8 characters.');
                return;
            }

            var signup = panel.dataset.authPanel === 'signup';
            var body = { email: panel.querySelector('[name="email"]').value, password: panel.querySelector('[name="password"]').value };
            if (signup) {
                body.name = panel.querySelector('[name="name"]').value;
            } else {
                body._remember_me = panel.querySelector('[name="_remember_me"]').checked;
            }

            submitButton.disabled = true;
            post(signup ? '/register' : '/login', body).then(function (result) {
                if (result.ok) {
                    // The session is now authenticated; admins are sent to the dashboard, everyone else
                    // reloads so the navbar and order flow reflect it.
                    if (result.data.redirect) {
                        window.location.assign(result.data.redirect);
                    } else {
                        window.location.reload();
                    }
                    return;
                }
                submitButton.disabled = false;
                show(result.status === 401
                    ? (/disabled/.test(result.data.error || '') ? result.data.error : 'Incorrect email or password.')
                    : result.data.error || 'Something went wrong. Please try again.');
            }).catch(function () {
                submitButton.disabled = false;
                show('Could not reach the server. Please try again.');
            });
        });
    });

    // Lets other modules open the dialog (the order flow asks guests to sign in first).
    document.addEventListener('auth:request', function (event) {
        var detail = event.detail || {};
        open(detail.tab);
        var message = dialog.querySelector('[data-auth-panel]:not([hidden]) [data-auth-message]');
        if (message && detail.notice) {
            message.className = 'auth__message auth__message--info';
            message.textContent = detail.notice;
            message.hidden = false;
        }
    });
})();

// Order modal: "Add to Cart" walks the customer through details -> (receiver) -> payment -> (e-receipt).
// Nothing is saved until the last step, which posts the order to /orders (validated and stored server-side)
// and then hands the product to the cart.
(function () {
    var dialog = document.querySelector('[data-order]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    var panels = dialog.querySelectorAll('[data-order-panel]');
    var form = dialog.querySelector('[data-order-panel="details"]');
    var receiverForm = dialog.querySelector('[data-order-panel="receiver"]');
    var dateInput = dialog.querySelector('[data-order-date]');
    var methods = dialog.querySelectorAll('[data-order-method]');
    var summary = dialog.querySelector('[data-order-delivery-summary]');
    var paymentButtons = dialog.querySelectorAll('[data-order-payment]');
    var selectPayment = dialog.querySelector('[data-order-select-payment]');
    var finish = dialog.querySelector('[data-order-finish]');
    var overline = dialog.querySelector('[data-order-overline]');
    var title = dialog.querySelector('[data-order-title]');
    var priceEl = dialog.querySelector('[data-order-price]');

    var trigger = null;
    var product = null;
    var method = 'Pick Up';
    var receiver = null;
    var payment = null;
    var receiptRef = null;
    var details = null;
    var current = 'details';
    var busy = false;

    function pad(n) { return String(n).padStart(2, '0'); }

    // Same-day orders close at the cutoff (5:00 PM Asia/Manila). Both values come from the server (OrderSchedule),
    // and the clock is read in the shop's timezone, not the visitor's.
    var TIMEZONE = dialog.dataset.orderTimezone || 'Asia/Manila';
    var cutoff = (dialog.dataset.orderCutoff || '17:00').split(':');
    var CUTOFF_SECONDS = parseInt(cutoff[0], 10) * 3600 + parseInt(cutoff[1], 10) * 60;
    var clock = new Intl.DateTimeFormat('en-CA', {
        timeZone: TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
    });
    var refreshTimer = null;

    function shopNow() {
        var p = {};
        clock.formatToParts(new Date()).forEach(function (part) { p[part.type] = part.value; });
        return {
            date: p.year + '-' + p.month + '-' + p.day,
            seconds: parseInt(p.hour, 10) * 3600 + parseInt(p.minute, 10) * 60 + parseInt(p.second, 10)
        };
    }

    function today() {
        return shopNow().date;
    }

    // Today until the cutoff, then tomorrow.
    function earliestDate() {
        var now = shopNow();
        if (now.seconds < CUTOFF_SECONDS) {
            return now.date;
        }
        var d = now.date.split('-');
        return new Date(Date.UTC(+d[0], +d[1] - 1, +d[2] + 1)).toISOString().slice(0, 10);
    }

    function closedMessage() {
        return 'Same-day orders close at ' + formatTime(dialog.dataset.orderCutoff || '17:00') + '. Please choose tomorrow or a later date.';
    }

    // Moves the picker's minimum date; a date picked earlier that is no longer allowed is cleared with a notice.
    function refreshEarliestDate() {
        var earliest = earliestDate();
        dateInput.min = earliest;
        var picked = dateInput.value;
        if (picked && picked < earliest) {
            dateInput.value = '';
            if (dialog.open && current === 'details') {
                showError(picked === today() ? closedMessage() : 'Please choose today or a later date.', dateInput);
            }
        }
    }

    // Wakes at the next change (the cutoff, or midnight in the shop's timezone) so an open page updates by itself.
    function scheduleRefresh() {
        clearTimeout(refreshTimer);
        var now = shopNow();
        var next = now.seconds < CUTOFF_SECONDS ? CUTOFF_SECONDS : 86400;
        refreshTimer = setTimeout(function () {
            refreshEarliestDate();
            scheduleRefresh();
        }, (next - now.seconds) * 1000 + 250);
    }

    function formatTime(value) {
        var parts = value.split(':');
        var h = parseInt(parts[0], 10);
        return (h > 12 ? h - 12 : h) + ':' + parts[1] + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    function peso(amount) {
        return '₱' + amount.toLocaleString('en-US');
    }

    function newReceiptRef() {
        var n = new Uint32Array(1);
        window.crypto.getRandomValues(n);
        return 'KD' + String(10000000 + (n[0] % 90000000));
    }

    var HEADERS = {
        details: function () { return ['Place Order', product.name, peso(product.price)]; },
        receiver: function () { return ['Delivery', 'Receiver Details', '']; },
        payment: function () { return ['Step 2 of 2', 'Payment Method', '']; },
        receipt: function () { return ['KDesigns Blooms & Styles', 'E-Receipt', '']; },
        success: function () { return ['Order Placed', product.name, '']; }
    };

    function show(name) {
        current = name;
        panels.forEach(function (panel) { panel.hidden = panel.dataset.orderPanel !== name; });
        var header = HEADERS[name]();
        overline.textContent = header[0];
        title.textContent = header[1];
        priceEl.textContent = header[2];
        priceEl.hidden = header[2] === '';
        clearErrors();
        dialog.scrollTop = 0;
    }

    function clearErrors() {
        dialog.querySelectorAll('[data-order-error]').forEach(function (el) { el.hidden = true; });
        dialog.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
    }

    function showError(message, input) {
        var panel = dialog.querySelector('[data-order-panel="' + current + '"]');
        var error = panel.querySelector('[data-order-error]');
        if (input) {
            input.setAttribute('aria-invalid', 'true');
            input.focus();
        }
        error.textContent = message;
        error.hidden = false;
    }

    function fail(input, message) {
        showError(message, input);
    }

    function renderSummary() {
        var visible = method === 'Delivery' && receiver !== null;
        summary.hidden = !visible;
        if (visible) {
            summary.querySelector('[data-summary-receiver]').textContent = receiver.name;
            summary.querySelector('[data-summary-contact]').textContent = receiver.contact;
            summary.querySelector('[data-summary-location]').textContent = receiver.location;
        }
    }

    function selectMethod(name) {
        method = name;
        methods.forEach(function (button) {
            button.setAttribute('aria-pressed', button.dataset.orderMethod === name ? 'true' : 'false');
        });
        renderSummary();
    }

    function openReceiver() {
        receiverForm.elements.receiver.value = receiver ? receiver.name : form.elements.name.value.trim();
        receiverForm.elements.receiverContact.value = receiver ? receiver.contact : form.elements.contact.value.trim();
        receiverForm.elements.location.value = receiver ? receiver.location : '';
        show('receiver');
        receiverForm.elements.receiver.focus();
    }

    function selectPaymentOption(name) {
        payment = name;
        paymentButtons.forEach(function (button) {
            button.setAttribute('aria-checked', button.dataset.orderPayment === name ? 'true' : 'false');
        });
        selectPayment.disabled = name === null;
    }

    function fillReceipt() {
        var set = function (key, text) {
            dialog.querySelectorAll('[data-receipt="' + key + '"]').forEach(function (el) { el.textContent = text; });
        };
        set('ref', receiptRef);
        set('ref-note', receiptRef);
        set('today', new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
        set('payment', payment);
        set('product', product.name);
        set('fulfillment', details.method);
        set('date', details.date);
        set('time', formatTime(details.time));
        set('total', peso(product.price));
    }

    // The last step: persist the order. Pay at Shop saves right after choosing it; online methods save from the e-receipt.
    function submitOrder() {
        if (busy) {
            return;
        }
        busy = true;
        selectPayment.disabled = true;
        finish.disabled = true;

        var body = {
            productId: trigger.dataset.productId,
            name: details.name,
            contact: details.contact,
            date: details.date,
            time: details.time,
            method: details.method,
            paymentMethod: payment
        };
        if (payment !== 'Pay at Shop') {
            body.receiptRef = receiptRef;
        }
        if (details.method === 'Delivery') {
            body.deliveryReceiver = receiver.name;
            body.deliveryContact = receiver.contact;
            body.deliveryLocation = receiver.location;
        }

        fetch('/orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                busy = false;
                selectPayment.disabled = payment === null;
                finish.disabled = false;

                if (response.status === 401) {
                    // Session expired or logged out in another tab.
                    dialog.close();
                    document.dispatchEvent(new CustomEvent('auth:request', {
                        detail: { tab: 'login', notice: data.error || 'Please log in to place an order.' }
                    }));
                } else if (!response.ok) {
                    showError(data.error || 'Something went wrong. Please try again.');
                } else {
                    document.dispatchEvent(new CustomEvent('order:confirmed', {
                        detail: {
                            button: trigger,
                            order: { name: details.name, contact: details.contact, date: details.date, time: details.time, method: details.method }
                        }
                    }));
                    dialog.querySelector('[data-success-reference]').textContent = data.reference;
                    show('success');
                }
            });
        }).catch(function () {
            busy = false;
            selectPayment.disabled = payment === null;
            finish.disabled = false;
            showError('Could not reach the server. Please try again.');
        });
    }

    refreshEarliestDate();
    scheduleRefresh();
    // Timers are throttled or paused while a tab sleeps; re-check as soon as it is visible again.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshEarliestDate();
            scheduleRefresh();
        }
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-add-to-cart]');
        if (!button) {
            return;
        }

        if (!document.body.hasAttribute('data-authenticated')) {
            document.dispatchEvent(new CustomEvent('auth:request', {
                detail: { tab: 'login', notice: 'Please log in or sign up to place an order.' }
            }));
            return;
        }

        trigger = button;
        product = {
            name: button.dataset.productName,
            price: parseInt(String(button.dataset.productPrice || '0').replace(/\D/g, ''), 10) || 0
        };
        form.reset();
        receiverForm.reset();
        receiver = null;
        details = null;
        busy = false;
        finish.disabled = false;
        selectPaymentOption(null);
        selectMethod('Pick Up');
        refreshEarliestDate();
        show('details');
        dialog.showModal();
        form.elements.name.focus();
    });

    methods.forEach(function (button) {
        button.addEventListener('click', function () {
            selectMethod(button.dataset.orderMethod);
            if (method === 'Delivery' && receiver === null) {
                openReceiver();
            }
        });
    });

    dialog.querySelector('[data-order-edit-receiver]').addEventListener('click', openReceiver);

    dialog.querySelectorAll('[data-order-close]').forEach(function (el) {
        el.addEventListener('click', function () { dialog.close(); });
    });

    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.querySelectorAll('[data-order-back]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (current === 'receiver') {
                // Backing out before any details were saved means the shopper does not want delivery after all.
                if (receiver === null) {
                    selectMethod('Pick Up');
                }
                show('details');
            } else if (current === 'receipt') {
                show('payment');
            } else {
                show('details');
            }
        });
    });

    // Step 1 -> receiver details (delivery only) -> payment.
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();

        var name = form.elements.name;
        var contact = form.elements.contact;
        var date = form.elements.date;

        if (!name.value.trim()) {
            return fail(name, 'Please enter your name.');
        }
        if (contact.value.replace(/\D/g, '').length < 7) {
            return fail(contact, 'Please enter a valid contact number.');
        }
        if (!date.value) {
            return fail(date, 'Please choose the date you need your order.');
        }
        if (date.value < earliestDate()) {
            return fail(date, date.value === today() ? closedMessage() : 'Please choose today or a later date.');
        }

        details = {
            name: name.value.trim(),
            contact: contact.value.trim(),
            date: date.value,
            time: form.elements.time.value,
            method: method
        };

        if (method === 'Delivery' && receiver === null) {
            return openReceiver();
        }
        show('payment');
    });

    receiverForm.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();

        var name = receiverForm.elements.receiver;
        var contact = receiverForm.elements.receiverContact;
        var location = receiverForm.elements.location;

        if (!name.value.trim()) {
            return fail(name, 'Receiver name is required for delivery.');
        }
        if (contact.value.replace(/\D/g, '').length < 7) {
            return fail(contact, 'Please enter a valid receiver contact number.');
        }
        if (!location.value.trim()) {
            return fail(location, 'Delivery location is required for delivery.');
        }

        receiver = { name: name.value.trim(), contact: contact.value.trim(), location: location.value.trim() };
        renderSummary();
        show('details');
    });

    paymentButtons.forEach(function (button) {
        button.addEventListener('click', function () { selectPaymentOption(button.dataset.orderPayment); });
    });

    selectPayment.addEventListener('click', function () {
        if (payment === null) {
            return;
        }
        // A method change after editing the receiver must not leave stale delivery data behind.
        if (details.method === 'Delivery' && receiver === null) {
            return openReceiver();
        }
        if (payment === 'Pay at Shop') {
            submitOrder();
        } else {
            receiptRef = newReceiptRef();
            fillReceipt();
            show('receipt');
        }
    });

    finish.addEventListener('click', submitOrder);
})();

// Reviews carousel: the track scrolls and snaps natively (swipe, trackpad, arrow keys); the buttons just page it by
// one card. They disable at either end, and the whole control row hides when every review already fits on screen.
(function () {
    var root = document.querySelector('[data-carousel]');
    if (!root) {
        return;
    }

    var track = root.querySelector('[data-carousel-track]');
    var controls = root.querySelector('[data-carousel-controls]');
    var prev = root.querySelector('[data-carousel-prev]');
    var next = root.querySelector('[data-carousel-next]');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function update() {
        var max = track.scrollWidth - track.clientWidth;
        controls.hidden = max <= 1;
        prev.disabled = track.scrollLeft <= 1;
        next.disabled = track.scrollLeft >= max - 1;
    }

    function page(direction) {
        var card = track.firstElementChild;
        var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        track.scrollBy({ left: direction * (card.offsetWidth + gap), behavior: reduceMotion.matches ? 'auto' : 'smooth' });
    }

    prev.addEventListener('click', function () { page(-1); });
    next.addEventListener('click', function () { page(1); });
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();

// Live stock: while a catalog grid is on screen, poll /stock every few seconds and update each card's stock badge and
// its Add to Cart / Sold Out button, so a restock (or someone else's order) shows up without a refresh. Polling pauses
// while the tab is hidden and catches up immediately when it is shown again or the network returns; after a failed
// request it backs off (up to 30s) and the next successful poll brings every card fully up to date.
(function () {
    var grid = document.querySelector('[data-stock-url]');
    if (!grid || !window.fetch) {
        return;
    }

    var url = grid.getAttribute('data-stock-url');
    var INTERVAL = 3000;
    var MAX_BACKOFF = 30000;
    var failures = 0;
    var timer = null;
    var inFlight = false;

    function apply(snapshot) {
        grid.querySelectorAll('[data-product-card]').forEach(function (card) {
            var state = snapshot[card.getAttribute('data-product-card')];
            if (!state) {
                return;
            }

            var badge = card.querySelector('[data-stock-badge]');
            var add = card.querySelector('[data-add-to-cart]');
            var soldOut = card.querySelector('[data-sold-out]');

            if (badge) {
                var className = 'stock stock--' + state.level;
                if (badge.className !== className) {
                    badge.className = className;
                }
                if (badge.textContent !== state.status) {
                    badge.textContent = state.status;
                }
            }
            if (add) {
                add.hidden = !state.available;
            }
            if (soldOut) {
                soldOut.hidden = state.available;
            }
        });
    }

    function schedule() {
        var wait = Math.min(INTERVAL * Math.pow(2, failures), MAX_BACKOFF);
        clearTimeout(timer);
        // Jitter keeps many open tabs from hitting the server in the same instant.
        timer = setTimeout(poll, wait * (0.85 + Math.random() * 0.3));
    }

    function poll() {
        clearTimeout(timer);
        if (document.hidden) {
            return; // resumed by visibilitychange
        }
        if (inFlight) {
            return;
        }

        inFlight = true;
        fetch(url, { cache: 'no-cache', credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                failures = 0;
                apply(data.stock || {});
            })
            .catch(function () {
                failures += 1;
            })
            .then(function () {
                inFlight = false;
                schedule();
            });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });
    window.addEventListener('online', function () {
        failures = 0;
        poll();
    });

    poll();
})();

// Photo viewer: tap a product photo to open it full screen. Pinch, double-tap, the +/- buttons or the mouse wheel
// zoom it; drag moves it around once zoomed. Plain pointer events, so it behaves the same on iPhone, iPad and desktop.
(function () {
    var dialog = document.querySelector('[data-zoom-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') { return; }

    var stage = dialog.querySelector('[data-zoom-stage]');
    var img = dialog.querySelector('[data-zoom-img]');
    var caption = dialog.querySelector('[data-zoom-caption]');
    var MIN = 1, MAX = 4, DOUBLE_TAP_SCALE = 2.5;
    var scale = 1, x = 0, y = 0;
    var pointers = {};            // pointerId -> {x, y}
    var pinchStart = null;        // {dist, scale}
    var tap = { time: 0, x: 0, y: 0, sx: 0, sy: 0, moved: false, downAt: 0 };

    function clamp(v, lo, hi) { return Math.min(hi, Math.max(lo, v)); }

    // Keep the picture from being dragged off screen: no panning at all unless it is larger than the stage.
    function limit() {
        var maxX = Math.max(0, (img.clientWidth * scale - stage.clientWidth) / 2);
        var maxY = Math.max(0, (img.clientHeight * scale - stage.clientHeight) / 2);
        x = clamp(x, -maxX, maxX);
        y = clamp(y, -maxY, maxY);
    }

    function apply() {
        limit();
        img.style.transform = 'translate(' + x + 'px,' + y + 'px) scale(' + scale + ')';
        dialog.classList.toggle('is-zoomed', scale > 1.01);
    }

    function setScale(next, centerX, centerY) {
        var prev = scale;
        scale = clamp(next, MIN, MAX);
        // Zoom around the given point (pinch midpoint or tapped spot) rather than the picture's middle.
        if (centerX !== undefined) {
            var rect = stage.getBoundingClientRect();
            var dx = centerX - (rect.left + rect.width / 2);
            var dy = centerY - (rect.top + rect.height / 2);
            x = dx - (dx - x) * (scale / prev);
            y = dy - (dy - y) * (scale / prev);
        }
        if (scale === MIN) { x = 0; y = 0; }
        apply();
    }

    function reset() { scale = 1; x = 0; y = 0; apply(); }

    function open(src, title) {
        reset();
        img.src = src;
        img.alt = title || '';
        caption.textContent = title || '';
        dialog.showModal();
        document.documentElement.style.overflow = 'hidden';
    }

    dialog.addEventListener('close', function () {
        document.documentElement.style.overflow = '';
        pointers = {};
        pinchStart = null;
        img.removeAttribute('src');
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-zoom]');
        if (trigger) { open(trigger.getAttribute('data-zoom-src'), trigger.getAttribute('data-zoom-title')); }
    });

    dialog.querySelector('[data-zoom-close]').addEventListener('click', function () { dialog.close(); });
    dialog.querySelector('[data-zoom-in]').addEventListener('click', function () { setScale(scale * 1.5); });
    dialog.querySelector('[data-zoom-out]').addEventListener('click', function () { setScale(scale / 1.5); });
    dialog.querySelector('[data-zoom-reset]').addEventListener('click', reset);

    function pair() {
        var ids = Object.keys(pointers);
        return [pointers[ids[0]], pointers[ids[1]]];
    }

    function distance() {
        var p = pair();
        return Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
    }

    stage.addEventListener('pointerdown', function (event) {
        stage.setPointerCapture(event.pointerId);
        pointers[event.pointerId] = { x: event.clientX, y: event.clientY };
        if (Object.keys(pointers).length === 2) {
            pinchStart = { dist: distance(), scale: scale };
            tap.moved = true;
        } else {
            tap.moved = false;
            tap.downAt = Date.now();
            tap.sx = event.clientX;
            tap.sy = event.clientY;
        }
    });

    stage.addEventListener('pointermove', function (event) {
        var p = pointers[event.pointerId];
        if (!p) { return; }
        var dx = event.clientX - p.x, dy = event.clientY - p.y;
        p.x = event.clientX;
        p.y = event.clientY;

        if (Object.keys(pointers).length === 2 && pinchStart) {
            var pts = pair();
            setScale(pinchStart.scale * (distance() / pinchStart.dist), (pts[0].x + pts[1].x) / 2, (pts[0].y + pts[1].y) / 2);
        } else if (scale > 1) {
            x += dx;
            y += dy;
            apply();
        }
        if (Math.hypot(event.clientX - tap.sx, event.clientY - tap.sy) > 10) { tap.moved = true; }
    });

    function end(event) {
        if (!pointers[event.pointerId]) { return; }
        delete pointers[event.pointerId];
        pinchStart = null;

        // A quick, still tap; two close together toggle between fit and zoomed in.
        if (event.type === 'pointerup' && !tap.moved && Date.now() - tap.downAt < 300) {
            var now = Date.now();
            if (now - tap.time < 320 && Math.hypot(event.clientX - tap.x, event.clientY - tap.y) < 30) {
                if (scale > 1.01) { reset(); } else { setScale(DOUBLE_TAP_SCALE, event.clientX, event.clientY); }
                tap.time = 0;
            } else {
                tap.time = now;
                tap.x = event.clientX;
                tap.y = event.clientY;
            }
        }
    }

    stage.addEventListener('pointerup', end);
    stage.addEventListener('pointercancel', end);

    stage.addEventListener('wheel', function (event) {
        event.preventDefault();
        setScale(scale * (event.deltaY < 0 ? 1.15 : 1 / 1.15), event.clientX, event.clientY);
    }, { passive: false });

    window.addEventListener('resize', apply);
})();

// Category pills (phones): the row scrolls sideways, so start with the active category in view
// (e.g. "Others" would otherwise sit off screen). Only the row itself scrolls, never the page.
(function () {
    var row = document.querySelector('.catalog__head .pills');
    var active = row && row.querySelector('.pill.is-active');
    if (!active || row.scrollWidth <= row.clientWidth) { return; }
    row.scrollLeft = Math.max(0, active.offsetLeft - (row.clientWidth - active.offsetWidth) / 2);
})();
