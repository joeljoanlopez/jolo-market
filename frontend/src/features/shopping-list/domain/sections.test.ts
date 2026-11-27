import { describe, expect, it } from 'vitest';

import { groupBySection, isSectionId, SECTIONS, type SectionId } from './sections';
import type { ListItem } from './shoppingList';

function anItem(id: string, position: number, section: SectionId, checked = false): ListItem {
  return {
    id,
    position,
    quantity: 1,
    checked,
    type: 'text',
    text: id,
    section,
    sectionOverride: null,
  };
}

const ids = (items: readonly ListItem[]) => items.map((item) => item.id);

describe('sections', () => {
  it('knows the thirteen section ids, with other last', () => {
    expect(SECTIONS).toHaveLength(13);
    expect(SECTIONS.at(-1)).toBe('other');
    expect(isSectionId('bakery')).toBe(true);
    expect(isSectionId('toys')).toBe(false);
  });

  it('groups unchecked items by section in the store order, leaving out empty sections', () => {
    const grouped = groupBySection([
      anItem('napkins', 0, 'other'),
      anItem('milk', 1, 'dairy-and-eggs'),
      anItem('apples', 2, 'fruit-and-vegetables'),
      anItem('bread', 3, 'bakery'),
    ]);

    expect(grouped.sections.map((group) => group.section)).toEqual([
      'fruit-and-vegetables',
      'bakery',
      'dairy-and-eggs',
      'other',
    ]);
  });

  it('keeps list order inside each section', () => {
    const grouped = groupBySection([
      anItem('yogurt', 1, 'dairy-and-eggs'),
      anItem('milk', 0, 'dairy-and-eggs'),
      anItem('eggs', 2, 'dairy-and-eggs'),
    ]);

    expect(grouped.sections.map((group) => ids(group.items))).toEqual([['milk', 'yogurt', 'eggs']]);
  });

  it('returns the checked items apart, in list order and not grouped', () => {
    const grouped = groupBySection([
      anItem('bread', 2, 'bakery', true),
      anItem('milk', 0, 'dairy-and-eggs'),
      anItem('napkins', 1, 'other', true),
    ]);

    expect(grouped.sections.map((group) => group.section)).toEqual(['dairy-and-eggs']);
    expect(ids(grouped.checked)).toEqual(['napkins', 'bread']);
  });

  it('has no sections when every item is checked, and nothing for an empty list', () => {
    expect(groupBySection([anItem('bread', 0, 'bakery', true)]).sections).toEqual([]);
    expect(groupBySection([])).toEqual({ sections: [], checked: [] });
  });
});
