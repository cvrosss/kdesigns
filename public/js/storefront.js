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

// Cart: lists the signed-in customer's confirmed orders with their live status. It is read-only: only an
// admin can change a status (Pending -> Successful, or Delivered for delivery orders).
(function () {
    var link = document.querySelector('[data-cart-link]');
    var badge = document.querySelector('[data-cart-count]');
    var drawer = document.querySelector('[data-cart]');
    var canDrawer = !!drawer && typeof drawer.showModal === 'function';
    var signedIn = document.body.hasAttribute('data-authenticated');
    var timer = null;

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
        drawer.querySelector('[data-cart-footer]').hidden = orders.length === 0;
        list.hidden = orders.length === 0;
        list.textContent = '';

        var sum = 0;
        orders.forEach(function (order) {
            var status = STATUSES[order.status] || { label: order.status, classes: 'bg-cream-dark text-muted' };
            if (order.status !== 'cancelled') {
                sum += order.total;
            }

            var row = el('li', 'flex gap-4 border-b border-[#e3d9cb] px-7 py-5');
            var img = el('img', 'h-16 w-16 flex-none rounded-[3px] bg-cream-dark object-cover');
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

        drawer.querySelector('[data-cart-total]').textContent = peso(sum);
    }

    function render(orders) {
        var pending = orders.filter(function (o) { return o.status === 'pending'; }).length;
        if (badge) {
            badge.textContent = pending > 0 ? String(pending) : '';
        }
        if (link) {
            link.setAttribute('aria-label', 'Orders, ' + pending + ' pending');
        }
        renderDrawer(orders);
    }

    function load() {
        if (!signedIn) {
            render([]);
            return;
        }
        fetch('/orders', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : { orders: [] }; })
            .then(function (data) { render(data.orders || []); })
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

    if (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            if (canDrawer && !drawer.open) {
                load();
                drawer.showModal();
                startPolling();
            }
        });
    }

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
                        : 'Password must be at least 6 characters.');
                return;
            }

            var signup = panel.dataset.authPanel === 'signup';
            var body = { email: panel.querySelector('[name="email"]').value, password: panel.querySelector('[name="password"]').value };
            if (signup) {
                body.name = panel.querySelector('[name="name"]').value;
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
                    ? 'Incorrect email or password.'
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

    function today() {
        var now = new Date();
        return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
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
        dateInput.min = today();
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
        if (date.value < today()) {
            return fail(date, 'Please choose today or a later date.');
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
