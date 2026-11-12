// Adds a show/hide eye button to every password input on the page (admin forms).
(function () {
    // Inline SVG so the button needs no extra request; .pw__slash is the diagonal stroke shown while the password is visible.
    var EYE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/><path class="pw__slash" d="M3 3l18 18"/></svg>';

    // Switches one field between hidden and visible and keeps the button's state and label in step for screen readers.
    function setReveal(input, btn, show) {
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    }

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        // Wrap the input so the eye button can be positioned inside its right edge (see .pw in the stylesheets).
        var wrap = document.createElement('span');
        wrap.className = 'pw';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw__toggle';
        btn.innerHTML = EYE;
        wrap.appendChild(btn);
        setReveal(input, btn, false);

        btn.addEventListener('click', function () {
            setReveal(input, btn, btn.getAttribute('aria-pressed') !== 'true');
            input.focus();
        });
    });

    // Never leave a password revealed when the form is submitted.
    document.addEventListener('submit', function (event) {
        event.target.querySelectorAll('.pw__toggle[aria-pressed="true"]').forEach(function (btn) { btn.click(); });
    }, true);
})();
