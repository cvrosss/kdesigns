// Admin / staff hamburger menu (tablets and phones). The desktop sidebar ignores the toggle.
(function () {
    // Must match the `max-width: 900px` breakpoint in admin.css where the sidebar becomes a top bar.
    var MOBILE_MAX = 900;

    var sidebar = document.querySelector('.sidebar');
    var toggle = document.querySelector('[data-admin-toggle]');
    if (!sidebar || !toggle) {
        return;
    }

    // One place that keeps the open class and the toggle's accessibility attributes in step.
    function setMenu(open) {
        sidebar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }

    toggle.addEventListener('click', function () { setMenu(!sidebar.classList.contains('is-open')); });

    // Escape closes the menu and hands focus back to the button that opened it.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) { setMenu(false); toggle.focus(); }
    });

    // Tapping anywhere outside the sidebar closes it.
    document.addEventListener('click', function (event) {
        if (sidebar.classList.contains('is-open') && !sidebar.contains(event.target)) { setMenu(false); }
    });

    // Rotating a tablet to landscape switches back to the desktop sidebar, so reset the open state.
    window.addEventListener('resize', function () { if (window.innerWidth > MOBILE_MAX) { setMenu(false); } });
})();
