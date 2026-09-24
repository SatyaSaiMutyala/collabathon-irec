import React, {useEffect} from 'react';
import {ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Badge,
  Button,
  Card,
  Chip,
  InfoRow,
  ProjectMiniCard,
  RemoteImage,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {clearRespondState, respondToLead, selectLeadById} from '../../store/slices/leadsSlice';
import {
  fetchNextPartnerProjects,
  fetchPartnerProjects,
  selectPartnerById,
  selectPartnerProjects,
} from '../../store/slices/partnersSlice';
import {initialsOf} from '../../utils/name';
import {SOCIAL_ICONS} from '../../utils/socialIcons';

/** ISO timestamp to "12 Mar 2026"; anything unparseable is dropped rather than shown raw. */
const formatDate = iso => {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  return Number.isNaN(date.getTime())
    ? null
    : date.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
};

/** Square, not circular or the wide 5:2 logo frame Avatar's own `square` shape uses —
 * a person's initials tile, matching the identity-row pattern used elsewhere in this
 * redesign (DeveloperProfileScreen's logo, StatTile's icon square). */
const InitialsTile = ({uri, name, size}) => {
  const {colors, radius} = useAppTheme();

  return (
    <View
      style={{
        width: size,
        height: size,
        borderRadius: radius.md,
        backgroundColor: colors.primarySoft,
        alignItems: 'center',
        justifyContent: 'center',
        overflow: 'hidden',
      }}>
      <RemoteImage
        uri={uri}
        style={{width: size, height: size}}
        resizeMode="cover"
        fallback={
          <AppText variant="h3" weight="bold" color={colors.primaryDark}>
            {initialsOf(name)}
          </AppText>
        }
      />
    </View>
  );
};

/** icon-in-a-circle + label-over-value, one line — the same "fact" shape
 * ProjectDetailsTab's Key details panel uses, for the same reason: every value here is
 * short enough to sit flush right instead of stacking under its label. */
const FactRow = ({label, value, isLast}) => {
  const {colors, spacing} = useAppTheme();
  if (!value) {
    return null;
  }

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: spacing.sm,
        borderBottomWidth: isLast ? 0 : 1,
        borderBottomColor: colors.border,
      }}>
      <AppText variant="body" color={colors.textSecondary} style={{flex: 1}}>
        {label}
      </AppText>
      <AppText variant="bodyMedium" weight="semiBold">
        {value}
      </AppText>
    </View>
  );
};

/**
 * One broker, reached either from a pending request in Requests or from an accepted
 * partner in Partners.
 *
 * When it is a request the screen is about the decision, so it leads with what that
 * decision should turn on — company, RERA, coverage, experience — and keeps the
 * reachable channels masked until accept. When it is a partner the decision is already
 * made, so the same layout gains the projects the two have actually worked on.
 */
