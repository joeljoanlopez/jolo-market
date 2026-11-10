import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Input } from '@/shared/ui/atoms/Input/Input';
import { Label } from '@/shared/ui/atoms/Label/Label';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatDateTime } from '../../domain/formatDateTime';

interface ActivationLinkPanelProps {
  username: string;
  /** The full link to send, e.g. https://lista.example/activate/… */
  link: string;
  expiresAt: Date;
  copied: boolean;
  onCopy: () => void;
  onDone: () => void;
}

/** Shows a new activation link the only time it is available (PD-34). */
export function ActivationLinkPanel({
  username,
  link,
  expiresAt,
  copied,
  onCopy,
  onDone,
}: ActivationLinkPanelProps) {
  const { t } = useTranslation();
  const titleId = useId();
  const linkId = useId();

  return (
    <section
      aria-labelledby={titleId}
      className="flex flex-col gap-3 rounded-md border border-primary bg-surface p-4"
    >
      <span id={titleId}>
        <Text as="h2" size="xl" weight="bold">
          {t('admin.users.link.title', { username })}
        </Text>
      </span>
      <Text>{t('admin.users.link.onlyOnce', { username })}</Text>
      <div className="flex flex-col gap-1">
        <Label htmlFor={linkId}>{t('admin.users.link.label')}</Label>
        <Input
          id={linkId}
          readOnly
          value={link}
          onFocus={(event) => {
            event.target.select();
          }}
        />
      </div>
      <Text size="sm" tone="muted">
        {t('admin.users.link.validUntil', { date: formatDateTime(expiresAt) })}
      </Text>
      <div role="status">
        {copied ? (
          <Text size="sm" weight="medium">
            {t('admin.users.link.copied')}
          </Text>
        ) : null}
      </div>
      <div className="flex gap-2">
        <Button onClick={onCopy}>{t('admin.users.link.copy')}</Button>
        <Button variant="secondary" onClick={onDone}>
          {t('admin.users.link.done')}
        </Button>
      </div>
    </section>
  );
}
