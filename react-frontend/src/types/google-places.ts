/**
 * Google Places Autocomplete types for Project NEXUS.
 *
 * Used by PlaceAutocompleteInput component and all location forms.
 */

/** Structured result returned when user selects a place suggestion. */
export interface PlaceResult {
  /** Google Place ID — stable identifier for this place. */
  placeId: string;
  /** Human-readable formatted address (e.g., "123 Main St, Dublin, Ireland"). */
  formattedAddress: string;
  /** Latitude coordinate. */
  lat: number;
  /** Longitude coordinate. */
  lng: number;
  /** Parsed address components (city, country, etc.). */
  addressComponents?: AddressComponents;
  /** Place or business name (e.g., "Starbucks", "Dublin Castle"). */
  name?: string;
  /** Google Place types (e.g., ['locality', 'political']). */
  types?: string[];
}

/** Parsed address components from Google Places response. */
export interface AddressComponents {
  city?: string;
  county?: string;
  state?: string;
  country?: string;
  countryCode?: string;
  postalCode?: string;
}

/** Props for PlaceAutocompleteInput component. */
export interface PlaceAutocompleteInputProps {
  /** Current text value of the input. */
  value: string;
  /** Called when user types (updates text only, no place selected yet). */
  onChange?: (value: string) => void;
  /** Called when user selects a place from suggestions. */
  onPlaceSelect?: (place: PlaceResult) => void;
  /** Called when user clears the input. */
  onClear?: () => void;
  /** Input label. */
  label?: string;
  /** Input placeholder text. */
  placeholder?: string;
  /** Whether the field is required. */
  isRequired?: boolean;
  /** Error message to display. */
  errorMessage?: string;
  /** Whether the input is invalid (shows error styling). */
  isInvalid?: boolean;
  /** HeroUI classNames pass-through for input styling. */
  classNames?: Record<string, string>;
  /** Additional class for the wrapper div. */
  className?: string;
  /** Whether to show the MapPin icon. Default: true. */
  showIcon?: boolean;
}
