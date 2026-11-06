import { setupServer } from 'msw/node';

// Handlers are registered per test with server.use(); anything unhandled fails the test.
export const server = setupServer();
