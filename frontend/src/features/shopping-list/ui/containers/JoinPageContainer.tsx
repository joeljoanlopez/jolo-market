import { Navigate, useParams } from 'react-router';

import { useJoinList } from '../../application/useMyLists';
import { JoinStatus } from '../components/JoinStatus';

/** `/join/:token`: joins the list of a secret link, then opens it (PD-2). */
export function JoinPageContainer() {
  const { token = '' } = useParams();
  const join = useJoinList(token);

  switch (join.status) {
    case 'joined':
      // Replace, so Back does not join again or land on a used link.
      return <Navigate to={`/lists/${join.listId}`} replace />;
    case 'error':
      return <JoinStatus status="error" onRetry={join.retry} />;
    case 'invalid':
    case 'joining':
      return <JoinStatus status={join.status} />;
  }
}
