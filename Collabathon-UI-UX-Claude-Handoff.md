# Collabathon — UI/UX redesign and Claude implementation prompt

Prepared 16 September 2026. Based on the supplied six-colour logo and 16 screenshots. Several screenshots show different scroll positions of the same screen; they do not establish 16 separate routes. This is a design handoff, not an implementation or an accessibility certification. The repository was not supplied. Use the generated boards as visual direction and this specification as the authoritative source for behaviours and exact tokens.

## 1. Design decision

Follow the user's final instruction: avoid sharp square borders. Use rounded surfaces, clear typography, restrained brand accents, consistent spacing and light separators. Preserve the supplied logo's geometry; rounded UI does not mean rounding or modifying the logo itself. Treat the supplied mark as the requested brand reference despite its uploaded filename.

Collabathon is a professional real estate collaboration product with two visible roles: channel partner and developer. The redesign should make discovery, introduction requests and accepted relationships easier to understand while retaining every existing field and permission rule.

## 2. Findings from the screenshots

| Observed area | Current issue | Redesign |
| --- | --- | --- |
| Global presentation | Square white containers, repeated borders and strong bottom shadows make almost every group equally prominent | Rounded cards only where grouping is useful; use whitespace and light separators elsewhere |
| Brand | Most controls use a blue that differs from the supplied mark; Collabathon identity is not prominent | Purple anchors actions and selected navigation; reproduce the original logo in suitable top-level headers |
| Type and density | Large blocks of similar-sized text compete with headings; labels are pale | Inter with explicit size/weight roles; stronger label contrast; fewer competing weights |
| Home developer directory | Large uneven logos, long names truncated, tall rows | Uniform contain-fit logo slots, two-line company names, compact location/project metadata |
| Developer profile | Oversized identity area and long biography delay access to projects | Compact identity, short expandable overview, visible projects, grouped contact details |
| Project details | Long uninterrupted page; repeated status labels; facts nested inside boxes | Section anchors, concise summary, fact rows, expandable secondary details |
| Pricing and units | Different INR grouping styles; Project Extent Metric shows only Sq.ft. | Locale-aware formatting; a unit is not an area value; missing extent is Not provided |
| Location | Address, coordinates, transport and vicinity appear as a dense wall of text | Address block, map action, transport rows and grouped nearby places; coordinates secondary |
| Plans | Multiple indistinguishable Unit Plan / Layout labels | Preserve actual names, use neutral numbered fallback names only when metadata is absent |
| Requests | Tall image cards and repeated developer strips; status is easy to overlook | Compact project summary, prominent labelled status, sent date and clear next action |
| CP request management | Viewed-only and actionable requests are mixed; names are cut off | Separate viewed activity from pending requests; wrap names; consistent decision controls |
| Broker request | Important privacy explanation is lengthy and distant from decision context | Short lock notice near masked contact fields; explain sharing again at acceptance |
| Partners | Accepted relationship mixes project and contact content with weak action hierarchy | One relationship card with project context, accepted date and permitted contact actions |
| Profile | Giant identity card, long static information sections, visible sensitive identifiers | Compact identity and grouped expandable sections; mask identifiers in overview |
| Dashboard | Metrics, chart and listing are similarly weighted | Compact metrics, clean labelled chart, clear listing action hierarchy |
| Insets | Some screenshots show content at or behind system areas | Verify safe areas, sticky controls and bottom padding on both platforms |

## 3. Brand system

These are the dominant solid pixel colours extracted from the supplied PNG, not approximate guesses.

| Token | Hex | Intended role |
| --- | --- | --- |
| brand.purple | #622D91 | Primary buttons, links, selected navigation, focus emphasis |
| brand.cyan | #0096CA | Secondary decorative accent and selected data visualisation accents |
| brand.green | #178A3B | Accepted, verified and success states when backed by data |
| brand.yellow | #FFC612 | Attention accents with dark text |
| brand.orange | #F5831F | Pending/commission accents with dark text |
| brand.pink | #ED1458 | Small notification/error accents, paired with explicit labels |

