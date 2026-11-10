/**
 * 2026-11-13T09:05:00Z → "13/11/2026, 10:05" in Madrid. The time zone defaults to the browser's,
 * so an admin sees when a link expires in their own time.
 */
export function formatDateTime(date: Date, timeZone?: string): string {
  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    ...(timeZone === undefined ? {} : { timeZone }),
  }).format(date);
}
