import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Text } from '@/shared/ui/atoms/Text/Text';

interface ShareLinkButtonProps {
  /** The full secret link, e.g. https://lista.example/join/… */
  link: string;
  copied: boolean;
  onCopy: () => void;
}

/** The owner's secret link to the list, shown and copied in full (PD-2). */
export function ShareLinkButton({ link, copied, onCopy }: ShareLinkButtonProps) {
  const { t } = useTranslation();
  const linkId = useId();
  const hintId = useId();

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={linkId}>{t('lists.share.label')}</Label>
      <div className="flex gap-2">
        <Input
          id={linkId}
          readOnly
          value={link}
          aria-describedby={hintId}
          onFocus={(event) => {
            event.target.select();
          }}
        />
        <Button variant="secondary" onClick={onCopy}>
          {t('lists.share.copy')}
        </Button>
      </div>
      <span id={hintId}>
        <Text as="span" size="sm" tone="muted">
          {t('lists.share.hint')}
        </Text>
      </span>
      <div role="status">
        {copied ? (
          <Text size="sm" weight="medium">
            {t('lists.share.copied')}
          </Text>
        ) : null}
      </div>
    </div>
  );
}