Neutral utility colours are necessary for readable UI: background #F7F7FA, surface #FFFFFF, primary text #201B28, secondary text #625B6B, separator #E5E1E9. Use neutral text rather than adding unrelated brand hues. Tinted surfaces may be derived by blending the six brand colours with white. Do not change the logo artwork colours.

Purple is the default action colour across both roles. Do not assign a different primary colour to every screen. Use white on purple for primary buttons. Do not assume white text on cyan, orange, yellow or pink is accessible. Use tested dark text or a neutral surface with a small coloured accent. Status always includes words and, where helpful, an icon. A missing value is never a failure state.

### Type, layout and shape

Use Inter Regular 400, Medium 500, SemiBold 600 and Bold 700 if compatible with the existing font setup and licensing. Use native system fonts until assets are correctly bundled; do not simulate missing font weights. Keep one family across the application and provide script-appropriate fallbacks.

| Role | Initial size / line height | Weight |
| --- | --- | --- |
| Screen heading | 26 / 34 | 700 |
| Section heading | 20 / 28 | 600 |
| Price or primary metric | 28 / 36 | 700 |
| Card title | 17 / 24 | 600 |
| Body and inputs | 16 / 24 | 400 |
| Label and metadata | 14 / 20 | 400–500 |
| Bottom tab label | 12 / 16 | 500–600 |

These are scalable React Native logical units, not fixed screenshot pixels. Spacing scale: 4, 8, 12, 16, 20, 24, 32, 40. Screen gutters: 20; compact devices may use 16. Cards: 20 radius, 16–20 padding. Inputs/buttons: 14 radius, minimum 52 height. Sheets: 28 top radius. Chips: capsule. Touch targets: at least 48 by 48 as a product target, including small icon buttons. Inputs need a visible boundary or sufficiently contrasting surface even though square decorative borders are removed. Use one consistent outline icon family; avoid emoji as functional icons.

## 4. Screen scope and information architecture

### Channel partner journey

1. Home: greeting, location selector, notification entry, interested/accepted metrics, developer search, directory. Preserve actual metrics and search scope. Debounce remote search if applicable; include loading, clear and no-results states. Location selection must affect only data supported by the existing application.
2. Developer profile: original developer logo, name, location, account status and project count; expandable About; website and permitted contact information; project cards. Distinguish company establishment date from account membership date. Do not label a company verified unless the backend supports it.
3. Project overview: media gallery, title, locality, status, price, commission where permitted, category and possession date. Put readable text below photos instead of overlaying all metadata. An image count is preferable to many pagination dots. Keep all source images available.
4. Project details: residence configuration, price, units, tower/block count, floors, extent and unit, parcel, Vastu and amenities. Preserve zero, false and missing as different values. Never infer area from acreage without a specified conversion and intended field mapping.
5. Location: address, locality, city, state, country, postal code, zone, coordinates, map action, connectivity and vicinity. Group nearby entries by category. Retain the distinction between proposed and operational transport. Travel times remain supplied estimates, not live verified journey times.
6. Files and sales: actual unit plans and site layouts; brochures, price lists/certificates only if present; video and virtual tour; sales office, hours and permitted contact. Show file type/size only when known. Offer native viewer or existing viewer, loading, failure and retry states. Do not expose a restricted sales contact just because it appears in a developer-facing screenshot.
7. Requests: All/Pending/Accepted/Declined filters mapped to real backend statuses; project, developer, sent date and next step. Do not add withdrawal, messaging or resend functionality without an existing supported action.
8. Partners: accepted introductions with their project and developer context, acceptance date, authorised contact details and native call/email actions. Never count an unaccepted request as a partner.
9. My profile: identity, verification state, personal information, professional information and business documents. Preserve suffix, names, phones, email, address, company-registration flag, state/city, segment, zone, contributions and multi-state flag. PAN/Aadhaar/RERA/GST overview values should be masked as appropriate, with authorised detail access retained.

