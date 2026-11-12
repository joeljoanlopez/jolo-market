/** The product page (PD-48). Other features link to it through this function, never by repeating the route. */
export function productPath(productId: string): string {
  return `/product/${encodeURIComponent(productId)}`;
}