const BrokerDetailScreen = ({route, navigation}) => {
  const {colors, radius, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  // Two entry points: a pending request from the inbox (`leadId`) or an accepted broker
  // from the partner roster (`partnerId`). Both resolve to the same broker payload —
  // PartnerResource builds it for either — so only the decision footer differs.
  const {leadId, partnerId} = route.params;

  const lead = useAppSelector(state => (leadId ? selectLeadById(state, leadId) : undefined));
  const partner = useAppSelector(state =>
    partnerId ? selectPartnerById(state, partnerId) : undefined,
  );
  const respondStatus = useAppSelector(state => state.leads.respondStatus);
  const respondError = useAppSelector(state => state.leads.respondError);

  const source = lead?.broker ?? partner;
  const brokerId = source?.id;
  const projects = useAppSelector(state =>
    brokerId ? selectPartnerProjects(state, brokerId) : undefined,
  );

  useEffect(() => {
    dispatch(clearRespondState());
  }, [dispatch]);

  // Only accepted brokers have projects to show, and the endpoint only answers for them.
  // Asking for a still-pending request would just be a 404 on every open.
  const hasProjects = (lead?.status ?? 'accepted') === 'accepted';

  useEffect(() => {
    if (brokerId && hasProjects) {
      dispatch(fetchPartnerProjects({brokerId, page: 1}));
    }
  }, [dispatch, brokerId, hasProjects]);

  if (!source) {
    return (
      <ScreenContainer edges={['top']}>
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
          <AppText variant="body" color={colors.textMuted}>
            {leadId ? 'This request is no longer available.' : 'This partner is no longer listed.'}
          </AppText>
        </View>
      </ScreenContainer>
    );
  }

  const broker = source;
  // The payload carries its own gate, so a partner row (always accepted) and a lead row
  // are read the same way rather than the screen inferring it from which param arrived.
  const visible = broker.contact_visible ?? lead?.contact_visible ?? false;
  const canRespond = lead?.status === 'interested';
  const busy = respondStatus === 'loading';

  const tone = {accepted: 'success', declined: 'danger', interested: 'warning'}[lead?.status];
  const label = lead
    ? {accepted: 'Accepted', declined: 'Rejected', interested: 'Requested'}[lead.status]
    : 'Partner';

  const respond = status => dispatch(respondToLead({leadId: lead.id, status}));

  return (
    <ScreenContainer edges={['top']}>
      <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm}}>
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h3" style={{marginLeft: spacing.sm}}>
          Request
        </AppText>
      </View>

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxxl}}>

        {/* ------------------------------------------------------------- identity */}
        <View style={{flexDirection: 'row', alignItems: 'flex-start', marginTop: spacing.lg}}>
          <InitialsTile uri={broker.photo_url} name={broker.name} size={moderateScale(56)} />
          <View style={{flex: 1, marginLeft: spacing.md}}>
            <AppText variant="h3" numberOfLines={2}>
              {broker.name}
            </AppText>
            {!!broker.company_name && (
              <AppText variant="body" color={colors.textSecondary} numberOfLines={1}>
                {broker.company_name}
              </AppText>
            )}
            {!!lead?.property?.name && (
              <AppText variant="caption" color={colors.textMuted} numberOfLines={1} style={{marginTop: moderateScale(2)}}>
                Requested {lead.property.name}
              </AppText>
            )}
            {!lead && broker.projects_count != null && (
              <AppText variant="caption" color={colors.textMuted} style={{marginTop: moderateScale(2)}}>
                {broker.projects_count} {broker.projects_count === 1 ? 'project' : 'projects'} together
                {formatDate(broker.last_collaborated_at)
                  ? ` · last accepted ${formatDate(broker.last_collaborated_at)}`
                  : ''}
              </AppText>
            )}
          </View>
          {!!label && <Badge label={label} tone={tone} />}
        </View>

        {/* ------------------------------------------------------------- facts */}
        <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          Request details
        </AppText>
        <View style={{backgroundColor: colors.surface, borderRadius: radius.lg, paddingHorizontal: spacing.md}}>
          {[
            {label: 'Project', value: lead?.property?.name},
            {label: 'Based in', value: [broker.city, broker.state].filter(Boolean).join(', ') || null},
            {label: 'Type', value: broker.is_company ? 'Company' : 'Independent broker'},
            {label: 'Experience', value: broker.years_of_experience ? `${broker.years_of_experience} years` : null},
            {label: 'Team size', value: broker.team_size ? `${broker.team_size}` : null},
            {label: 'RERA number', value: broker.rera_number},
            {label: 'GST number', value: broker.gst_number},
            {label: 'Operates', value: broker.operates_multiple_states ? 'Multiple states' : null},
            {label: 'On Collabathon since', value: formatDate(broker.registered_at ?? broker.member_since)},
          ]
            .filter(row => !!row.value)
            .map((row, index, arr) => (
              <FactRow key={row.label} label={row.label} value={row.value} isLast={index === arr.length - 1} />
            ))}
        </View>

        {/* ------------------------------------------------------------- contact */}
        <AppText variant="overline" color={colors.textMuted} style={{marginTop: spacing.lg}}>
          CONTACT
        </AppText>
        <Card style={{marginTop: spacing.xs}}>
          {!visible && (
            <View
              style={{
                flexDirection: 'row',
                alignItems: 'flex-start',
                backgroundColor: colors.primarySoft,
                borderRadius: radius.md,
                padding: spacing.sm,
                marginBottom: spacing.sm,
              }}>
              <Icon name="lock-closed" size={moderateScale(16)} color={colors.primaryDark} />
              <View style={{marginLeft: spacing.sm, flex: 1}}>
                <AppText variant="bodyMedium" color={colors.primaryDark}>
                  Your privacy matters
                </AppText>
                <AppText variant="caption" color={colors.textSecondary} style={{marginTop: moderateScale(2)}}>
                  The last few digits are hidden while this request is pending. Accepting
                  releases the full phone and email — declining leaves them masked.
                </AppText>
              </View>
            </View>
          )}
          <InfoRow
            icon="call-outline"
            label="Mobile"
            value={broker.mobile}
            valueColor={visible ? undefined : colors.textMuted}
          />
          <InfoRow
            icon="phone-portrait-outline"
            label="Alternate mobile"
            value={broker.alternate_mobile}
            valueColor={visible ? undefined : colors.textMuted}
          />
          <InfoRow
            icon="mail-outline"
            label="Email"
            value={broker.email}
            valueColor={visible ? undefined : colors.textMuted}
          />
          <InfoRow icon="globe-outline" label="Website" value={broker.company_website} />
          {(broker.social_links ?? []).map(link => (
            <InfoRow
              key={link.key}
              icon={SOCIAL_ICONS[link.key] ?? 'link-outline'}
              label={link.label}
              value={link.value}
            />
          ))}
          <InfoRow icon="business-outline" label="Office" value={broker.office_address} />
          <InfoRow icon="home-outline" label="Residence" value={broker.residence_address} />
        </Card>

        {/* ------------------------------------------------------------- coverage */}
        {(!!broker.segments?.length || !!broker.zones?.length) && (
          <>
            <AppText variant="overline" color={colors.textMuted} style={{marginTop: spacing.lg}}>
              COVERAGE
            </AppText>
            <Card style={{marginTop: spacing.xs}}>
              <View style={styles.chips}>
                {(broker.segments ?? []).map(segment => (
                  <View key={`seg-${segment}`} style={styles.chip}>
                    <Chip label={segment} />
                  </View>
                ))}
                {(broker.zones ?? []).map(zone => (
                  <View key={`zone-${zone}`} style={styles.chip}>
                    <Chip label={zone} />
                  </View>
                ))}
              </View>
            </Card>
          </>
        )}

        {/* ------------------------------------------------------------- projects */}
        {hasProjects && !!projects?.items?.length && (
          <>
            <View style={styles.sectionHead(spacing)}>
              <AppText variant="overline" color={colors.textMuted}>
                PROJECTS TOGETHER
              </AppText>
              <AppText variant="caption" color={colors.textMuted}>
                {projects.total}
              </AppText>
            </View>
            <View style={{marginTop: spacing.xs}}>
              {projects.items.map(item => (
                <ProjectMiniCard
                  key={item.id}
                  project={item.property}
                  meta={
                    formatDate(item.responded_at)
                      ? `Accepted ${formatDate(item.responded_at)}`
                      : null
                  }
                  // Reuses the developer's existing listing screen rather than adding a
                  // second one — it already shows this project plus everyone else on it,
                  // which is the question a tap from here is asking.
                  onPress={() =>
                    item.property &&
                    navigation.navigate('PropertyLeads', {projectId: item.property.id})
                  }
                />
              ))}

              {/* A nested FlatList inside this ScrollView would break virtualisation, so
                  the remaining pages load on demand instead of on scroll. */}
              {projects.page < projects.lastPage && (
                <Button
                  label={
                    projects.status === 'loadingMore'
                      ? 'Loading…'
                      : `Show more (${projects.total - projects.items.length} left)`
                  }
                  variant="outline"
                  size="md"
                  disabled={projects.status === 'loadingMore'}
                  onPress={() => dispatch(fetchNextPartnerProjects({brokerId}))}
                />
              )}
            </View>
          </>
        )}

        {!!broker.project_contributions && (
          <View style={{marginTop: spacing.lg}}>
            <AppText variant="h3" style={{marginBottom: spacing.sm}}>
              Past projects
            </AppText>
            <View style={{backgroundColor: colors.surface, borderRadius: radius.lg, padding: spacing.md}}>
              <AppText variant="body" color={colors.textSecondary} style={{fontStyle: 'italic'}}>
                "{broker.project_contributions}"
              </AppText>
              <AppText variant="caption" color={colors.textMuted} style={{marginTop: spacing.sm}}>
                — {broker.name}
              </AppText>
            </View>
          </View>
        )}

        {!!respondError && (
          <AppText variant="caption" color={colors.danger} style={{marginTop: spacing.md}}>
            {respondError}
          </AppText>
        )}

        {/* ------------------------------------------------------------- decision */}
        {canRespond && (
          <View style={{flexDirection: 'row', marginTop: spacing.xl}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Button
                label="Decline"
                variant="outline"
                disabled={busy}
                onPress={() => respond('declined')}
              />
            </View>
            <View style={{flex: 1, marginLeft: spacing.xs}}>
              <Button
                label={busy ? 'Sending…' : 'Accept request'}
                disabled={busy}
                onPress={() => respond('accepted')}
              />
            </View>
          </View>
        )}
      </ScrollView>
    </ScreenContainer>
  );
};

const styles = {
  sectionHead: spacing => ({
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
    marginTop: spacing.lg,
  }),
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  chip: {
    marginRight: moderateScale(6),
    marginBottom: moderateScale(6),
  },
};

export default BrokerDetailScreen;
