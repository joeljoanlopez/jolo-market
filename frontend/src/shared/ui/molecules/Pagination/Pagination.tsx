import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

interface PaginationProps {
  /** 1-based. */
  page: number;
  pageCount: number;
  onPageChange: (page: number) => void;
  labels: { label: string; previous: string; next: string; status: string };
}

export function Pagination({ page, pageCount, onPageChange, labels }: PaginationProps) {
  return (
    <nav aria-label={labels.label} className="flex items-center justify-between gap-2">
      <Button
        variant="secondary"
        size="sm"
        disabled={page <= 1}
        onClick={() => {
          onPageChange(page - 1);
        }}
      >
        {labels.previous}
      </Button>
      <Text as="span" size="sm" tone="muted">
        {labels.status}
      </Text>
      <Button
        variant="secondary"
        size="sm"
        disabled={page >= pageCount}
        onClick={() => {
          onPageChange(page + 1);
        }}
      >
        {labels.next}
      </Button>
    </nav>
  );
}
