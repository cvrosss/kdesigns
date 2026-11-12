# Cart and order history separation

## Intent

After an order is successfully placed, the cart drawer should show its existing
empty state. Placed orders must remain available under **My Orders**.

## Current behavior

The cart drawer fetches and displays the customer's saved orders, and refreshes
them periodically. The **Add to Cart** action starts the order form directly;
there is no separate persisted cart-items model. The My Orders page already
provides order history.

## Goals and constraints

- Keep submitted orders and their status history intact.
- Do not show submitted orders in the cart drawer.
- Leave the cart drawer's current empty-state presentation in place.
- Do not add a multi-item or persisted cart as part of this change.

## Approaches

- Clear the drawer only in the browser after checkout. This is fragile: polling,
  reopening, or reloading fetches the same saved orders again.
- Delete the order on checkout. This would lose the history the customer needs.
- Separate the cart drawer from the order-history feed, leaving submitted orders
  under My Orders. This is the chosen approach because it reliably keeps the
  drawer empty without deleting orders or building a new cart system.

## Implementation decisions

Keep the existing order-history page and its data intact. Stop using saved
orders to populate the cart drawer or its count; the drawer should render its
empty state after checkout and remain empty on refresh. The existing direct
order-form flow remains unchanged.

## Non-goals

Add-to-cart persistence, multi-item checkout, and changes to order status
management are out of scope.
