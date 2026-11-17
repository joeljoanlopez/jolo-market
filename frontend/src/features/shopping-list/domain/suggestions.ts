/** Docs: PD-80 (what the add-item field suggests) and PD-81 (how a suggestion is picked). */

export const MAX_PRODUCT_SUGGESTIONS = 10;

/** Generic over the product, so the list domain does not depend on the catalog. */
export type SuggestionOption<P> = { kind: 'text' } | { kind: 'product'; product: P };

/** "Add as text" first, then the first products of the search, in the order the API ranks them. */
export function suggestionOptions<P>(products: readonly P[]): SuggestionOption<P>[] {
  return [
    { kind: 'text' },
    ...products
      .slice(0, MAX_PRODUCT_SUGGESTIONS)
      .map((product) => ({ kind: 'product' as const, product })),
  ];
}

/** Id of an option, for the combobox's `aria-activedescendant`. */
export function suggestionOptionId(listboxId: string, index: number): string {
  return `${listboxId}-option-${String(index)}`;
}

/** Index of the highlighted option; nothing is highlighted until the user moves with the keys. */
export type Highlight = number | null;

export const NO_HIGHLIGHT: Highlight = null;

export type HighlightEvent =
  | { type: 'next'; optionCount: number }
  | { type: 'previous'; optionCount: number }
  | { type: 'textChanged' };

export function highlightAfter(current: Highlight, event: HighlightEvent): Highlight {
  if (event.type === 'textChanged' || event.optionCount === 0) {
    return NO_HIGHLIGHT;
  }
  const count = event.optionCount;
  const inRange = current !== null && current < count ? current : null;
  if (event.type === 'next') {
    return inRange === null ? 0 : (inRange + 1) % count;
  }
  return inRange === null ? count - 1 : (inRange - 1 + count) % count;
}

/** "See every result": the search page, which remembers the list to add to (PD-82). */
export function searchResultsPath(text: string, listId: string): string {
  return `/search?${new URLSearchParams({ q: text, list: listId }).toString()}`;
}
