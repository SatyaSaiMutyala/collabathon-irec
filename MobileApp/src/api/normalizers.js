/**
 * Maps the API's property resource onto the view shape the property components render
 * (PropertyCard / PropertyHero / PropertyDetailBody).
 *
 * Kept at the store boundary rather than inside each component so there is exactly
 * one place that knows both shapes. If the API changes, only this file moves.
 *
 * Two rules this file exists to enforce:
 *   1. The app shows exactly the fields the admin panel still collects — no more, no
 *      less. The intake form was cut back (the Timeline & legal and Compliance & trust
 *      steps went entirely, the price band became a single entry price, and the three
 *      area figures per unit type stopped being asked for). Those columns still exist
 *      on the models and the API still emits them so old records keep what they hold,
 *      but a field nothing can edit any more must not be shown as if it were current —
 *      it reads as live data when it is a frozen leftover. The admin project sheet
 *      (resources/views/admin/properties/show.blade.php) is the reference for what is
 *      in and what is out; if a field returns to that form, add it back here.
 *   2. Media is grouped by `kind`, not filtered down to photos. `property_media.kind`
 *      carries eleven values; only one of them is `image`. The rest are brochures,
 *      price lists, plans, certificates, videos and tours, and they are the documents
 *      a channel partner actually needs in front of a client.
 */

const DAY_MS = 24 * 60 * 60 * 1000;

function daysSince(iso) {
  if (!iso) {
    return 0;
  }
  return Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / DAY_MS));
}

/**
 * Month and year only — for a possession date.
 *
 * A handover is a target month, not a calendar appointment, so naming an exact day
 * claims a precision the developer has not committed to. "March 2029" also reads like a
 * brochure where "2 Mar 2029" still reads like a record.
 */
function formatMonthYear(iso) {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  return Number.isNaN(date.getTime())
    ? null
    : date.toLocaleDateString('en-GB', {month: 'long', year: 'numeric'});
}

/** Joins a list into readable prose, or returns null so InfoRow drops the row. */
function listToText(value) {
  if (!Array.isArray(value) || !value.length) {
    return null;
  }
  return value
    .map(entry => {
      if (entry && typeof entry === 'object') {
        // JSON columns hold either plain strings or {label,value}-ish objects.
        const label = entry.label ?? entry.name ?? entry.title;
        const detail = entry.value ?? entry.distance ?? entry.detail;
        return [label, detail].filter(Boolean).join(' — ');
      }
      return String(entry);
    })
    .filter(Boolean)
    .join(' · ');
}

function sqft(value) {
  return value ? `${Number(value).toLocaleString()} sqft` : null;
}

/**
 * How each media kind is presented. `image` feeds the gallery; everything else is an
 * attachment the user opens externally, so each kind needs a label and an icon rather
 * than being lumped into one undifferentiated "files" list.
 */
const MEDIA_KINDS = {
  image: {label: 'Photo', icon: 'image-outline', group: 'gallery'},
  site_layout: {label: 'Site Layout', icon: 'map-outline', group: 'plans'},
  master_plan: {label: 'Master Plan', icon: 'grid-outline', group: 'plans'},
  floor_plan: {label: 'Floor Plan', icon: 'layers-outline', group: 'plans'},
  unit_plan: {label: 'Unit Plan / Layout', icon: 'home-outline', group: 'plans'},
  brochure: {label: 'Brochure', icon: 'document-text-outline', group: 'documents'},
  price_list: {label: 'Price List', icon: 'pricetags-outline', group: 'documents'},
  payment_schedule: {label: 'Payment Schedule', icon: 'calendar-outline', group: 'documents'},
  rera_certificate: {label: 'RERA Certificate', icon: 'shield-checkmark-outline', group: 'documents'},
  video: {label: 'Video', icon: 'videocam-outline', group: 'tours'},
  virtual_tour: {label: 'Virtual Tour', icon: 'cube-outline', group: 'tours'},
};

function normalizeMedia(media = []) {
  const usable = media.filter(item => item?.url);

  const bucket = group =>
    usable
      .filter(item => (MEDIA_KINDS[item.kind]?.group ?? 'documents') === group)
      .map(item => ({
        id: item.id,
        url: item.url,
        kind: item.kind,
        caption: item.caption,
        label: MEDIA_KINDS[item.kind]?.label ?? 'Attachment',
        icon: MEDIA_KINDS[item.kind]?.icon ?? 'document-outline',
      }));

  return {
    images: bucket('gallery').map(item => item.url),
    plans: bucket('plans'),
    documents: bucket('documents'),
    tours: bucket('tours'),
  };
}

/**
 * A unit-type row is now four things: the label, the price it starts at, how many there
 * are and its floor plan — the same four the intake form asks for. Carpet / built-up /
 * super built-up and the upper price are still on the API for records that have them,
 * but nothing collects them any more, so they are not carried through to the screen.
 */
function normalizeUnitTypes(units = []) {
  return units.map(unit => ({
    id: unit.id,
    label: unit.label,
    priceMin: unit.price_min,
    unitsCount: unit.units_count,
    floorPlanUrl: unit.floor_plan_url,
  }));
}

