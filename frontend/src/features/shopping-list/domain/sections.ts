/**
 * The store sections of a list, in the order a list shows them, "other" always last (PD-90).
 * Their Spanish names live in es.json under `lists.sections` (PD-8).
 */
export const SECTIONS = [
  'fruit-and-vegetables',
  'bakery',
  'meat-and-fish',
  'deli-and-cheese',
  'dairy-and-eggs',
  'pantry',
  'frozen',
  'drinks',
  'household',
  'personal-care',
  'baby',
  'pets',
  'other',
] as const;

export type SectionId = (typeof SECTIONS)[number];

export function isSectionId(value: unknown): value is SectionId {
  return typeof value === 'string' && (SECTIONS as readonly string[]).includes(value);
}
