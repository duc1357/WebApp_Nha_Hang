# Responsive UI Polish Design

## Goal

Upgrade the restaurant application UI so it looks more professional and works well across desktop PC, laptop, iPad/tablet, and mobile, while preserving the current visual identity and page structure.

## Current Context

The project is a zero-dependency PHP, HTML, CSS, and vanilla JavaScript restaurant system. Customer-facing screens include homepage/menu, floating cart, booking, QR payment, login/register, forgot password, and profile/history. Admin screens include dashboard, orders, bookings/POS table map, menu, users, vouchers, and logs.

The current UI already has a recognizable warm restaurant identity using orange accents, rounded cards, food imagery, floating cart, sticky navigation, and admin sidebar. The main issues are polish and consistency: CSS has repeated sections, several pages use inline styles, admin pages define their own local UI rules, and some layouts need stronger responsive behavior for tablet and mobile.

## Design Direction

Use a lightweight responsive design system, not a redesign. Keep the existing brand colors, imagery, navigation concepts, floating cart, booking map, profile layout, and admin sidebar. Improve the visual quality through consistent tokens, spacing, typography, component states, breakpoints, and viewport-specific layout rules.

The result should feel like the same application, just tightened: cleaner spacing, predictable components, more balanced cards, better table handling, accessible focus states, and mobile interactions that feel intentional.

## Responsive Targets

Support these viewport classes explicitly:

- Desktop PC: 1440px and wider.
- Laptop: 1024px to 1439px.
- Tablet/iPad: 768px to 1023px.
- Mobile: 320px to 767px.

Every major page should avoid horizontal body overflow, overlapping UI, clipped button text, unreadable tables, and modals larger than the viewport.

## UI Foundation

Add a shared layer in `style.css` for reusable design tokens and components. Keep existing variable names where possible, then extend them with a small set of consistent values:

- Color tokens for primary orange, accent navy, page background, surface, border, muted text, success, warning, danger, and focus ring.
- Spacing tokens for compact, normal, and roomy layouts.
- Radius tokens for controls, cards, and modals.
- Shadow tokens for subtle surfaces and elevated overlays.
- Type scale for page headings, section headings, body text, compact admin text, and button labels.
- Breakpoint rules for desktop, laptop, tablet, and mobile.

Reusable components should cover buttons, icon/action buttons, form controls, toolbar/filter rows, cards, badges, data tables, modals, toast/state messages, and responsive containers.

## Customer Pages

The customer UI should remain warm and food-focused. Homepage, menu cards, reviews, floating cart, QR payment modal, auth pages, and profile should share the same spacing and control styles.

Homepage/menu:

- Keep the hero and menu grid concept.
- Make card image ratios stable across devices.
- Reduce visual noise from duplicated card shadows and inconsistent radii.
- Ensure nav wraps or collapses gracefully on tablet/mobile.
- Keep CTA buttons prominent and touch-friendly.

Cart/payment:

- Keep the floating cart entry point.
- On desktop/laptop, preserve the popup behavior.
- On tablet/mobile, make the cart behave like a bottom sheet or full-width drawer with scroll-safe content.
- QR payment modal must fit small screens, keep QR visible, and keep key amount/content copy controls accessible.

Booking:

- Keep the two-column booking layout on desktop.
- On tablet/mobile, stack the flow into clear sections: filters, table map, selected table summary, customer info, preorder, submit/payment.
- Make the selected table and submit action easy to find after scrolling.
- Preserve existing booking/payment logic; this phase is visual and layout polish only.

Profile/auth:

- Keep the premium profile/sidebar feel on desktop.
- On tablet/mobile, make profile navigation horizontal or stacked without clipping.
- Forms should have consistent labels, inputs, helper text, error messages, and button states.
- Review and order-detail modals should be viewport-safe.

## Admin Pages

The admin UI should feel operational, calm, and consistent with the customer brand without becoming decorative. Keep the sidebar/dashboard/table-based structure.

Dashboard:

- Keep stat cards and charts.
- Normalize card spacing, headers, stat typography, and chart containers.
- Make dashboard grids adapt cleanly from desktop to tablet/mobile.

Orders, bookings, menu, users, vouchers, logs:

- Standardize page header, filters, action buttons, tables, badges, pagination, and modals.
- Filters should wrap cleanly on laptop/tablet and stack on mobile.
- Tables should use safe horizontal scrolling on smaller screens.
- Row action buttons should use consistent sizing and spacing.
- Modals should use shared overlay/content styles and never exceed viewport height.

Bookings/POS:

- Keep map/list toggle and POS modal.
- Make table map cards readable on tablet and mobile.
- POS modal should divide menu/cart clearly on desktop and stack on smaller screens.

## Scope Boundaries

This design does not change database schema, API contracts, authentication, payment behavior, or core business workflows. It does not introduce a CSS framework, JS bundler, frontend framework, or icon library. It avoids wholesale rewrites and keeps changes in existing HTML/CSS/JS patterns.

Inline styles can be migrated only when they directly block responsive polish or component consistency. Large structural refactors are out of scope unless required to make a viewport usable.

## Rollout Roadmap

Phase 1: UI foundation.

Create shared CSS tokens and utilities in `style.css`. Add responsive container, toolbar, button, input, table, modal, badge, and state-message rules. Keep compatibility with existing class names.

Phase 2: Customer homepage/menu/cart/payment.

Polish homepage, menu grid, reviews, floating cart, and QR/thank-you modals. Prioritize stable images, touch-friendly controls, mobile cart behavior, and no overflow.

Phase 3: Booking flow.

Polish booking filters, floor tabs, table grid, selected table summary, preorder area, confirmation form, and booking QR modal across desktop/tablet/mobile.

Phase 4: Auth/profile.

Polish login/register/forgot password and profile tabs/history/modals. Ensure forms, avatar upload, order history, booking history, review modal, and order detail modal behave well on mobile.

Phase 5: Admin system.

Standardize admin sidebar/page layout, dashboard cards/charts, filter bars, tables, badges, action buttons, pagination, and modals across all admin pages.

Phase 6: Visual QA and portfolio capture.

Run visual checks on desktop, laptop, tablet, and mobile widths. Capture demo screenshots for homepage, menu/cart, booking map, QR payment, profile, admin dashboard, and admin booking/POS.

## Verification Criteria

The update is successful when:

- The app keeps its current brand identity and recognizable layout.
- No major page has horizontal body overflow at 1440, 1366, 1024, 768, 430, 390, or 320px widths.
- Buttons and inputs remain at least 44px tall where they are primary touch targets.
- Modals fit within viewport height and allow internal scrolling where needed.
- Customer cart, booking, payment, profile, and admin table workflows remain usable on mobile.
- Existing JS and PHP verification scripts still pass.
- Portfolio screenshots look consistent enough to present as one product.

## Implementation Notes

Follow the existing vanilla CSS/JS architecture. Prefer CSS changes first, then minimal HTML class adjustments, then minimal JS only when a component needs state-specific classes for responsive behavior. Keep edits scoped and avoid introducing dependencies.

The implementation plan should be split into small, testable tasks, with visual verification after each major customer/admin phase.
