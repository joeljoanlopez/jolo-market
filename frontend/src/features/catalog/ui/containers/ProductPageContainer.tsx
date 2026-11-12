import { type ReactNode, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams } from 'react-router';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { usePriceHistory } from '../../application/usePriceHistory';
import { useProduct } from '../../application/useProduct';
import { DEFAULT_PRICE_HISTORY_PERIOD, type PriceHistoryPeriod } from '../../domain/priceHistory';
import type { Product } from '../../domain/product';
import { PriceHistoryPanel } from '../components/PriceHistoryPanel';
import { ProductDetailView } from '../components/ProductDetailView';
import { ProductNotFound } from '../components/ProductNotFound';

interface ProductPageContainerProps {
  /** Actions from other features (e.g. "Add to list"), offered only for an available product (PD-48). */
  renderProductActions?: (product: Product) => ReactNode;
}

/** `/product/:productId`: the product and its price history (PD-48). */
export function ProductPageContainer({ renderProductActions }: ProductPageContainerProps = {}) {
  const { t } = useTranslation();
  const { productId = '' } = useParams();
  const [period, setPeriod] = useState<PriceHistoryPeriod>(DEFAULT_PRICE_HISTORY_PERIOD);
  const product = useProduct(productId);
  const history = usePriceHistory(productId, period);

  switch (product.status) {
    case 'loading':
      return <Text tone="muted">{t('catalog.detail.loading')}</Text>;
    case 'notFound':
      return <ProductNotFound />;
    case 'error':
      return (
        <div className="flex flex-col items-start gap-2">
          <Alert>{t('catalog.detail.error')}</Alert>
          <Button variant="secondary" size="sm" onClick={product.retry}>
            {t('catalog.detail.retry')}
          </Button>
        </div>
      );
    case 'success':
      return (
        <ProductDetailView
          product={product.product}
          actions={product.product.available ? renderProductActions?.(product.product) : undefined}
        >
          <PriceHistoryPanel
            state={history}
            period={period}
            onPeriodChange={setPeriod}
            currentPriceCents={product.product.priceCents}
          />
        </ProductDetailView>
      );
  }
}
