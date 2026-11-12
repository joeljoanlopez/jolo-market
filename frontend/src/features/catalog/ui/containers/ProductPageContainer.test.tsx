import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { renderApp } from '@/test/renderApp';
import { testProductDetail } from '@/test/testProductDetail';

import { formatPrice } from '../../domain/format';
import { periodStart, type PricePoint } from '../../domain/priceHistory';
import { asShown } from '../components/testProduct';

function point(day: string, priceCents: number): PricePoint {
  return { capturedAt: new Date(`${day}T05:00:00Z`), priceCents, unitPrice: null };
}

function catalogWith(product = testProductDetail(), points: PricePoint[] = []): FakeProductCatalog {
  const catalog = new FakeProductCatalog();
  catalog.products.set(product.id, product);
  catalog.respondHistory = (productId) => Promise.resolve({ productId, points });
  return catalog;
}

function renderPage(catalog: FakeProductCatalog, path = '/product/milk') {
  return renderApp(path, { auth: FakeAuthGateway.loggedInAs(maria), catalog });
}

describe('ProductPageContainer (/product/:productId)', () => {
  it('shows the product, its price chart and the minimum, maximum and current price', async () => {
    renderPage(
      catalogWith(testProductDetail({ priceCents: 130 }), [
        point('2026-11-01', 120),
        point('2026-11-02', 140),
        point('2026-11-03', 130),
      ]),
    );

    expect(await screen.findByRole('heading', { level: 1, name: 'Leche entera' })).toBeVisible();
    expect(await screen.findByRole('img', { name: /^Precio del 01\/11\/2026/u })).toBeVisible();
    expect(screen.getByText(asShown(formatPrice(120)))).toBeVisible();
    expect(screen.getByText(asShown(formatPrice(140)))).toBeVisible();
    expect(screen.getAllByText(asShown(formatPrice(130))).length).toBeGreaterThan(0);
  });

  it('asks for the last 6 months by default', async () => {
    const catalog = catalogWith();
    renderPage(catalog);

    await waitFor(() => {
      expect(catalog.historyRequests).toEqual([
        { productId: 'milk', range: { from: periodStart('6m', new Date()) } },
      ]);
    });
    expect(
      await screen.findByRole('button', { name: es.catalog.history.period['6m'] }),
    ).toHaveAttribute('aria-pressed', 'true');
  });

  it('asks for another period when the user chooses it', async () => {
    const catalog = catalogWith();
    renderPage(catalog);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: es.catalog.history.period.all }));

    await waitFor(() => {
      expect(catalog.historyRequests.at(-1)).toEqual({ productId: 'milk', range: {} });
    });
  });

  it('says when the period has no prices', async () => {
    renderPage(catalogWith());

    expect(await screen.findByText(es.catalog.history.empty)).toBeVisible();
  });

  it('offers to add an available product to a list', async () => {
    renderPage(catalogWith());

    expect(
      await screen.findByRole('button', { name: 'Añadir Leche entera a una lista' }),
    ).toBeVisible();
  });

  it('flags an unavailable product and does not offer to add it to a list', async () => {
    renderPage(catalogWith(testProductDetail({ available: false })));

    expect(await screen.findByText(es.catalog.detail.unavailable)).toBeVisible();
    expect(screen.queryByRole('button', { name: /a una lista/u })).not.toBeInTheDocument();
  });

  it('shows that an unknown product was not found', async () => {
    renderPage(new FakeProductCatalog(), '/product/unknown');

    expect(
      await screen.findByRole('heading', { name: es.catalog.detail.notFound.title }),
    ).toBeVisible();
  });

  it('shows an error with a retry when the product cannot be loaded', async () => {
    const catalog = catalogWith();
    const milk = testProductDetail();
    let calls = 0;
    catalog.getProduct = () => {
      calls += 1;
      return calls <= 4 ? Promise.reject(new Error('offline')) : Promise.resolve(milk);
    };
    renderPage(catalog);
    const user = userEvent.setup();

    expect(await screen.findByRole('alert')).toHaveTextContent(es.catalog.detail.error);
    await user.click(screen.getByRole('button', { name: es.catalog.detail.retry }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Leche entera' })).toBeVisible();
  });
});