export function normalizeProperty(api) {
  if (!api) {
    return null;
  }

  const media = normalizeMedia(api.media);
  const units = api.unit_types ?? [];
  const detail = api.detail ?? {};
  const location = api.location ?? {};
  const price = api.price ?? {};
  const currency = price.currency ?? 'INR';
  // Emitted by PropertyResource on the show route only — absent on list payloads.
  const rera = api.rera ?? {};
  const scale = api.scale ?? {};
  const compliance = api.compliance ?? {};

  return {
    id: api.id,
    name: api.name,
    developerId: api.developer?.id,
    developerName: api.developer?.company_name,
    developer: api.developer ?? null,

    type: api.project_type,
    listingType: api.project_status,
    listingStatus: api.listing_status,

    // The developer-acceptance gate. `isLive` is the server's own answer to "can a
    // partner see this?", so no screen re-derives the two-key rule.
    developerStatus: api.developer_status,
    developerRespondedAt: api.developer_responded_at,
    developerDeclineReason: api.developer_decline_reason,
    isLive: api.is_live ?? false,

    /**
     * One price, not a band. Intake asks for "Starting from" alone now, so `priceUnit`
     * reads "onwards" rather than "– <max>" for anything entered since. Records saved
     * under the old two-field form still have an upper end, and those keep showing the
     * range — the same rule the admin sheet uses.
     */
    price: price.min ?? 0,
    priceUnit: !price.min
      ? '' // "0 onwards" is worse than a bare 0 on a project with no price yet.
      : price.max && price.max !== price.min
        ? `– ${price.max.toLocaleString()}`
        : 'onwards',
    currency,
    location: [location.locality, location.city].filter(Boolean).join(', '),
    postedDaysAgo: daysSince(api.created_at),

    coverImage: api.cover_image_url ?? media.images[0] ?? null,
    images: media.images,
    photoCount: media.images.length,
    // Everything that is not a photo — surfaced as openable attachments.
    plans: media.plans,
    documents: media.documents,
    tours: media.tours,
    attachmentCount: media.plans.length + media.documents.length + media.tours.length,

    commissionPercent: detail.cp_commission_percent ?? 0,
    fosCommissionPercent: detail.fos_commission_percent ?? 0,

    /**
     * The developer's terms for this project, when there are any.
     *
     * `hasTerms` is the server's own flag rather than a check on which field happens to
     * be populated — a project switched from document to text still has the old file on
     * record, and only the API knows which one is live.
     */
    hasTerms: detail.has_terms ?? false,
    terms: detail.terms
      ? {
          type: detail.terms.type,
          title: detail.terms.title,
          documentUrl: detail.terms.document_url,
          documentName: detail.terms.document_name,
          documentExtension: detail.terms.document_extension,
          content: detail.terms.content,
          excerpt: detail.terms.excerpt,
        }
      : null,
    amenities: detail.amenities ?? [],
    description: api.description ?? '',

    viewsCount: api.views_count ?? 0,
    interestsCount: api.interests_count ?? 0,
    // Kept in the API's own shape — screens read `my_lead.status` directly.
    my_lead: api.my_lead ?? null,

    unitTypes: normalizeUnitTypes(units),

    /**
     * Grouped the way the admin project sheet groups them, so a row an admin can see
     * there has one obvious counterpart here.
     *
     * Deliberately absent, because the intake form no longer collects them: the RERA
     * registered-on / valid-till dates, construction progress, launch date, price per
     * sq.ft., flats per floor, parking details, construction specifications, clubhouse
     * size, amenities count, awards, approving authorities and bank approvals.
     */
    details: {
      overview: {
        reraNumber: rera.number,
        projectType: api.project_type,
        projectStatus: api.project_status,
        listingStatus: api.listing_status,
        tagline: api.tagline,
        possessionDate: formatMonthYear(api.possession_date),
      },
      configuration: {
        // The unit the project's extent is quoted in — Sq.ft., Acres, Guntha, …
        extentMetric: price.extent_metric,
        // Prefer the property's own tally; fall back to summing the unit-type rows.
        totalUnits: scale.total_units
          ? String(scale.total_units)
          : String(units.reduce((sum, u) => sum + (u.units_count ?? 0), 0) || '') || null,
        towers: scale.towers ? String(scale.towers) : null,
        floors: scale.floors_per_tower ? String(scale.floors_per_tower) : null,
      },
      location: {
        city: location.city,
        state: location.state,
        locality: location.locality,
        fullAddress: location.full_address,
        landmark: location.landmark,
        pincode: location.pincode,
        zone: location.zone,
        mapsLink: location.maps_link,
        connectivity: listToText(detail.connectivity_highlights),
        nearbyInfrastructure: listToText(detail.nearby_infrastructure),
        coordinates:
          location.latitude && location.longitude
            ? `${location.latitude}, ${location.longitude}`
            : null,
      },
      specs: {
        landParcel: scale.land_parcel_acres ? `${scale.land_parcel_acres} acres` : null,
        totalProjectArea: sqft(scale.total_project_area_sqft),
        greenCertification: compliance.green_certification,
        // Yes *and* No, matching the admin sheet: it is a claim a partner may be asked
        // about, so "we checked and it is not" is worth as much as the positive. Only
        // once the detail payload is actually present, though — `compliance` is absent
        // from list responses, and a missing block must not be reported as "No".
        vastuCompliant: api.compliance ? (compliance.vastu_compliant ? 'Yes' : 'No') : null,
      },
      sales: {
        officeAddress: detail.sales_office_address,
        visitTimings: detail.site_visit_timings,
        contactName: detail.sales_contact_name,
        contactNumber: detail.sales_contact_number,
        bookingProcess: detail.booking_process,
      },
    },
  };
}

export function normalizeProperties(list = []) {
  return list.map(normalizeProperty);
}
