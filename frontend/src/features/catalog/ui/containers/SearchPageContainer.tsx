import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  type NavigateOptions,
  useLocation,
  useNavigationType,
  useSearchParams,
} from 'react-router';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';
import { Pagination } from '@/shared/ui/molecules/Pagination/Pagination';
import { SearchBox } from '@/shared/ui/molecules/SearchBox/SearchBox';

import { useProductSearch } from '../../application/useProductSearch';
import { useSupermarkets } from '../../application/useSupermarkets';
import { ProductResultList } from '../components/ProductResultList';
import { SupermarketFilter } from '../components/SupermarketFilter';

interface SearchUrl {
  q: string;
  supermarket: string | undefined;
  /** 1-based; the first page is left out of the URL. */
  page: number | undefined;
}

/** `/search?q=&supermarket=&page=`: the URL is the source of truth of the search. */
export function SearchPageContainer() {
  const { t } = useTranslation();
  const [searchParams, setSearchParams] = useSearchParams();
  const url = readSearchUrl(searchParams);
  const text = useTypedText(url.q);
  const search = useProductSearch({
    text: text.value,
    ...(url.supermarket === undefined ? {} : { supermarket: url.supermarket }),
    ...(url.page === undefined ? {} : { page: url.page }),
  });
  const supermarkets = useSupermarkets();
  const pageCount =
    search.status === 'success' ? Math.ceil(search.data.total / search.data.perPage) : 0;

  function go(next: SearchUrl, options?: NavigateOptions) {
    setSearchParams(writeSearchUrl(next), options);
  }

  return (
    <section className="flex flex-col gap-4">
      <Text as="h1" size="2xl" weight="bold">
        {t('catalog.search.title')}
      </Text>
      <SearchBox
        label={t('catalog.search.label')}
        value={text.value}
        onChange={(q) => {
          text.set(q);
          // Typing replaces the history entry, so Back does not walk through every letter.
          go({ q, supermarket: url.supermarket, page: undefined }, { replace: true });
        }}
      />
      <SupermarketFilter
        supermarkets={supermarkets}
        value={url.supermarket}
        onChange={(supermarket) => {
          go({ q: text.value, supermarket, page: undefined });
        }}
      />
      {search.status === 'idle' && <Text tone="muted">{t('catalog.search.idle')}</Text>}
      {search.status === 'loading' && (
        <div role="status">
          <Text tone="muted">{t('catalog.search.loading')}</Text>
        </div>
      )}
      {search.status === 'error' && (
        <div role="alert" className="flex flex-col items-start gap-2">
          <Text>{t('catalog.search.error')}</Text>
          <Button variant="secondary" onClick={search.retry}>
            {t('catalog.search.retry')}
          </Button>
        </div>
      )}
      {search.status === 'success' && search.data.total === 0 && (
        <Text>{t('catalog.search.empty', { query: text.value.trim() })}</Text>
      )}
      {search.status === 'success' && search.data.total > 0 && (
        <div aria-busy={search.isFetching} className="flex flex-col gap-4">
          <Text tone="muted">{t('catalog.search.total', { count: search.data.total })}</Text>
          <ProductResultList
            products={search.data.items}
            cheapestUnitPriceCents={search.data.cheapestUnitPriceCents}
          />
          <Pagination
            page={search.data.page}
            pageCount={pageCount}
            onPageChange={(page) => {
              go({ q: text.value, supermarket: url.supermarket, page });
            }}
            labels={{
              label: t('catalog.search.pagination'),
              previous: t('catalog.search.previous'),
              next: t('catalog.search.next'),
              status: t('catalog.search.pageStatus', { page: search.data.page, pageCount }),
            }}
          />
        </div>
      )}
    </section>
  );
}

/**
 * The text of the search box. It is local state, because the URL updates a moment after
 * each key press and a controlled input would lose the caret. It follows the URL on any
 * navigation that is not our own typing (Back, Forward, links).
 */
function useTypedText(urlText: string) {
  const location = useLocation();
  // react-router exports NavigationType as a type only, so compare its string value.
  const navigationType: string = useNavigationType();
  const [value, set] = useState(urlText);
  const [seenLocationKey, setSeenLocationKey] = useState(location.key);

  if (location.key !== seenLocationKey) {
    setSeenLocationKey(location.key);
    if (navigationType !== 'REPLACE') {
      set(urlText);
    }
  }

  return { value, set };
}

function readSearchUrl(params: URLSearchParams): SearchUrl {
  const supermarket = params.get('supermarket');
  const page = Number(params.get('page'));
  return {
    q: params.get('q') ?? '',
    supermarket: supermarket === null || supermarket === '' ? undefined : supermarket,
    page: Number.isInteger(page) && page > 1 ? page : undefined,
  };
}

function writeSearchUrl({ q, supermarket, page }: SearchUrl): URLSearchParams {
  const params = new URLSearchParams();
  if (q !== '') {
    params.set('q', q);
  }
  if (supermarket !== undefined) {
    params.set('supermarket', supermarket);
  }
  if (page !== undefined && page > 1) {
    params.set('page', String(page));
  }
  return params;
}
