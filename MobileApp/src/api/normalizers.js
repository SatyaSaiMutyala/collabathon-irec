/**
 * Maps the API's property resource onto the view shape the property components render
 * (PropertyCard / PropertyHero / PropertyDetailBody).
 *
 * Kept at the store boundary rather than inside each component so there is exactly
 * one place that knows both shapes. If the API changes, only this file moves.
 *
 * Two rules this file exists to enforce:
 *   1. Nothing the API sends is dropped silently. The property spec is ~69 fields
 *      across `properties` + `property_details`, and every populated one has to reach
 *      the detail screen — a field the admin captured but the app never shows is
 *      indistinguishable, to the user, from a field that was never captured.
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

/** "3BHK" -> 3. Bedrooms are not a column; they come from the unit-type label. */
function bedroomsFromLabel(label = '') {
  const match = String(label).match(/(\d+)\s*BHK/i);
  return match ? Number(match[1]) : null;
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

function normalizeUnitTypes(units = []) {
  return units.map(unit => ({
    id: unit.id,
    label: unit.label,
    carpetArea: sqft(unit.carpet_area_sqft),
    builtUpArea: sqft(unit.built_up_area_sqft),
    superBuiltUpArea: sqft(unit.super_built_up_area_sqft),
    priceMin: unit.price_min,
    priceMax: unit.price_max,
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
  const primaryUnit = units[0] ?? {};
  const detail = api.detail ?? {};
  const location = api.location ?? {};
  const price = api.price ?? {};
  const currency = price.currency ?? 'AED';
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
    price: price.min ?? 0,
    priceUnit: price.max && price.max !== price.min ? `– ${price.max.toLocaleString()}` : '',
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
    bedrooms: bedroomsFromLabel(primaryUnit.label) ?? units.length,
    bathrooms: bedroomsFromLabel(primaryUnit.label) ?? units.length,
    areaSqft: primaryUnit.carpet_area_sqft ?? 0,
    amenities: detail.amenities ?? [],
    description: api.description ?? '',

    viewsCount: api.views_count ?? 0,
    interestsCount: api.interests_count ?? 0,
    // Kept in the API's own shape — screens read `my_lead.status` directly.
    my_lead: api.my_lead ?? null,

    unitTypes: normalizeUnitTypes(units),

    details: {
      overview: {
        reraNumber: rera.number,
        reraRegisteredAt: rera.registered_at,
        reraValidTill: rera.valid_till,
        projectType: api.project_type,
        projectStatus: api.project_status,
        listingStatus: api.listing_status,
        tagline: api.tagline,
        launchDate: compliance.launch_date,
        possessionDate: api.possession_date,
        constructionProgress: api.construction_progress
          ? `${api.construction_progress}%`
          : null,
      },
      unit: {
        unitTypes: units.map(u => u.label).filter(Boolean).join(', ') || null,
        carpetArea: sqft(primaryUnit.carpet_area_sqft),
        builtUpArea: sqft(primaryUnit.built_up_area_sqft),
        superBuiltUpArea: sqft(primaryUnit.super_built_up_area_sqft),
        pricePerSqft: price.per_sqft ? `${currency} ${price.per_sqft.toLocaleString()}` : null,
        // Prefer the property's own tally; fall back to summing the unit-type rows.
        totalUnits: scale.total_units
          ? String(scale.total_units)
          : String(units.reduce((sum, u) => sum + (u.units_count ?? 0), 0) || '') || null,
        towers: scale.towers ? String(scale.towers) : null,
        floorsPerTower: scale.floors_per_tower ? String(scale.floors_per_tower) : null,
        flatsPerFloor: scale.flats_per_floor ? String(scale.flats_per_floor) : null,
        parking: detail.parking_details,
      },
      location: {
        locality: location.locality,
        fullAddress: location.full_address,
        landmark: location.landmark,
        mapsLink: location.maps_link,
        city: location.city,
        state: location.state,
        zone: location.zone,
        pincode: location.pincode,
        connectivity: listToText(detail.connectivity_highlights),
        nearbyInfrastructure: listToText(detail.nearby_infrastructure),
        coordinates:
          location.latitude && location.longitude
            ? `${location.latitude}, ${location.longitude}`
            : null,
      },
      specs: {
        totalProjectArea: sqft(scale.total_project_area_sqft),
        landParcel: scale.land_parcel_acres ? `${scale.land_parcel_acres} acres` : null,
        constructionSpecs: detail.construction_specifications,
        amenitiesSize: detail.amenities_size,
        amenitiesCount: detail.amenities_count ? String(detail.amenities_count) : null,
        parking: detail.parking_details,
        greenCertification: compliance.green_certification,
        // Only worth a row when true — "Vastu Compliant: No" is noise.
        vastuCompliant: compliance.vastu_compliant ? 'Yes' : null,
        awards: listToText(detail.awards),
      },
      approvals: {
        approvingAuthorities: listToText(detail.approving_authorities),
        bankApprovals: listToText(detail.bank_approvals),
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
