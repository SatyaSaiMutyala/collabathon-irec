import {useAppSelector} from '../store/hooks';

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

function leadDate(lead) {
  return lead.responded_at || lead.interested_at || lead.viewed_at || lead.created_at;
}

/** Per-role copy for each lead status. A broker isn't notified about their own views. */
const STATUS_CONFIG = {
  broker: {
    accepted: {
      icon: 'checkmark-circle',
      tone: 'success',
      title: 'Interest accepted',
      message: lead =>
        `${lead.developer?.company_name ?? 'The developer'} accepted your interest in ${
          lead.property?.name ?? 'a property'
        }. Contact details are now unlocked.`,
    },
    declined: {
      icon: 'close-circle',
      tone: 'danger',
      title: 'Interest declined',
      message: lead =>
        `${lead.developer?.company_name ?? 'The developer'} declined your interest in ${
          lead.property?.name ?? 'a property'
        }.`,
    },
    interested: {
      icon: 'time',
      tone: 'warning',
      title: 'Awaiting response',
      message: lead =>
        `Your interest in ${lead.property?.name ?? 'a property'} is with ${
          lead.developer?.company_name ?? 'the developer'
        }, awaiting response.`,
    },
  },
  developer: {
    accepted: {
      icon: 'checkmark-done-circle',
      tone: 'success',
      title: 'Introduction Confirmed',
      message: lead =>
        `You accepted ${lead.broker?.name ?? 'a channel partner'}'s interest in ${
          lead.property?.name ?? 'a property'
        }. Our team has been notified to follow up.`,
    },
    declined: {
      icon: 'close-circle',
      tone: 'neutral',
      title: 'Interest Declined',
      message: lead =>
        `You declined ${lead.broker?.name ?? 'a channel partner'}'s interest in ${
          lead.property?.name ?? 'a property'
        }.`,
    },
    interested: {
      icon: 'star',
      tone: 'primary',
      title: 'New Interest',
      message: lead =>
        `${lead.broker?.name ?? 'A channel partner'} expressed interest in ${lead.property?.name ?? 'a listing'}.`,
    },
    viewed: {
      icon: 'eye',
      tone: 'neutral',
      title: 'Listing Viewed',
      message: lead => `${lead.broker?.name ?? 'A channel partner'} viewed ${lead.property?.name ?? 'a property'}.`,
    },
  },
};

/**
 * Derives a role-aware notification feed from already-fetched lead state (no separate
 * mock notification list to keep in sync — /api/v1/leads already returns each role's
 * own view of the same leads, see leadsSlice.js).
 */
export function useNotifications() {
  const role = useAppSelector(state => state.auth.role);
  const leads = useAppSelector(state => state.leads.notifications.items);
  const readIds = useAppSelector(state => state.notifications.readIds);

  const config = STATUS_CONFIG[role] ?? {};
  const items = leads
    .map(lead => {
      const entry = config[lead.status];
      if (!entry) {
        return null;
      }
      return {
        id: `lead-${lead.id}-${lead.status}`,
        icon: entry.icon,
        tone: entry.tone,
        title: entry.title,
        message: entry.message(lead),
        date: leadDate(lead),
        // What every one of these notifications is actually about — the property a
        // broker interacted with. Kept so the screen can navigate somewhere on tap
        // instead of just displaying text; not used by the copy above.
        propertyId: lead.property?.id,
      };
    })
    .filter(Boolean);

  items.sort((a, b) => new Date(b.date) - new Date(a.date));

  return items.map(item => ({
    ...item,
    timeAgo: timeAgo(item.date),
    isUnread: !readIds[item.id],
  }));
}
