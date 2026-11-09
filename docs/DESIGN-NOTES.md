# Setup and checkout design

The integration should feel like the same Plaxswap merchant workspace that customers already use, while respecting WooCommerce's familiar settings and checkout layouts.

The palette is navy `#111c31`, ink `#1a2940`, cobalt `#315ce8`, muted blue `#596982`, cloud `#f4f7fc` and divider `#d9e1ed`. System typography avoids an external font request and fits WordPress admin conventions. Titles use a restrained negative letter spacing; instructional text stays readable and left aligned.

The distinctive element is the compact navy Plaxswap header containing the approved logo, a practical explanation and merchant dashboard access. The rest of the screen stays quiet. Three numbered setup steps are appropriate because account connection, payment notifications and testing are a real sequence. They become a vertical sequence on narrow screens.

The first design review rejected repeated raised cards and decorative gradients. This version uses a single setup surface, simple dividers and clear callback controls. Status colours communicate test/ready/warning states rather than decorating headings. Keyboard focus is visible and there is no automatic animation.

Checkout content stays within WooCommerce's payment option. A small logo, the merchant's payment label, a concise description and fee/confirmation guidance are enough. No additional coin picker is shown because current coin and network availability belongs to the hosted Plaxswap quote.

CSS is scoped to `.plaxswap-setup` and `.plaxswap-checkout`; it does not restyle the surrounding WordPress admin or store theme.
