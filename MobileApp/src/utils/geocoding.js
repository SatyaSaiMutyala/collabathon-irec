/**
 * Free, keyless geocoding via OpenStreetMap's Nominatim — used both for "what city is
 * my GPS point in" (useLocation's detectLocation) and MapPickerScreen's search box and
 * final-pin lookup. No API key, but rate-limited and meant for light, non-commercial-
 * scale traffic per Nominatim's usage policy — fine for this app's volume, not a
 * substitute for a paid geocoder if usage ever grows well past that.
 */
const USER_AGENT = 'CollabathonApp/1.0';

/** A GPS point -> the city it falls in, for "Use current location"/a picked map pin. */
export async function reverseGeocode(lat, lon) {
  const response = await fetch(
    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=10&addressdetails=1`,
    {headers: {'User-Agent': USER_AGENT}},
  );
  const json = await response.json();
  const address = json?.address ?? {};
  const city = address.city || address.town || address.state || 'Unknown';
  return {city, label: city};
}

/**
 * Free-text search (a city, area or landmark name) -> up to 5 candidate places, each
 * with coordinates to jump the map to and the city Nominatim resolved for it — the map
 * screen's search box.
 */
export async function searchPlaces(query) {
  const trimmed = query.trim();
  if (!trimmed) {
    return [];
  }

  const response = await fetch(
    `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=5&q=${encodeURIComponent(trimmed)}`,
    {headers: {'User-Agent': USER_AGENT}},
  );
  const json = await response.json();

  return (Array.isArray(json) ? json : []).map(result => {
    const address = result.address ?? {};
    return {
      id: `${result.place_id}`,
      name: result.display_name,
      latitude: Number(result.lat),
      longitude: Number(result.lon),
      city: address.city || address.town || address.state || result.display_name,
    };
  });
}
