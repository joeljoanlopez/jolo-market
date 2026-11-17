import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

/** Back to the list a search was opened from (PD-82). */
export function BackToListLink({ listId, listName }: { listId: string; listName: string }) {
  const { t } = useTranslation();

  return (
    <Link to={`/lists/${listId}`} className="self-start text-primary underline underline-offset-2">
      {t('lists.backToList', { name: listName })}
    </Link>
  );
}
