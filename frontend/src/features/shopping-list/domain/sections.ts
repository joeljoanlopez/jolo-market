import type { ListItem } from './shoppingList';

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

export interface SectionGroup<T extends ListItem = ListItem> {
  section: SectionId;
  /** In list order (PD-45). */
  items: T[];
}

export interface GroupedItems<T extends ListItem = ListItem> {
  /** Only sections with unchecked items, in the order of SECTIONS (PD-93). */
  sections: SectionGroup<T>[];
  /** Every checked item, in list order, in one block at the end. */
  checked: T[];
}

/**
 * How a list shows its items (PD-93): unchecked ones under their section, checked ones apart. It
 * takes any items, so the supermarket filter (PD-85) groups its products the same way.
 */
export function groupBySection<T extends ListItem>(items: readonly T[]): GroupedItems<T> {
  const inOrder = [...items].sort((a, b) => a.position - b.position);
  const sections = SECTIONS.map((section) => ({
    section,
    items: inOrder.filter((item) => !item.checked && item.section === section),
  })).filter((group) => group.items.length > 0);
  return { sections, checked: inOrder.filter((item) => item.checked) };
}
