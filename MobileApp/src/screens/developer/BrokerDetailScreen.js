import React, {useEffect} from 'react';
import {ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Badge,
  Button,
  Card,
  Chip,
  InfoRow,
  ProjectMiniCard,
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
  const {colors, spacing} = useAppTheme();
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
          Broker request
        </AppText>
      </View>

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxxl}}>

        {/* ------------------------------------------------------------- identity */}
        <View style={{alignItems: 'center', marginTop: spacing.lg}}>
          <Avatar
            uri={broker.photo_url}
            name={broker.name}
            size="xl"
            ringColor={colors.primary}
          />
          <AppText variant="h2" align="center" style={{marginTop: spacing.md}}>
            {broker.name}
          </AppText>
          {!!broker.company_name && (
            <AppText variant="body" color={colors.textSecondary} align="center">
              {broker.company_name}
            </AppText>
          )}
          {!!label && (
            <View style={{marginTop: spacing.sm}}>
              <Badge label={label} tone={tone} />
            </View>
          )}
          {!!lead?.property?.name && (
            <AppText
              variant="caption"
              color={colors.textMuted}
              align="center"
              style={{marginTop: spacing.xs}}>
              Requested {lead.property.name}
            </AppText>
          )}
          {!lead && broker.projects_count != null && (
            <AppText
              variant="caption"
              color={colors.textMuted}
              align="center"
              style={{marginTop: spacing.xs}}>
              {broker.projects_count} {broker.projects_count === 1 ? 'project' : 'projects'} together
              {formatDate(broker.last_collaborated_at)
                ? ` · last accepted ${formatDate(broker.last_collaborated_at)}`
                : ''}
            </AppText>
          )}
        </View>

        {/* ------------------------------------------------------------- contact */}
        <AppText variant="overline" color={colors.textMuted} style={{marginTop: spacing.xl}}>
          CONTACT
        </AppText>
        <Card style={{marginTop: spacing.xs}}>
          {!visible && (
            <View style={styles.lockNote}>
              <Icon name="lock-closed" size={moderateScale(15)} color={colors.warning} />
              <AppText
                variant="caption"
                color={colors.textSecondary}
                style={{marginLeft: moderateScale(8), flex: 1}}>
                The last few digits are hidden while this request is pending. Accepting
                releases the full phone and email — declining leaves them masked.
              </AppText>
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

        {/* ------------------------------------------------------------- credentials */}
        <AppText variant="overline" color={colors.textMuted} style={{marginTop: spacing.lg}}>
          CREDENTIALS
        </AppText>
        <Card style={{marginTop: spacing.xs}}>
          <InfoRow icon="shield-checkmark-outline" label="RERA number" value={broker.rera_number} />
          <InfoRow
            icon="calendar-outline"
            label="RERA valid until"
            value={broker.rera_certificate_expiry}
          />
          <InfoRow icon="receipt-outline" label="GST number" value={broker.gst_number} />
          <InfoRow
            icon="briefcase-outline"
            label="Type"
            value={broker.is_company ? 'Company' : 'Independent broker'}
          />
          <InfoRow
            icon="ribbon-outline"
            label="Experience"
            value={broker.years_of_experience ? `${broker.years_of_experience} years` : null}
          />
          <InfoRow
            icon="people-outline"
            label="Team size"
            value={broker.team_size ? `${broker.team_size}` : null}
          />
          <InfoRow
            icon="location-outline"
            label="Based in"
            value={[broker.city, broker.state].filter(Boolean).join(', ') || null}
          />
          <InfoRow
            icon="map-outline"
            label="Operates"
            value={broker.operates_multiple_states ? 'Multiple states' : null}
          />
          <InfoRow
            icon="time-outline"
            label="On Collabathon since"
            value={formatDate(broker.registered_at ?? broker.member_since)}
          />
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
          <>
            <AppText variant="overline" color={colors.textMuted} style={{marginTop: spacing.lg}}>
              PAST PROJECTS
            </AppText>
            <Card style={{marginTop: spacing.xs}}>
              <AppText variant="body" color={colors.textSecondary}>
                {broker.project_contributions}
              </AppText>
            </Card>
          </>
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
  lockNote: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingBottom: moderateScale(10),
  },
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
