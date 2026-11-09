import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import { NotFoundError, UnexpectedResponseError, ValidationError } from '@/shared/http/errors';
import { createHttpClient } from '@/shared/http/httpClient';
import { server } from '@/test/server';

import { createCatalogApi } from './catalogApi';

const baseUrl = 'http://localhost';
const api = createCatalogApi(createHttpClient({ baseUrl }));

const milkJson = {
  id: '0190f3c2-0000-7000-8000-000000000001',
  name: 'Leche entera',
  brand: 'Hacendado',
  supermarket: { id: 'mercadona', name: 'Mercadona' },
  packaging: { quantity: 1.5, unit: 'l' },
  priceCents: 125,
  unitPrice: { cents: 83, unit: 'l' },
  imageUrl: 'https://example.test/milk.jpg',
};

const bareJson = {
  ...milkJson,
  id: '0190f3c2-0000-7000-8000-000000000002',
  brand: null,
  packaging: null,
  unitPrice: null,
  imageUrl: null,
};

function searchBody(overrides: Record<string, unknown> = {}) {
  return {
    items: [milkJson, bareJson],
    page: 1,
    perPage: 20,
    total: 2,
    cheapestUnitPriceCents: { l: 83 },
    ...overrides,
  };
}

function problem(status: number, title: string) {
  return HttpResponse.json(
    { type: 'about:blank', title, status },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

describe('catalogApi.searchProducts', () => {
  it('maps a search page to domain types', async () => {
    server.use(http.get(`${baseUrl}/api/products`, () => HttpResponse.json(searchBody())));

    await expect(api.searchProducts({ q: 'leche' })).resolves.toEqual({
      items: [milkJson, bareJson],
      page: 1,
      perPage: 20,
      total: 2,
      cheapestUnitPriceCents: { l: 83 },
    });
  });

  it('sends only the text when there is no filter and no page', async () => {
    let query = '';
    server.use(
      http.get(`${baseUrl}/api/products`, ({ request }) => {
        query = new URL(request.url).search;
        return HttpResponse.json(searchBody());
      }),
    );

    await api.searchProducts({ q: 'leche entera' });

    expect(query).toBe('?q=leche+entera');
  });

  it('sends the supermarket filter and the page', async () => {
    let params: URLSearchParams | undefined;
    server.use(
      http.get(`${baseUrl}/api/products`, ({ request }) => {
        params = new URL(request.url).searchParams;
        return HttpResponse.json(searchBody({ page: 2 }));
      }),
    );

    await api.searchProducts({ q: 'leche', supermarket: 'mercadona', page: 2 });

    expect(Object.fromEntries(params ?? [])).toEqual({
      q: 'leche',
      supermarket: 'mercadona',
      page: '2',
    });
  });

  it('lets a 422 reach the caller as a ValidationError', async () => {
    server.use(http.get(`${baseUrl}/api/products`, () => problem(422, 'Unprocessable Content')));

    await expect(api.searchProducts({ q: 'l' })).rejects.toBeInstanceOf(ValidationError);
  });

  it.each([
    ['a body that is not an object', 'oops'],
    ['items that are not a list', searchBody({ items: 'nope' })],
    ['a product without a name', searchBody({ items: [{ ...milkJson, name: 42 }] })],
    [
      'an unknown unit',
      searchBody({ items: [{ ...milkJson, unitPrice: { cents: 1, unit: 'g' } }] }),
    ],
    ['a price that is not an integer', searchBody({ items: [{ ...milkJson, priceCents: 1.5 }] })],
    ['a cheapest price of an unknown unit', searchBody({ cheapestUnitPriceCents: { g: 1 } })],
    ['a missing total', { ...searchBody(), total: undefined }],
  ])('rejects %s with an UnexpectedResponseError', async (_case, body) => {
    server.use(http.get(`${baseUrl}/api/products`, () => HttpResponse.json(body)));

    await expect(api.searchProducts({ q: 'leche' })).rejects.toBeInstanceOf(
      UnexpectedResponseError,
    );
  });
});

describe('catalogApi.getProduct', () => {
  it('maps a product with its availability', async () => {
    server.use(
      http.get(`${baseUrl}/api/products/${milkJson.id}`, () =>
        HttpResponse.json({ ...milkJson, available: false }),
      ),
    );

    await expect(api.getProduct(milkJson.id)).resolves.toEqual({ ...milkJson, available: false });
  });

  it('encodes the id in the path', async () => {
    let path = '';
    server.use(
      http.get(`${baseUrl}/api/products/*`, ({ request }) => {
        path = new URL(request.url).pathname;
        return HttpResponse.json({ ...milkJson, available: true });
      }),
    );

    await api.getProduct('a/b');

    expect(path).toBe('/api/products/a%2Fb');
  });

  it('lets a 404 reach the caller as a NotFoundError', async () => {
    server.use(http.get(`${baseUrl}/api/products/unknown`, () => problem(404, 'Not Found')));

    await expect(api.getProduct('unknown')).rejects.toBeInstanceOf(NotFoundError);
  });

  it('rejects a product without availability with an UnexpectedResponseError', async () => {
    server.use(
      http.get(`${baseUrl}/api/products/${milkJson.id}`, () => HttpResponse.json(milkJson)),
    );

    await expect(api.getProduct(milkJson.id)).rejects.toBeInstanceOf(UnexpectedResponseError);
  });
});

describe('catalogApi.listSupermarkets', () => {
  it('maps the supermarkets in the order of the API', async () => {
    const supermarkets = [
      { id: 'mercadona', name: 'Mercadona' },
      { id: 'aldi', name: 'Aldi' },
    ];
    server.use(http.get(`${baseUrl}/api/supermarkets`, () => HttpResponse.json(supermarkets)));

    await expect(api.listSupermarkets()).resolves.toEqual(supermarkets);
  });

  it('rejects a supermarket without a name with an UnexpectedResponseError', async () => {
    server.use(http.get(`${baseUrl}/api/supermarkets`, () => HttpResponse.json([{ id: 'aldi' }])));

    await expect(api.listSupermarkets()).rejects.toBeInstanceOf(UnexpectedResponseError);
  });
});
