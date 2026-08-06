# App Store & Google Play listing copy

Ready to paste. Every length-limited field has been counted against the store's limit.
Replace **Collabathon** throughout if the app name changes before submission.

---

## App identity (current values in the repo)

| | Value | Set in |
|---|---|---|
| App name (display) | `Collabathon` | `app.json`, iOS `Info.plist` → `CFBundleDisplayName`, Android `strings.xml` → `app_name` |
| iOS bundle ID | `com.app.collabathon` | `Collabathon.xcodeproj` → `PRODUCT_BUNDLE_IDENTIFIER` |
| Android package | `com.app.collabathon` | `android/app/build.gradle` → `applicationId` / `namespace` |
| Version | `1.0` (build `1`) | `build.gradle`, `MARKETING_VERSION` |

> The bundle ID cannot be changed once the app exists in App Store Connect. Decide it before
> the first submission. The splash image reads **Collabothon 2026** (different spelling, plus a
> year) while the app name is **Collabathon** — worth reconciling before store screenshots.

---

## Google Play

### Short description — 77 / 80 characters

```
Channel partner platform for real estate — live projects, requests, partners.
```

### Full description — 2,269 / 4,000 characters

```
Collabathon connects real estate channel partners with the developers whose projects they sell.

Instead of chasing project details over phone calls and forwarded PDFs, partners work from one shared, up-to-date catalogue — and developers see exactly who is asking about what.

Access is by approval. Channel partners register in the app and are reviewed by the platform team before sign-in; developer accounts are created by the team.

FOR CHANNEL PARTNERS

• Browse developers and the projects they currently have live
• Search by name and filter by city
• Open a full project sheet: price, configurations and unit sizes, location and connectivity, specifications, amenities, approvals, payment terms and the sales desk
• See the channel partner payout published for each project
• Register your interest in a project in one tap
• Track every request you have sent and where it stands
• Developer contact details are shared with you once your request is accepted
• Keep your accepted projects together under Partners
• Get notified when a developer responds

FOR DEVELOPERS

• A dashboard of live projects, interested leads and matched partners
• Lead activity over the week or month
• Review projects assigned to you and accept or decline them — accepting is what makes a project visible to channel partners
• Manage your inventory with search and filters by status, city, type and stage
• Review incoming partner requests with the partner's profile and credentials
• Accept a request to share your contact details and add them to your roster
• See every partner who has viewed or shown interest in a listing

WHAT YOU NEED

• An approved channel partner account, or a developer account created by the platform team
• Registration asks for the identity and registration documents required to empanel a channel partner, including your RERA registration details

Collabathon is a workflow tool for professionals in the property trade. It does not sell property, process payments, or handle transactions between parties, and it does not offer investment advice. Commercial terms, payouts and bookings are agreed directly between the channel partner and the developer.

Questions or feedback? Get in touch with the team through the contact details on your profile screen.
```

---

## Apple App Store

### Subtitle — 27 / 30 characters

```
Projects, partners, payouts
```

### Promotional text — 149 / 170 characters

```
Browse live projects, register your interest, and track every request in one place. Access is by approval — ask your platform contact for an account.
```

Promotional text can be changed without shipping a build, so it is the field to use for
"registration is open again" style notices later.

### Keywords — 90 / 100 characters

```
real estate,channel partner,broker,property,developer,RERA,projects,leads,inventory,realty
```

No spaces after the commas — spaces count against the 100 and waste the field.

### Description — 2,496 / 4,000 characters

Same as the Play description with one addition: a short PRIVACY paragraph. Apple reviewers
read the description alongside the privacy answers, and an app that uploads government ID
documents is easier to approve when the description itself says what happens to them.

```
Collabathon connects real estate channel partners with the developers whose projects they sell.

Instead of chasing project details over phone calls and forwarded PDFs, partners work from one shared, up-to-date catalogue — and developers see exactly who is asking about what.

Access is by approval. Channel partners register in the app and are reviewed by the platform team before sign-in; developer accounts are created by the team.

FOR CHANNEL PARTNERS

• Browse developers and the projects they currently have live
• Search by name and filter by city
• Open a full project sheet: price, configurations and unit sizes, location and connectivity, specifications, amenities, approvals, payment terms and the sales desk
• See the channel partner payout published for each project
• Register your interest in a project in one tap
• Track every request you have sent and where it stands
• Developer contact details are shared with you once your request is accepted
• Keep your accepted projects together under Partners
• Get notified when a developer responds

FOR DEVELOPERS

• A dashboard of live projects, interested leads and matched partners
• Lead activity over the week or month
• Review projects assigned to you and accept or decline them — accepting is what makes a project visible to channel partners
• Manage your inventory with search and filters by status, city, type and stage
• Review incoming partner requests with the partner's profile and credentials
• Accept a request to share your contact details and add them to your roster
• See every partner who has viewed or shown interest in a listing

WHAT YOU NEED

• An approved channel partner account, or a developer account created by the platform team
• Registration asks for the identity and registration documents required to empanel a channel partner, including your RERA registration details

PRIVACY

Documents you upload during registration are used only to verify you as a channel partner and are visible to the platform team. You can request deletion of your account and its data from your profile screen.

Collabathon is a workflow tool for professionals in the property trade. It does not list property for sale to the public, process payments, or handle transactions between parties, and it does not offer investment advice. Commercial terms, payouts and bookings are agreed directly between the channel partner and the developer.

Questions or feedback? Reach the team using the contact details on your profile screen.
```

> The PRIVACY paragraph promises in-app account deletion. That promise has to be true before
> you submit — see the account-deletion item below. A description that claims a feature the
> build does not have is itself a rejection under Guideline 2.3.1.

---

## Before you submit — the things that actually get apps rejected

These matter more than the wording. The copy above is written to be policy-safe (no invented
ratings, no "#1" or "best", no income guarantees, no claim that the app handles transactions),
but the following are open risks in the app itself.

### 1. Demo accounts are mandatory

Sign-in is approval-gated: a reviewer who registers lands on "awaiting approval" and can see
nothing. Both stores will reject on that alone.

- **Apple** — App Review Information → provide a working **channel partner** login *and* a
  **developer** login, plus a note that the two roles show different screens.
- **Google Play** — App content → App access → "All or some functionality is restricted", with
  the same two logins.

Keep those accounts alive and approved for as long as the app is listed.

### 2. Aadhaar, PAN and financial documents

Registration collects Aadhaar number, PAN, a cancelled cheque, and RERA certificate images.
Both stores treat these as sensitive personal data.

- **Google Play Data safety** — declare *Government ID*, *Financial info* and *Photos*. An
  undeclared category is grounds for suspension, not just rejection.
- **Apple App Privacy** — declare the same under the privacy "nutrition label".
- A **privacy policy URL** is required by both, and it must name this data explicitly, say where
  it is stored, how long it is kept, and how a user has it deleted.
- Aadhaar collection carries Indian regulatory obligations beyond store policy. Confirm the legal
  position separately — a store approval is not a compliance opinion.

### 3. In-app account deletion is required

Apple Guideline 5.1.1(v): any app that lets a user create an account must let them delete it
from inside the app. The profile screen currently offers **Log out** only. This will be
rejected until account deletion is added.

### 4. Screenshots

Current captures show live demo records — AED prices, real-looking names, phone numbers and
email addresses. Use sanitised demo data for store screenshots, and keep the currency consistent
with the market you are listing in.

### 5. Content rating & category

- Category: **Business** (both stores). "Real Estate" exists on Play as a subcategory.
- Play content rating questionnaire: no user-generated content shared publicly, no ads, no
  purchases — answer accordingly and it lands at *Everyone*.
