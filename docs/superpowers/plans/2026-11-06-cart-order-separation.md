# Cart and Order History Separation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep the cart drawer in its empty state after checkout while preserving submitted orders under My Orders.

**Architecture:** Stop using the saved-order list as the cart's data source. Keep order persistence and history routes intact, and use the drawer's existing empty state for the cart.

**Tech Stack:** Vanilla JavaScript, Twig, Symfony 7.4/PHP 8.2.

**Spec:** [2026-11-06-cart-order-separation-design.md](../specs/2026-11-06-cart-order-separation-design.md)

## Global Constraints

- Keep submitted orders and their status history intact.
- Do not show submitted orders in the cart drawer.
- Leave the cart drawer's current empty-state presentation in place.
- Do not add a multi-item or persisted cart as part of this change.

## Review Focus

- A successful order event must not repopulate the cart with the newly submitted order; verify checkout leaves the empty state visible.
- Polling, reopening, or reloading the drawer must not restore saved orders; verify each path.
- Order history must still be available; verify the submitted order appears under My Orders.
- Signed-out users must continue to see the empty cart state; verify with a logged-out session.
- The existing order-list API must not be changed as a side effect; verify `GET /orders` still returns the customer's order data.

---

### Task 1: Separate the cart drawer from order history

**Files:**
- Modify: `public/js/storefront.js`
- Modify: `templates/partials/_cart_drawer.html.twig`

**Interfaces:**
- Consumes: the existing order-confirmation event, cart drawer markup, and My Orders route.
- Produces: a cart drawer that remains empty and order history that remains available from My Orders.

- [ ] **Step 1: Stop populating the cart drawer with saved orders**

Remove the drawer's order-list fetch, polling, order rendering, and pending-order badge updates from `public/js/storefront.js`. Keep the drawer open/close behavior and the order-confirmation event's unrelated listeners intact. Do not modify the `GET /orders` controller response or My Orders data.

- [ ] **Step 2: Label the existing empty state as the cart state**

Update the empty-state copy in `templates/partials/_cart_drawer.html.twig` to say “Your cart is empty” and direct customers to the catalog. Keep the existing flower icon and hide the order list and total footer.

- [ ] **Step 3: Validate syntax and Twig**

Run: `node --check public/js/storefront.js`
Expected: exits successfully with no syntax errors.

Run: `php bin/console lint:twig templates/partials/_cart_drawer.html.twig`
Expected: the template passes Twig lint.

- [ ] **Step 4: Verify the customer flow**

With an authenticated session, place an order and verify the cart drawer shows the empty state immediately, after reopening, and after reloading the page. Verify the submitted order remains visible under My Orders. With a signed-out session, verify the cart still shows the empty state. Verify `GET /orders` continues to return saved orders.
