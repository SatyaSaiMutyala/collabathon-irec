# iREC / Collabathon — Project Status

**Prepared:** 29 July 2026
**Purpose:** A single up-to-date snapshot of everything built so far across the mobile app, the admin web panel, and the backend — for anyone (including future-us) picking this project back up.

---

## 1. What This Product Is

iREC is a B2B real estate matching platform connecting **Property Developers/Builders** with **Brokers / Channel Partners (CP) / Agents**, overseen by a single **Admin**. Developers list properties; brokers browse them and raise interest; the Admin onboards everyone and tracks matches end-to-end.

Three roles:

| Role | Who they are | How they get in |
|---|---|---|
| **Admin** | Platform operator (single account) | Fixed login, no self-registration |
| **Developer / Builder** | Property owners/companies listing projects | Account created **by Admin** — never self-registers |
| **Broker / CP / Agent** | Independent agents sourcing buyers | **Self-registers** in-app, then waits for Admin approval |

**Core business rule (enforced everywhere):** a broker *viewing* a property never exposes their contact details to the developer — only name and company show. Only once a broker taps **"Mark as Interested"** does full contact info (mobile, email, RERA number) unlock for the developer. This "viewed vs. interested" gate is the single most important rule in the product.

---

## 2. Build Status at a Glance

| Area | Status |
|---|---|
| Broker mobile app (register → approval → login → browse → interest → profile → notifications) | ✅ Built |
| Developer mobile app (dashboard, my properties, broker leads, requests, profile, notifications) | ✅ Built |
| Admin web panel UI (dashboard, approvals, developers, properties, leads, settings) | ✅ Built |
| Laravel backend — database schema, models, auth, real API | ✅ Built |
| Mobile app wired to the **real** API (mock data removed) | ✅ Done |
| Admin panel wired to the **real** database (no more static mock arrays) | ✅ Done |
| Admin app **authentication/session security hardening** | 🚧 In progress |
| Push notifications / real-time sync (sockets, Firebase) | ⬜ Not wired yet |
| Production deployment | ⬜ Not started |

This is a big shift from two weeks ago: the app was fully clickable but ran on placeholder (`mockDevelopers.js`) data with no backend at all. As of this week there is a real Laravel + MySQL/SQLite backend, a real REST API secured with Sanctum tokens, and both the mobile app and the admin panel talk to it live.

---

## 3. Mobile App (`MobileApp/`) — React Native CLI, plain JavaScript

### Broker / CP / Agent side
- **Register** — 3-step KYC-style empanelment form (numbered section headers): Personal info (name, mobile, alt. mobile, email, address, photo), Professional info (company, years of experience, team size, PAN, Aadhaar, RERA + certificate, cheque, GST), Business info (state/city, segments, zones, signature pad).
- **Pending Approval** screen while Admin reviews the registration.
- **Login** with mobile + password (now hitting the real `/auth/login` endpoint).
- **Home** — browse all developers, filterable by detected/chosen city (live geolocation + reverse-geocoding), verified badge + project count per developer.
- **Developer Profile** → full RERA details, project list, CP payout %.
- **Project Detail** — full specs, pricing, amenities, commission, multi-image swipeable gallery.
- **Mark as Interested** — one tap, notifies the developer.
- **Interested tab** — tracks status per property: Pending → Accepted (contact shared) / Rejected.
- **Notifications** — feed of status changes on the broker's own interests.
- **Profile** — full read-back of every field submitted at registration.

### Developer / Builder side
- **Developer Login only** — no registration screen, matches the "never self-registers" rule.
- **Dashboard** — Properties / Interested Leads / Matches stat row, Profile Views trend chart (custom-built gradient area chart, Week/Month toggle with a real month picker), "My Properties" preview.
- **My Properties** — only properties Admin has assigned to this developer.
- **Property → Broker Leads** — full property detail (shared hero/body components with the broker side) plus every broker who viewed/showed interest, gated by the viewed-vs-interested rule.
- **Requests tab** — one combined list of incoming broker interest across all of the developer's properties.
- **Accept / Decline** on interested leads — accept unlocks contact both ways and is recorded as a match.
- **Notifications**, **Profile** (company identity, contact person, CP payout %, city).

### Cross-cutting mobile app work
- **Real API layer** (`src/api/`: `client.js`, `config.js`, `endpoints.js`, `normalizers.js`) replacing the old `mockDevelopers.js`/`mockLeads.js` data files — auth, dashboard, properties, developers, and leads all now call the live Laravel API (platform-aware dev host: `127.0.0.1` on iOS simulator, `10.0.2.2` on Android emulator).
- Role-aware navigation (`RootNavigator` branches Auth / Broker stack / Developer stack), Redux Toolkit store, theme system (gold/navy palette, Outfit font, `size-matters` responsive scaling), and a reusable component library (`Button`, `Input`, `Card`, `Dropdown`, `SwipeableImages`, `TrendChart`, `SignaturePad`, `BrokerLeadCard`, etc.) — all built with **zero extra native dependencies** where a plain-React-Native technique would do, to avoid repeating an earlier iOS Podfile/Firebase build fragility.
- Session persistence (stays logged in across app restarts), location detection for the broker Home screen, notifications screen shared by both roles.
- **Admin mobile app has not been built** — Admin is web-only today (see §4).

---

