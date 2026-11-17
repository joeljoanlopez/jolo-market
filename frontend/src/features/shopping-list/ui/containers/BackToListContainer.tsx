import { useMyList } from '../../application/useMyLists';
import { BackToListLink } from '../components/BackToListLink';

/** The link back to the list of a search; nothing when it is not one of "My lists" (PD-82). */
export function BackToListContainer({ listId }: { listId: string }) {
  const list = useMyList(listId);
  return list === null ? null : <BackToListLink listId={list.id} listName={list.name} />;
}
