# Listing Compliance (DLD permit + approval)

In Dubai, a property ad can only be published if it has a **DLD advertising permit**. The brokerage
gets the permit from DLD through **Trakheesi**, and the permit comes with a **Madmoun QR code** that
buyers scan to check the ad. To get a permit, the brokerage needs the owner's marketing agreement,
**RERA Form A**. MW Realty is the portal: it **never issues permits**. It records them, Super Admin
checks them, and only then does the listing go live.

The "advertisement" in the DLD rules is **the listing itself**, not the banner ads under
CMS › Ads. Banner ads are MW Realty's own site promotions and aren't affected by this.

## Who approves what

| Step | Who | Where |
|---|---|---|
| Brokerage licence (ORN, trade licence) and agent BRN / RERA card | Super Admin | Portal Accounts (KYC). Already in place |
| Form A (owner → brokerage) | Owner + brokerage, registered with RERA | Outside the platform. The file is uploaded to the listing |
| Advertising permit + QR | DLD (Trakheesi) | Outside the platform. The number, expiry and QR are entered on the listing |
| Permit matches the ad, documents valid | Super Admin | Portal › Listings › **Listing Approvals** |
| Permit expiry | System | `properties:expire-permits`, daily at 00:05 UAE time |

## Flow

```
Agent / agency saves listing ── permit + QR + Form A missing ──▶ draft (offline)
            │ complete
            ▼
         pending ──▶ Super Admin: Approve ──▶ approved (goes live)
            ▲                 └─ Request changes (note) ─▶ changes_requested (offline)
            │                                                  │ agent fixes + saves
            └──────────────────────────────────────────────────┘
approved ── permit / Form A / price / purpose / type / beds / size changed ──▶ pending (offline)
approved ── permit expiry date passed ──▶ expired (offline, notified) ── renewed permit saved ──▶ pending
```

- `properties.compliance_status` is the review state. `properties.status` is still the website
  on/off switch, but it can only be `true` while `Property::canGoLive()` is true: the listing is
  approved, not sold, and its permit hasn't expired. This rule is checked on store, update, the
  card toggle, bulk Activate and sold → revert.
- If an approved listing is edited without touching its permit details (description, photos,
  amenities…), it stays live.
- MW Realty's own listings (created by Super Admin, no portal owner) are approved on save once their
  permit details are complete.
- A permit number can only be used on one listing (validation rule).
- Form A and title deed files are stored on the private `kyc` disk. They're served by
  `portal.properties.compliance-document`, which only the owner, the assigned agent or Super Admin
  can open. The QR is public media, because it's shown on the website.
- History: `property_compliance_logs`, shown on the review page.
- Notifications (bell + email for every event; a failed send is logged and never blocks the action):

  | Event | Super Admin | Agency / assigned agent |
  |---|---|---|
  | Submitted / resubmitted | Bell for each superadmin + email to the site notification address (`ListingReviewRequestedNotification`, `ListingReviewRequestedMail`) | "Sent for approval" bell + email |
  | Approved / changes requested / expiring in 7 days / expired | — | Bell + email with the admin's note (`ListingComplianceNotification`, `ListingComplianceMail`) |

- What the agency / agent sees: the **DLD permit review** strip on Properties / Commercial (a count
  for each state; click one to filter), the admin's note and a "Fix now" link on the card, a badge
  on the Properties menu for listings sent back or with an expired permit, and a banner at the top
  of the edit page. "Fix now" opens the DLD Permit tab (`#tab-compliance`).
- Super Admin: **Listing Approvals** has five status cards that work as tabs (Pending is first and
  pulses while there is a queue), and a review page with the QR, Form A, a checklist, the "does the
  ad match the permit" comparison and the history.

## Demo data

`php artisan db:seed --class=ListingComplianceDemoSeeder` gives every listing without a permit a
sample permit (number, expiry, QR image marked SAMPLE, a sample Form A PDF, title deed no). It then
moves 15 agency / agent listings into Pending (5), Permit details needed (4), Changes requested (3)
and Permit expired (3), with history. You can run it again safely: if listings are already in those
states, it leaves them alone.

## Existing listings

The migration marks every listing that already exists as `approved`, with a note, so the website
doesn't go empty. If one of these listings has no permit, it shows **No permit** under Listing
Approvals › Approved. Super Admin can send it back with *Take down & request changes*.

## Later: DLD API

DLD offers a Trakheesi Listing Validation API and a Delisting API (paid, with registration). Once
access is granted, call the API in `ListingComplianceService` before `approve()` and in the daily
command. Nothing else needs to change, because the rest of the app only reads `compliance_status`.

## Files

`ListingComplianceService`, `PortalListingApprovalController`, `ExpireListingPermits`,
`views/portal/listing-approvals/*`, and the **DLD Permit** tab in `views/portal/properties/_form.blade.php`.
Tests: `tests/Feature/ListingComplianceTest.php`.
