/**
 * Maps the API's property resource onto the view shape the property components
 * already render (PropertyCard / PropertyHero / PropertyDetailBody).
 *
 * Kept at the store boundary rather than inside each component so there is exactly
 * one place that knows both shapes. If the API changes, only this file moves.
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

export function normalizeProperty(api) {
  if (!api) {
    return null;
  }

  const media = api.media ?? [];
  const images = media.filter(m => m.kind === 'image').map(m => m.url).filter(Boolean);
  const units = api.unit_types ?? [];
  const primaryUnit = units[0] ?? {};
  const detail = api.detail ?? {};
  const location = api.location ?? {};
  const price = api.price ?? {};

  return {
    id: api.id,
    name: api.name,
    developerId: api.developer?.id,
    developerName: api.developer?.company_name,

    type: api.project_type,
    listingType: api.project_status,
    price: price.min ?? 0,
    priceUnit: price.max && price.max !== price.min ? `– ${price.max.toLocaleString()}` : '',
    currency: price.currency ?? 'AED',
    location: [location.locality, location.city].filter(Boolean).join(', '),
    postedDaysAgo: daysSince(api.created_at),

    coverImage: api.cover_image_url ?? images[0] ?? null,
    images,
    photoCount: images.length,

    commissionPercent: detail.cp_commission_percent ?? 0,
    bedrooms: bedroomsFromLabel(primaryUnit.label) ?? units.length,
    bathrooms: bedroomsFromLabel(primaryUnit.label) ?? units.length,
    areaSqft: primaryUnit.carpet_area_sqft ?? 0,
    amenities: detail.amenities ?? [],
    description: api.description ?? '',

    viewsCount: api.views_count ?? 0,
    interestsCount: api.interests_count ?? 0,
    // Kept in the API's own shape — screens read `my_lead.status` directly.
    my_lead: api.my_lead ?? null,

    details: {
      overview: {
        reraNumber: api.rera_number,
        projectType: api.project_type,
        projectStatus: api.project_status,
        tagline: api.tagline,
        possessionDate: api.possession_date,
        constructionProgress: api.construction_progress ? `${api.construction_progress}%` : null,
      },
      unit: {
        unitTypes: units.map(u => u.label).join(', '),
        carpetArea: primaryUnit.carpet_area_sqft
          ? `${primaryUnit.carpet_area_sqft.toLocaleString()} sqft`
          : null,
        builtUpArea: primaryUnit.built_up_area_sqft
          ? `${primaryUnit.built_up_area_sqft.toLocaleString()} sqft`
          : null,
        superBuiltUpArea: primaryUnit.super_built_up_area_sqft
          ? `${primaryUnit.super_built_up_area_sqft.toLocaleString()} sqft`
          : null,
        pricePerSqft: price.per_sqft ? `${price.currency} ${price.per_sqft.toLocaleString()}` : null,
      },
      location: {
        address: location.locality,
        city: location.city,
        zone: location.zone,
        pincode: location.pincode,
        connectivity: detail.connectivity_highlights ?? [],
        nearby: detail.nearby_infrastructure ?? [],
      },
      specs: {
        construction: detail.construction_specifications,
        parking: detail.parking_details,
        amenities: detail.amenities ?? [],
      },
      approvals: {
        authorities: detail.approving_authorities ?? [],
        banks: detail.bank_approvals ?? [],
      },
      payment: {
        plans: detail.payment_plan_options ?? [],
        bookingAmount: detail.booking_amount,
        maintenance: detail.maintenance_charges,
      },
      sales: {
        office: detail.sales_office_address,
        timings: detail.site_visit_timings,
        contactName: detail.sales_contact_name,
        contactNumber: detail.sales_contact_number,
      },
    },
  };
}

export function normalizeProperties(list = []) {
  return list.map(normalizeProperty);
}
