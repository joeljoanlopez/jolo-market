import { describe, expect, it } from 'vitest';

import { isAdmin } from './currentUser';

describe('isAdmin', () => {
  it('is true for an ADMIN and false for a USER', () => {
    expect(isAdmin({ id: '1', username: 'admin', role: 'ADMIN' })).toBe(true);
    expect(isAdmin({ id: '2', username: 'maria', role: 'USER' })).toBe(false);
  });
});
