import {useAppSelector} from '../store/hooks';
import {getDeveloperById, getProjectById} from '../data/mockDevelopers';
import {getBrokerById, getLeadsForProject} from '../data/mockLeads';

function timeAgo(isoString) {
  const diffMs = Date.now() - new Date(isoString).getTime();
  const minutes = Math.floor(diffMs / 60000);
  if (minutes < 1) {
    return 'Just now';
  }
  if (minutes < 60) {
    return `${minutes}m ago`;
  }
  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${hours}h ago`;
  }
  const days = Math.floor(hours / 24);
  if (days < 7) {
    return `${days}d ago`;
  }
  return `${Math.floor(days / 7)}w ago`;
}

function buildBrokerNotifications(leadsByProjectId) {
  return Object.values(leadsByProjectId).map(lead => {
    const projectName = getProjectById(lead.projectId)?.name ?? 'a property';
    const developerName = getDeveloperById(lead.developerId)?.name ?? 'The developer';

    if (lead.status === 'accepted') {
      return {
        id: `broker-${lead.projectId}-accepted`,
        icon: 'checkmark-circle',
        tone: 'success',
        title: 'Interest accepted',
        message: `${developerName} accepted your interest in ${projectName}. Contact details are now unlocked.`,
        date: lead.markedAt,
      };
    }
    if (lead.status === 'declined') {
      return {
        id: `broker-${lead.projectId}-declined`,
        icon: 'close-circle',
        tone: 'danger',
        title: 'Interest declined',
        message: `${developerName} declined your interest in ${projectName}.`,
        date: lead.markedAt,
      };
    }
    return {
      id: `broker-${lead.projectId}-pending`,
      icon: 'time',
      tone: 'warning',
      title: 'Awaiting response',
      message: `Your interest in ${projectName} is with ${developerName}, awaiting response.`,
      date: lead.markedAt,
    };
  });
}

function buildDeveloperNotifications(projects, responses) {
  const items = [];

  projects.forEach(project => {
    getLeadsForProject(project.id).forEach(lead => {
      const brokerName = getBrokerById(lead.brokerId)?.name ?? 'A broker';
      const responseStatus = responses[project.id]?.[lead.brokerId];

      if (responseStatus === 'accepted') {
        items.push({
          id: `dev-${project.id}-${lead.brokerId}-accepted`,
          icon: 'checkmark-done-circle',
          tone: 'success',
          title: 'Match confirmed',
          message: `You accepted ${brokerName}'s interest in ${project.name}. Admin has been notified to follow up.`,
          date: lead.markedAt,
        });
      } else if (responseStatus === 'declined') {
        items.push({
          id: `dev-${project.id}-${lead.brokerId}-declined`,
          icon: 'close-circle',
          tone: 'neutral',
          title: 'Lead declined',
          message: `You declined ${brokerName}'s interest in ${project.name}.`,
          date: lead.markedAt,
        });
      } else if (lead.status === 'interested') {
        items.push({
          id: `dev-${project.id}-${lead.brokerId}-interested`,
          icon: 'star',
          tone: 'primary',
          title: 'New interested lead',
          message: `${brokerName} marked interest in ${project.name}.`,
          date: lead.markedAt,
        });
      } else {
        items.push({
          id: `dev-${project.id}-${lead.brokerId}-viewed`,
          icon: 'eye',
          tone: 'neutral',
          title: 'Property viewed',
          message: `${brokerName} viewed ${project.name}.`,
          date: lead.markedAt,
        });
      }
    });
  });

  return items;
}

/** Derives a role-aware notification feed from real lead/response state (no separate mock notification list to keep in sync). */
export function useNotifications() {
  const role = useAppSelector(state => state.auth.role);
  const leadsByProjectId = useAppSelector(state => state.leads.byProjectId);
  const developer = useAppSelector(state => state.auth.developer);
  const responses = useAppSelector(state => state.developerLeads.responses);
  const readIds = useAppSelector(state => state.notifications.readIds);

  let items = [];
  if (role === 'broker') {
    items = buildBrokerNotifications(leadsByProjectId);
  } else if (role === 'developer') {
    const projects = getDeveloperById(developer?.developerId)?.projects ?? [];
    items = buildDeveloperNotifications(projects, responses);
  }

  items.sort((a, b) => new Date(b.date) - new Date(a.date));

  return items.map(item => ({
    ...item,
    timeAgo: timeAgo(item.date),
    isUnread: !readIds[item.id],
  }));
}