### Developer journey

10. Dashboard: active listings, pending requests and introductions; engagement period selector and chart; listing preview. The screenshots show 6 dashboard requests and 14 project-level request entries. These may have different scopes; do not force the counts to match or assume a bug. Define each count from actual query semantics.
11. Listings: preserve existing list, filters, create/edit/publish capabilities and permissions. No dedicated listings screen was supplied, so adapt the shared project-card system after inspecting the repository. Keep publication state separate from construction status.
12. CP requests: distinguish Viewed activity from Requested introduction. Only actionable requests receive decision buttons. Wrap names, show company and available credential metadata, masked contacts and clear status. Add list filters only where data supports them.
13. Broker request detail: identity, project requested, credentials, type, joining date and other existing information. Keep pending contacts masked. Present a short acceptance confirmation explaining which parties' details will be shared, based on actual business logic. Declining retains privacy.
14. Developer partners and profile: apply the same relationship and profile components with role-specific fields and permissions. Their full screens were not supplied; inspect actual routes instead of inventing fields.

Preserve existing role navigation: channel partner Home / Requests / Partners / Profile; developer Dashboard / Listings / Partners / Requests / Profile. Detail screens use back navigation and contextual actions. Project section anchors are not new global tabs. Existing login, onboarding, notifications, filters, edit forms, document previews and settings must receive the same system after the repository audit; they were not visible in the screenshots.

## 5. Synchronisation and behaviour

Every screen must render request, project, developer and user entities from the existing authoritative data source. Avoid separate local copies that drift. After a successful mutation, update or invalidate relevant request lists, detail views, partner lists, dashboard metrics and notification counts with the correct role/project scope. Preserve active filters and scroll state. Cancel or ignore stale search responses. Never reveal masked contact information optimistically: wait for server-authorised data after acceptance.

Support submitting, success, failure, timeout and conflict states. Disable repeated decision submissions while in flight; maintain server-supported idempotency if available. On a stale request, refresh and explain the current status. Failed requests must not appear accepted. After reauthentication or role changes, discard incompatible cached private data.

Use consistent price formatting. For the supplied example, INR 75,000,000 = ₹7,50,00,000 = ₹7.50 Cr. A compact card can show ₹7.50 Cr onwards; detail/accessibility text must retain the exact price and currency. International support means locale-aware display and explicit currency, not automatic exchange-rate conversion. Persist numeric amounts independently of labels. Externalise UI strings and allow longer translations, RTL layouts and flexible postal/phone formats when supported by the product.

## 6. Quality gates

