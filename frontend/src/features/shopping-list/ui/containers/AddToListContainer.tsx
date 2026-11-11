import { useState } from 'react';

import { useAddProductToList } from '../../application/useListItems';
import { useMyLists } from '../../application/useMyLists';
import { AddToListButton, AddToListPicker } from '../components/AddToListPicker';

interface AddToListContainerProps {
  productId: string;
  productName: string;
}

/** Adds a product from the search results to one of "My lists". */
export function AddToListContainer({ productId, productName }: AddToListContainerProps) {
  const [open, setOpen] = useState(false);
  const addition = useAddProductToList(productId);

  if (!open) {
    return (
      <AddToListButton
        productName={productName}
        onOpen={() => {
          setOpen(true);
        }}
      />
    );
  }

  return (
    <OpenPicker
      productName={productName}
      addition={addition}
      onClose={() => {
        setOpen(false);
      }}
    />
  );
}

/** Loads "My lists" only once the user opens the picker. */
function OpenPicker({
  productName,
  addition,
  onClose,
}: {
  productName: string;
  addition: ReturnType<typeof useAddProductToList>;
  onClose: () => void;
}) {
  const myLists = useMyLists();
  const lists = myLists.status === 'success' ? myLists.lists : [];
  const [chosen, setChosen] = useState<string | null>(null);
  const selectedListId = chosen ?? lists[0]?.id ?? '';
  const addedTo = lists.find((list) => list.id === addition.addedTo)?.name ?? null;

  return (
    <AddToListPicker
      productName={productName}
      lists={
        myLists.status === 'success'
          ? myLists.lists
          : myLists.status === 'loading'
            ? 'loading'
            : 'failed'
      }
      selectedListId={selectedListId}
      pending={addition.pending}
      addedTo={addedTo}
      failed={addition.failed}
      onSelect={setChosen}
      onSubmit={() => {
        if (selectedListId !== '') {
          addition.add(selectedListId);
        }
      }}
      onCancel={onClose}
    />
  );
}
