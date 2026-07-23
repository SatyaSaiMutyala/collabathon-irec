export const mockBrokers = [
  {
    id: 'lead-broker-1',
    name: 'Sara Al Farsi',
    company: 'Skyline Realty',
    mobile: '+971 50 111 2222',
    email: 'sara@skylinerealty.com',
    reraNumber: 'RERA-11223',
  },
  {
    id: 'lead-broker-2',
    name: 'Omar Al Hashimi',
    company: 'Elite Properties',
    mobile: '+971 50 222 3333',
    email: 'omar@eliteprop.com',
    reraNumber: 'RERA-22334',
  },
  {
    id: 'lead-broker-3',
    name: 'Layla Haddad',
    company: 'Urban Nest Realty',
    mobile: '+971 50 333 4444',
    email: 'layla@urbannest.com',
    reraNumber: 'RERA-33445',
  },
  {
    id: 'lead-broker-4',
    name: 'Yusuf Karim',
    company: 'Horizon Homes',
    mobile: '+971 50 444 5555',
    email: 'yusuf@horizonhomes.com',
    reraNumber: 'RERA-44556',
  },
];

/** Seed leads per project: projectId -> array of {brokerId, status, markedAt}. Status: 'viewed' | 'interested'. */
export const projectLeadSeed = {
  'proj-1': [
    {brokerId: 'lead-broker-1', status: 'interested', markedAt: '2026-07-20T09:30:00Z'},
    {brokerId: 'lead-broker-2', status: 'viewed', markedAt: '2026-07-21T14:10:00Z'},
    {brokerId: 'lead-broker-3', status: 'viewed', markedAt: '2026-07-21T16:45:00Z'},
  ],
  'proj-2': [
    {brokerId: 'lead-broker-4', status: 'interested', markedAt: '2026-07-19T11:00:00Z'},
  ],
  'proj-3': [
    {brokerId: 'lead-broker-2', status: 'interested', markedAt: '2026-07-22T08:15:00Z'},
    {brokerId: 'lead-broker-1', status: 'viewed', markedAt: '2026-07-22T10:00:00Z'},
  ],
  'proj-4': [
    {brokerId: 'lead-broker-3', status: 'viewed', markedAt: '2026-07-18T13:20:00Z'},
  ],
};

export function getBrokerById(id) {
  return mockBrokers.find(b => b.id === id);
}

export function getLeadsForProject(projectId) {
  return projectLeadSeed[projectId] ?? [];
}

/** Weekly profile-view trend for the dashboard chart, Mon..Sun. */
export const weeklyProfileViews = [12, 18, 9, 24, 30, 21, 15];

/**
 * Deterministic mock weekly-bucket profile-view trend for a given month label
 * (e.g. "Jul 2026"), so switching months shows different-but-stable numbers
 * without needing a hand-written dataset per month.
 */
export function getMonthlyProfileViews(monthLabel) {
  let seed = 0;
  for (let i = 0; i < monthLabel.length; i += 1) {
    seed += monthLabel.charCodeAt(i) * (i + 7);
  }
  const next = () => {
    seed = (seed * 9301 + 49297) % 233280;
    return seed / 233280;
  };
  return Array.from({length: 5}, () => Math.round(70 + next() * 70));
}
