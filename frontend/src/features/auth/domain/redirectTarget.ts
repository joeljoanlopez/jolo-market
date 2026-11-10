/**
 * Where to go after login. `next` comes from the URL, so anyone can craft it: only a path of
 * this app is accepted, never another site (open redirect) and never the login page again.
 */
export function safeRedirectTarget(next: string | null): string {
  if (next === null || !next.startsWith('/') || next.startsWith('//') || next.includes('\\')) {
    return '/';
  }
  return next === '/login' || next.startsWith('/login?') ? '/' : next;
}
