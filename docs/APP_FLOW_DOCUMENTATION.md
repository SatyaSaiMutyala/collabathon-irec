# iREC — Mobile App Flow Documentation

**Project:** Collabathon (iREC) — B2B Real Estate Matching Platform
**Prepared:** 24 July 2026
**Scope:** Mobile App (Broker/CP/Agent side + Developer/Builder side)

---

## 1. What This App Does

iREC connects **Property Developers/Builders** with **Brokers / Channel Partners (CP) / Agents**, under the oversight of a single **Admin**. Developers list properties; brokers browse them and express interest; the Admin manages onboarding and tracks every match end-to-end so deals can be closed.

There are **three roles** in the system:

| Role | Who they are | How they get in |
|---|---|---|
| **Admin** | The platform operator (single account) | Fixed login, no self-registration |
| **Developer / Builder** | Property owners/companies listing projects | Account created **by Admin** — never self-registers |
| **Broker / CP / Agent** | Independent agents sourcing buyers for listed properties | **Self-registers** in-app, then waits for Admin approval |

---

## 2. Current Build Status (at a glance)

| Area | Status |
|---|---|
| Broker app (registration, approval wait, login, browse, mark interest, profile, notifications) | ✅ Built |
| Developer app (dashboard, my properties, incoming broker leads, requests, profile, notifications) | ✅ Built |
| Admin app (approvals, property assignment, platform-wide monitoring) | ⬜ Not started |
| Backend / live data (currently runs on realistic placeholder data so the flows can be demoed end-to-end) | 🚧 In progress, separate track |
| Push notifications / real-time sync | ⬜ Not wired to a live backend yet |

**In short: both sides a broker and a developer interact with are fully clickable and demoable today. The Admin app — the third leg that approves brokers and assigns properties — has not been built yet.**

---

## 3. Broker / CP / Agent Flow

**Goal:** Register once, get approved, then browse developers' projects and raise interest on the ones they can place a buyer for.

1. **Role Select** — chooses "Broker / CP / Agent" on first launch.
2. **Register** — a 3-step empanelment form:
   - *Personal info*: name, mobile, alternate mobile, email, address, photo
   - *Professional info*: company details, years of experience, team size, PAN, Aadhaar, RERA number + certificate, cheque details, GST
   - *Business info*: state/city, segments (Residential/Commercial/Land/etc.), zones of operation, signature
3. **Pending Approval** — a holding screen while Admin reviews the registration.
4. **Login** — once approved, signs in with mobile number.
5. **Home** — browse all developers on the platform (verified badge, project count, filterable by city/location).
6. **Developer Profile** — tap a developer to see their RERA details, project list, and CP payout %.
7. **Project Detail** — full specs, pricing, amenities, and commission for a specific property, with a photo gallery.
8. **Mark as Interested** — one tap sends interest to the owning developer.
9. **Interested tab** — tracks status of every property the broker has raised interest on: *Pending* → *Accepted* (contact shared) or *Rejected*.
10. **Notifications** — real-time-style feed of status changes on the broker's interests.
11. **Profile** — full read-back of everything submitted at registration.

---

## 4. Developer / Builder Flow

**Goal:** See who's interested in their properties, unlock full contact details for serious leads, and decide who to accept.

1. **Developer Login** — credentials are issued by Admin (no registration screen exists for this role, by design).
2. **Dashboard** — snapshot of Properties / Interested Leads / Matches, plus a Profile Views trend chart (weekly or by-month).
3. **My Properties** — only the properties Admin has assigned to this developer (developers cannot add their own listings).
4. **Property → Broker Leads** — opening a property shows every broker who has viewed or shown interest in it (see the gating rule below).
5. **Requests tab** — a single combined list of all incoming broker interest across every property this developer owns, so nothing has to be found property-by-property.
6. **Accept / Decline** on an interested lead:
   - *Decline* — lead closes, no further action.
   - *Accept* — full contact is shared, and this is recorded as a confirmed match.
7. **Notifications** — feed of new views/interests/status changes.
8. **Profile** — company identity, contact person, CP payout %, city.

---

## 5. Core Business Rule: "Viewed" vs "Interested"

This is the single most important rule in the product, and it's enforced on every relevant screen:

- A broker **viewing** a property never exposes their contact details to the developer — only name and company are shown.
- Only once a broker actively taps **"Mark as Interested"** does their full contact information (mobile, email, RERA number) unlock for the developer.

This protects brokers from being contacted on the strength of casual browsing, and ensures developers only get contact access to leads with real intent.

---

## 6. Admin Flow (Not Yet Built)

For completeness — this is the third role, designed but not yet implemented:

- Reviews new broker registrations → **Approve** or **Reject**.
- Creates Developer accounts directly and issues their login credentials.
- Adds properties to the platform and assigns each one to a Developer.
- Monitors every view/interest/match across the whole platform, not just one developer's properties.
- Once a Developer accepts a broker's interest, Admin is notified and manually follows up to close the deal (deal-closing itself is an offline step, not an in-app transaction).

---

## 7. What's Configurable by Admin (Planned)

Two things are designed to be Admin-controlled rather than fixed in the app's code, so the platform can evolve without a rebuild:

- **Form fields** — the broker registration form and property listing form are built so fields can be added or removed by Admin rather than being hardcoded.
- **App theme color** — the app's primary color is centralized so Admin can re-brand the app's look without a code change.

---

## 8. What's Next

1. Build the Admin app (approvals, developer creation, property assignment, platform-wide monitoring).
2. Connect Broker and Developer apps to a live backend (registration, login, leads, matches currently run on realistic placeholder data so flows can be demoed today).
3. Wire real push notifications for approvals, new interests, and match confirmations.

---

*This document reflects the state of the mobile app as of 24 July 2026. Screens and flows described as "Built" are fully navigable in the current app build.*
