import {useAppSelector} from '../store/hooks';
import {getLeadsForProject} from '../data/mockLeads';

/** Merges seed leads with any developer accept/decline overrides into a single effectiveStatus. */
export function useEffectiveLeads(projectIds) {
  const responses = useAppSelector(state => state.developerLeads.responses);

  return projectIds.flatMap(projectId =>
    getLeadsForProject(projectId).map(lead => ({
      ...lead,
      projectId,
      effectiveStatus: responses[projectId]?.[lead.brokerId] ?? lead.status,
    })),
  );
}