Target readable contrast: at least 4.5:1 for normal text and 3:1 for large text; essential controls require appropriate non-text contrast. These targets follow [W3C WCAG 2.2](https://www.w3.org/TR/WCAG22/). This handoff does not certify conformance.

Implement meaningful labels, roles, selected/expanded/busy/disabled states and logical reading order using [React Native accessibility APIs](https://reactnative.dev/docs/accessibility). Respect text scaling rather than globally disabling it; see [React Native Text](https://reactnative.dev/docs/text). Verify real VoiceOver and TalkBack behaviour, not just prop presence.

Check narrow phones, typical Android/iOS phones, larger screens, large fonts, keyboard visibility, safe areas, long names/emails, slow/offline networks, empty lists, failed images and missing data. Sticky actions must not cover content or system navigation. Use reduced motion when enabled. Keep chart values available in text. Do not invent positive analytics trends or sample counts in production.

Generated reference boards are illustrative. Their image text, chart geometry, logo rendition and minor spacing can differ from the specification. Use the original logo, actual property assets and repository data in production. Do not bake a generated screen into an image and use it as the UI.

---

## 7. Copy-paste master prompt for Claude in VS Code

Copy everything between BEGIN PROMPT and END PROMPT into Claude. Attach this file, the original logo, current screenshots and generated reference boards to the same task when available.

### BEGIN PROMPT

Act as a senior React Native engineer and mobile product designer. Implement a coherent UI/UX redesign of the existing Collabathon application in this repository. The app already works. Inspect it before changing it, preserve business functionality, and carry the redesign through every existing screen and shared component. Use the attached Collabathon handoff as the authoritative specification and the reference boards as visual guidance. Do the implementation, not only a proposal.

FIRST: Read repository instructions, package manifests, lockfile, navigation, role guards, API services, state/cache setup, fonts, icons, shared components and tests. Determine React Native version and Expo/bare setup. Make a route inventory for channel partner and developer roles, mapping each route to its source files, data dependencies, permissions and primary actions. Include screens absent from the screenshots. Do not claim to have inspected missing files. Preserve existing uncommitted work and do not rewrite the project scaffold.

DESIGN RULES:
- Use the supplied logo unchanged. Brand tokens: purple #622D91, cyan #0096CA, green #178A3B, yellow #FFC612, orange #F5831F, pink #ED1458. No unrelated accent palette. Purple is the shared primary action and selected-navigation colour.
- Utility neutrals: background #F7F7FA, white #FFFFFF, text #201B28, secondary #625B6B, separators #E5E1E9. Derive subtle brand tints with white. Do not apply all six saturated colours to every screen.
- Use rounded 20-radius cards, 14-radius inputs/buttons, 28-radius sheet tops and pill badges. Avoid sharp square decorative containers, heavy shadows and boxes nested inside boxes. Retain visible accessible input boundaries.
- Use Inter 400/500/600/700 through the existing compatible font loader, with native fallback. One family, sensible script fallback, no fake weights. Type sizes/line heights: heading 26/34, section 20/28, metric 28/36, card title 17/24, body 16/24, metadata 14/20, tab label 12/16. Respect user scaling and wrap content instead of imposing fixed text heights.
- Spacing scale 4/8/12/16/20/24/32/40. Screen padding 20, 16 on compact devices. Minimum 48 touch targets, primary controls minimum 52 high. Use one consistent icon set already supported by the project.
- Primary CTA white text on purple. Use dark readable text on bright yellow/orange and test all colour pairs. Target WCAG AA contrast; status needs a word, not colour alone.
- Safe-area-aware top/bottom layout, keyboard avoidance, responsive content widths, correct sticky-action padding. Keep meaningful labels visible on bottom navigation. No gratuitous animation or rainbow gradients.

IMPLEMENTATION ARCHITECTURE:
Create or consolidate theme tokens and reusable primitives appropriate to the actual repository. Suggested responsibilities: Screen, AppText, Header, Button, IconButton, SearchField, Surface, SectionHeader, StatusBadge, Avatar, DeveloperRow, ProjectCard, Metric, InfoRow, DocumentRow, PrivacyNotice, SegmentedControl, EmptyState, ErrorState, Skeleton and BottomActionBar. These names are illustrative; integrate existing abstractions instead of duplicating them. Centralise role/status mappings, formatters and accessibility defaults. Avoid scattered colours and arbitrary spacing in screens.

Preserve installed navigation, state management, networking and authentication libraries. Do not upgrade dependencies or introduce a UI framework as a shortcut. Use compatible platform APIs for the installed version. Do not fabricate API endpoints, fields or business permissions. Do not replace real queries with mock data. Use existing list virtualisation and image caching where available; avoid unbounded nested scrolling and unnecessary rerenders.

SCREEN IMPLEMENTATION:
1. Channel partner Home: compact greeting/location/bell; interested and accepted metrics; search; uniform developer rows with contain-fit original logos and wrapping company names.
2. Developer profile: compact identity/status/location/project count; expandable About; accessible website/contact/address rows; visible project cards. Keep company founding date distinct from app joining date.
3. Project overview: gallery and image count, title/locality/status, exact/compact price, permitted commission, category and possession. Clear section anchors Overview, Details, Location, Files. Preserve all original project fields through accessible sections.
4. Project details: residence pricing, configuration facts, extent plus unit, units/towers/floors/parcel, Vastu and amenities. Use Not provided for missing information; false and zero remain valid data. Avoid nested square statistic boxes.
5. Location: readable full address, locality/city/state/country/postal/zone, map action, secondary coordinates, transport and grouped nearby places. Preserve proposed transport labels and supplied travel-time context.
6. Files/sales: distinct plan names or neutral numbered fallbacks; brochures and actual documents; video/virtual tour; sales office/hours/authorised contacts. Handle viewer errors. Do not invent file metadata or unlock documents/contacts.
7. Requests: backend-supported status filters, compact consistent request/project/developer cards, sent dates and state-specific actions. Preserve all status meanings.
8. Accepted Partners: relationship/project/developer context, accepted date and server-authorised call/email actions. Never display pending contacts as accepted relationships.
9. Profiles: compact identity and real verification state; grouped personal/professional/business sections. Preserve all fields and supported edit actions. Mask sensitive identifiers in summary; retain authorised document access.
10. Developer Dashboard: concise metrics, Week/Month engagement with real data and accessible textual values, listing preview. Preserve query scopes; project request totals and dashboard pending totals may differ.
11. Listings: reuse project cards and keep existing create/edit/publish/filter capabilities, role guards and routes.
12. CP Requests: separate viewed activity from actionable introductions; wrapping names, company/credentials, masked contacts, lock notice and consistent Accept/Decline controls.
13. Broker request detail: complete identity/project/credentials/type/join date; masked pending contacts; safe-area decision bar. Confirm acceptance with the actual contact-sharing implications.
14. Apply the same tokens and primitives to every remaining existing route, including auth, notifications, settings, forms, filters, previews and developer partner/profile routes. Do not invent missing product capabilities.

STATE AND PRIVACY:
Keep authoritative server state and existing security logic. After a successful accept/decline/request mutation, update or invalidate all relevant detail/list/dashboard/partner/notification caches for the correct role and scope. Never optimistically reveal contacts. Refetch authorised contact data after confirmed acceptance. Prevent double submissions, handle stale/conflicting decisions, and preserve pending state on failure. Do not simulate backend success or undo an irreversible operation without API support. On role/account changes clear incompatible cached private data. Any discovered server-side permission flaw must be reported explicitly; client-side hiding alone is not a fix.

GLOBAL UX:
Include loading, empty, no-results, error/retry, offline/stale and disabled/submitting/success states. Preserve search/filter/scroll context on return navigation. Handle long names/emails and missing images. Use existing locale and currency utilities; never convert currency silently. For INR 75,000,000 the compact value is ₹7.50 Cr and full Indian grouping is ₹7,50,00,000. Externalise strings following existing localisation patterns. Respect text scaling, screen readers, reduced motion and RTL where applicable.

WORK SEQUENCE:
Audit routes and dependencies; implement tokens and primitives; migrate discovery and developer profile; migrate all project sections; migrate requests and partners; migrate developer management; migrate profiles and remaining routes; verify cross-screen state and visual consistency. Keep a checklist so no route is silently omitted. Proceed with routine decisions. If a required backend capability is absent, preserve current behaviour, document the limitation and continue the remaining redesign.

VALIDATION AND DELIVERY:
Run existing lint/typecheck/tests and available builds. Add meaningful regression coverage for state transitions/contact masking only where appropriate. Test acceptance updates relevant views, decline preserves masking, failed decisions remain pending, and viewed-only entries have no decision controls. Check representative 360/390/430 logical-pixel widths and large text, plus keyboard/safe-area interactions. Verify VoiceOver/TalkBack when devices are available. Capture before/after screenshots of changed journeys where possible. Do not claim device checks or builds you could not run.

Finish with the route checklist, changed files, design-system location, tests/builds actually run, known limitations and any unmet backend dependency. The outcome must be working native components across the existing app, with consistent colours, rounded shapes, typography, navigation and state. Do not substitute generated mockup images for functional screens.

### END PROMPT
