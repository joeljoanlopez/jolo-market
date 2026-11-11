import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { useCreateList, useMyLists, type CreateListError } from '../../application/useMyLists';
import { CreateListForm } from '../components/CreateListForm';
import { MyListsList } from '../components/MyListsList';

/** The "My lists" section of the home page, with the create-list form. */
export function MyListsContainer() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const myLists = useMyLists();
  const createList = useCreateList();
  const [name, setName] = useState('');
  const titleId = useId();
  const createErrors: Record<CreateListError, string> = {
    invalid: t('lists.create.invalid'),
    failed: t('lists.create.failed'),
  };

  return (
    <section aria-labelledby={titleId} className="flex flex-col gap-4">
      <span id={titleId}>
        <Text as="h2" size="xl" weight="bold">
          {t('lists.mine.title')}
        </Text>
      </span>
      <CreateListForm
        name={name}
        pending={createList.pending}
        error={createList.error === null ? null : createErrors[createList.error]}
        onNameChange={setName}
        onSubmit={() => {
          createList.create(name, (listId) => {
            void navigate(`/lists/${listId}`);
          });
        }}
      />
      {myLists.status === 'loading' ? (
        <div role="status">
          <Text tone="muted">{t('lists.mine.loading')}</Text>
        </div>
      ) : null}
      {myLists.status === 'error' ? (
        <div className="flex flex-col items-start gap-3">
          <Text>{t('lists.mine.loadFailed')}</Text>
          <Button variant="secondary" onClick={myLists.retry}>
            {t('lists.mine.retry')}
          </Button>
        </div>
      ) : null}
      {myLists.status === 'success' ? <MyListsList lists={myLists.lists} /> : null}
    </section>
  );
}
