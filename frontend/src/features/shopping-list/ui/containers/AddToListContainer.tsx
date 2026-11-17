import { useState } from 'react';

import { useAddProductToList } from '../../application/useListItems';
import { useMyList, useMyLists } from '../../application/useMyLists';
import {
  AddToListButton,
  AddToListPicker,
  AddToTargetListButton,
} from '../components/AddToListPicker';

interface AddToListContainerProps {
  productId: string;
  productName: string;
  /** The list the search came from (PD-82); ignored unless it is one of "My lists". */
  targetListId?: string | undefined;
}

/** Adds a product from the search results to one of "My lists". */
export function AddToListContainer({
  productId,
  productName,
  targetListId,
}: AddToListContainerProps) {
  return targetListId === undefined ? (
    <PickAList productId={productId} productName={productName} />
  ) : (
    <AddToTargetList productId={productId} productName={productName} listId={targetListId} />
  );
}

/** Adds straight to the list of the search, or offers the picker when it is not a list of mine. */
function AddToTargetList({
  productId,
  productName,
  listId,
}: {
  productId: string;
  productName: string;
  listId: string;
}) {
  const target = useMyList(listId);
  const addition = useAddProductToList(productId);

  if (target === null) {
    return <PickAList productId={productId} productName={productName} />;
  }
  return (
    <AddToTargetListButton
      productName={productName}
      listName={target.name}
      pending={addition.pending}
      added={addition.addedTo === target.id}
      failed={addition.failed}
      onAdd={() => {
        addition.add(target.id);
      }}
    />
  );
}

function PickAList({ productId, productName }: { productId: string; productName: string }) {
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