## 4. Admin Web Panel (`collabathon-backend/`) — Laravel 13 + Blade + Tailwind v4 + Alpine.js

Originally built as a UI-only pass (26 July) on static mock arrays; **as of 28–29 July it is wired to the real database** through dedicated `Admin\*` controllers, alongside the mobile-facing `Api\*` controllers in the same Laravel app.

Pages built (all six Admin responsibilities from the flow design):
- **Dashboard** — live stat tiles (developers, active brokers, listings, pending approvals, confirmed matches) each with a real 12-week sparkline, an engagement trend (views vs. interests), a viewed→interested→accepted funnel, top properties by interest, and a recent cross-entity activity feed — all computed from real Eloquent queries, not mock arrays (see `Admin\DashboardController`, grouped/aggregated queries rather than one query per data point).
- **Broker Approvals** — pending-registrations table with Approve/Reject, recent-decisions history.
- **Developers** — table + "Add Developer" modal (company/contact/mobile/city/email/CP-payout).
- **Properties** — table + "Add Property" modal with developer assignment.
- **Leads & Matches** — platform-wide activity table, explains the viewed-vs-interested gating rule inline, status badges (viewed/interested/accepted/declined).
- **Settings** — per-group toggle list for admin-configurable form fields, plus a theme-color swatch picker.

Auth is real: `/login` behind Laravel's `guest` middleware, an `EnsureAdmin` middleware gates the whole `/admin` prefix, matching the same Sanctum-backed `User` model the mobile API uses. (This authentication path is the piece still being actively hardened — see §2.)

Visual identity matches the mobile app exactly (same hex tokens ported from `MobileApp/src/theme/palette.js`, same Outfit font via Bunny Fonts) so the two clients read as one product.

---

## 5. Backend / API (`collabathon-backend/`) — Laravel 13

### Data model (migrations, 28–29 July)
`users` (extended with `role` + `status`), `broker_profiles`, `developers`, `properties`, `property_details`, `property_unit_types`, `property_media`, `leads`, `approval_decisions`, `settings` + `form_fields`, plus Sanctum's `personal_access_tokens`.

### Models
`User` (role/status scopes: `ROLE_BROKER`, `STATUS_ACTIVE`, `STATUS_PENDING`, etc.), `Developer`, `Property`, `PropertyDetail`, `PropertyUnitType`, `PropertyMedia`, `Lead` (`contact_unlocked` flag drives the viewed-vs-interested gate server-side), `BrokerProfile`, `ApprovalDecision`, `Setting`, `FormField`.

### Mobile REST API (`routes/api.php`, prefix `/api/v1`, Sanctum token auth)
- `POST /auth/register`, `POST /auth/login` (rate-limited 10/min — credential-stuffing surface).
- `GET /auth/me`, `POST /auth/logout`, `GET /dashboard` (all token-protected).
- Catalogue: `GET /developers`, `GET /developers/{id}`, `GET /developers/{id}/properties`, `GET /properties`, `GET /properties/{id}` — every list route supports pagination + search/sort/filter query params, capped server-side.
- Broker actions: `POST /properties/{id}/view`, `POST /properties/{id}/interest`.
- Leads: `GET /leads` (scoped to the caller's role inside the controller), `PATCH /leads/{id}` (accept/decline).

### Admin web routes (`routes/web.php`)
Session-based (not token) auth behind `EnsureAdmin`, covering dashboard/approvals/developers/properties/leads/settings as described in §4.

### Local dev environment
PHP 8.5 + Composer via Homebrew; XAMPP installed for MySQL (per explicit preference for MySQL over SQLite long-term); `php artisan serve` (127.0.0.1:8000) + `npm run dev` (Vite, 5173) both need to run for the admin panel; the mobile app points at the same Laravel instance via `MobileApp/src/api/config.js`.

---

## 6. What's Configurable by Admin (by design)

Two things are deliberately data-driven rather than hardcoded, so the platform can evolve without a rebuild:
- **Form fields** — broker registration and property listing fields are meant to be added/removed by Admin (`form_fields` table + Settings page groundwork is in place).
- **App theme color** — centralized in both clients (mobile `ThemeContext`, admin CSS tokens) so Admin can re-brand without a code change (Settings page has the swatch picker; full end-to-end persistence of a chosen color is not yet confirmed wired).

---

## 7. Known Gaps / What's Next

1. **Admin mobile app** — no mobile UI for the Admin role yet; Admin is web-only.
2. **Auth/session hardening** — the Admin login/session path is mid-fix (uncommitted work in `Api/DeveloperController.php` and the mobile auth screens as of this writing).
3. **Real-time notifications** — push notifications and live socket updates (approvals, new interests, match confirmations) are not wired to the backend yet; the notifications UI exists but is currently poll/derive-on-open, not push-driven.
4. **Production deployment** — everything above runs locally (XAMPP/Homebrew PHP + Metro); no staging/production environment yet.
5. **End-to-end reconciliation pass** — confirm every screen in both mobile roles and the admin panel is reading exclusively from the live API with no leftover mock-data assumptions.

---

*This document reflects the state of the codebase as of 29 July 2026 (through commit `b1a5cb0`, "merge and fix the developer creation"), superseding the 24 July `docs/APP_FLOW_DOCUMENTATION.md` snapshot for backend/admin status.*
