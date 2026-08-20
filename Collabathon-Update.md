# Collabathon — Product Update

A round of fixes and improvements across registration, notifications, and the property & developer pages — focused on reliability during broker onboarding and a faster, less repetitive experience across the app.

**Scope:** Channel Partner & Developer apps
**Type:** Fixes + improvements
**Status:** Ready for review

---

## Registration & Document Upload

- **[Fixed]** Registration could silently fail to complete on a slow connection. The final submission step (uploading PAN, Aadhaar, RERA and GST documents together) could time out without a clear reason on weaker networks, leaving a broker stuck without knowing why.
- **[Improved]** Documents now upload the moment they're selected, one at a time, with a visible progress state — instead of all four being sent together at the very end. Submission is faster and far less likely to fail.
- **[Fixed]** A signed-out, then newly registered broker could briefly see the previous account's mobile number pre-filled. Each new registration now starts from a fully clean session.

## Notifications

- **[Fixed]** Tapping a notification on the Channel Partner side did nothing. It now opens the relevant property directly, matching how it already worked on the Developer side.
- **[Improved]** Consistent wording across every notification, push alert and email — all now refer to a broker's action as a *request*, replacing the previous mix of "interest" and "request" language.

## Home Screen & Location

- **[New]** The Home screen now detects the broker's current location automatically and applies it as the city filter the moment the app opens — no manual step required.
- **[New]** If a device's location is turned off, the app now prompts the user to enable it, with a direct link to their phone's location settings.
- **[Fixed]** The Requests and Partners tabs weren't refreshing automatically. Marking a property as interested elsewhere in the app now reflects immediately on both tabs, without a manual pull-to-refresh.

## Property & Developer Pages

- **[Improved]** Reorganized the Property and Developer Profile pages so short details (city, floors, land parcel, contact info and similar) sit two to a row instead of each taking a full line — noticeably less scrolling, with every field still shown.
- **[Improved]** The Developer Profile header now shows account status as a clear badge next to the developer's name, and shows how long they've been on Collabathon in place of the old Verified/Unverified label.
- **[Improved]** Clearer button and status copy on the property page — "I'm Interested" and "Request Sent — Awaiting Developer Confirmation" replace the earlier, less specific wording.
- **[Fixed]** Uneven spacing around the "awaiting developer" status banner on the property page, which previously sat flush against the screen edges.
- **[Fixed]** A rare display glitch on properties with no uploaded photos.

---

All changes above have been reloaded and verified on a live device today. Happy to walk through any of these together, or prioritize the next round based on your feedback.

— The Collabathon build team
