import { render } from '@testing-library/react';
import { createMemoryRouter, type RouteObject } from 'react-router';

import { App } from '@/app/App';
import { routes as appRoutes } from '@/app/routes';

import { FakeAuthGateway } from './FakeAuthGateway';
import { FakeProductCatalog } from './FakeProductCatalog';
import { FakeShoppingLists } from './FakeShoppingLists';
import { FakeUserAdministration } from './FakeUserAdministration';
import { testI18n } from './i18n';
import { createTestQueryClient } from './render';

interface RenderAppOptions {
  auth?: FakeAuthGateway;
  catalog?: FakeProductCatalog;
  administration?: FakeUserAdministration;
  lists?: FakeShoppingLists;
  routes?: RouteObject[];
}

/** Renders the whole app (providers + router) at `path`. Returns the router to read its location. */
export function renderApp(
  path: string,
  {
    auth = new FakeAuthGateway(),
    catalog = new FakeProductCatalog(),
    administration = new FakeUserAdministration(),
    lists = new FakeShoppingLists(),
    routes = appRoutes,
  }: RenderAppOptions = {},
) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(
    <App
      router={router}
      queryClient={createTestQueryClient()}
      i18n={testI18n}
      productCatalog={catalog}
      authGateway={auth}
      userAdministration={administration}
      shoppingLists={lists}
    />,
  );
  return router;
}

/** Where the router is now, as `pathname + search`. */
export function locationOf(router: ReturnType<typeof createMemoryRouter>): string {
  return router.state.location.pathname + router.state.location.search;
}
