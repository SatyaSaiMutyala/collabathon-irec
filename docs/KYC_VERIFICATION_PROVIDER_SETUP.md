# KYC Verification API Keys — GST, Aadhaar & PAN (Sandbox + Production)

**Purpose:** To support the verification/auto-fill features on the Complete Profile screen — GST number lookup, Aadhaar verification, and PAN verification
**Prepared:** 9 August 2026 (updated to include Aadhaar and PAN)

---

## 1. Background

The Complete Profile screen collects a channel partner's GST number, Aadhaar number and PAN number. Rather than accepting these at face value, each should be verified against the issuing authority's records, and where possible the verified details (company name and address for GST, demographic details for Aadhaar) should auto-fill the form instead of being typed by hand.

None of these three checks has a free public API:

- **GST** — the official GST portal only offers a CAPTCHA-protected search page for humans, not something an app can call.
- **Aadhaar** — live, real-time Aadhaar OTP-based e-KYC (UIDAI sending an OTP and returning demographic data instantly) is restricted by law to entities permitted under the Telegraph Act / PMLA — banks, telecom, and a few notified categories. A platform like this does not qualify for that route. What's actually available and legally accessible is **Aadhaar Offline XML/QR verification**: the individual downloads a UIDAI-signed offline e-KYC file themselves from the UIDAI website, and the verification provider checks its digital signature and reads the demographic data from it — no live UIDAI OTP call involved. This is the real, currently-working option for a platform in this category.
- **PAN** — verified against Income Tax Department / Protean (NSDL) records via the same class of KYC provider. No special restriction here, which is why it works well as the fallback identity check when Aadhaar verification isn't available or applicable to a given user.

All three checks are offered by the same category of licensed **GSP (GST Suvidha Provider) / KYC verification vendors**, and in practice one account with one of them typically covers all three APIs — so a single signup should be enough.

---

## 2. What we'll need

For **both** the sandbox (testing) and production (live) environments, for whichever of GST / Aadhaar / PAN verification the chosen provider offers:
- An **API key** (sometimes called a token/secret) — sandbox and production keys are usually issued separately
- The **base URL** for each environment, per API
- A copy of, or link to, the provider's **API documentation** for each of the three endpoints (GST verification, Aadhaar Offline XML/QR verification, PAN verification)

Both sets of credentials (sandbox and production) will be needed — sandbox to build and test against, production to go live with.

---

## 3. Provider options

Any of the following offer all three checks under one account; they differ mainly in pricing and how self-serve the signup process is. If your organisation already has a relationship with one of them (or a similar KYC/verification vendor), that would be the simplest path.

| Provider | Website | GST | Aadhaar (offline XML/QR) | PAN | Notes |
|---|---|---|---|---|---|
| **Surepass** | https://surepass.io | Yes | Yes | Yes | Self-serve signup. |
| **Cashfree Verification Suite** | https://www.cashfree.com/verification-suite/ | Yes | Yes | Yes | Self-serve signup. |
| **IDfy** | https://www.idfy.com | Yes | Yes | Yes | Self-serve signup. |
| **Signzy** | https://signzy.com | Yes | Yes | Yes | Self-serve signup. |
| **Karza (Perfios)** | https://www.karza.in | Yes | Yes | Yes | May involve a sales conversation before credentials are issued. |

Pricing and signup terms change from time to time — we'd recommend checking each provider's current pricing/signup page directly rather than relying on this document for exact figures.

If a recommendation is useful as a starting point: **Surepass** or **Cashfree Verification Suite** tend to have the most self-serve signup flow across all three checks.

---

## 4. Steps to obtain the credentials

1. Choose a provider from the table above (or one your organisation already works with).
2. Sign up for a developer/business account on their site.
3. Locate **GST Verification**, **Aadhaar Verification (Offline XML/QR)**, and **PAN Verification** in their product/API list — confirm all three are available on the account.
4. Activate both the **sandbox/test** environment and the **production** environment — most providers require the production tier to be requested or upgraded to separately, which may involve billing details or a brief review on their end.
5. From the provider's developer dashboard, collect, for **each** environment (sandbox and production) and **each** API (GST, Aadhaar, PAN):
   - The API key (and any accompanying secret/token)
   - The base URL / endpoint
   - A link to, or PDF of, the relevant API documentation
6. Share those details once available.

---

Please let us know if there are any questions about any of the providers above, or if it would help to evaluate which one best fits your existing setup.
