import React, {useState} from 'react';
import {ScrollView, View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Badge,
  Button,
  Card,
  ConfirmDialog,
  InfoRow,
  ScreenContainer,
  StatRow,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {logout} from '../../store/slices/authSlice';
import {showSnackbar} from '../../store/slices/uiSlice';
import {openLink} from '../../utils/openLink';
import {SOCIAL_ICONS} from '../../utils/socialIcons';

const SectionTitle = ({children, spacing}) => (
  <AppText variant="h3" style={{marginTop: spacing.lg, marginBottom: spacing.sm}}>
    {children}
  </AppText>
);

/**
 * The developer's own company record.
 *
 * Every row here is a field the admin panel collects on the developer — the same set as
 * its Company panel, in the same order — so a developer reading this screen sees what
 * the admin sees of them. The one deliberate exception is the key contact (person,
 * designation, mobile, email): that is the internal relationship owner, DeveloperResource
 * does not send it, and this screen must never grow a row for it.
 */
const ProfileScreen = () => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const [confirmLogout, setConfirmLogout] = useState(false);
  const user = useAppSelector(state => state.auth.user);
  // DeveloperResource's own shape (snake_case). This screen used to read a camelCase
  // mock-data object with a `projects` array; that array has no API equivalent, so the
  // property tally comes from the `properties_count` the resource exposes when counted.
  const company = user?.developer;
  const socialLinks = company?.social_links ?? [];

  // The address block, as one value rather than five rows of one word each.
  const region = [company?.city, company?.state, company?.country, company?.pincode]
    .filter(Boolean)
    .join(', ');

  // Shown only when the login differs from the company address on record — otherwise it
  // is the same string twice under two labels.
  const loginEmail = user?.email && user.email !== company?.email ? user.email : null;

  return (
    <ScreenContainer edges={['top']}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
          Profile
        </AppText>

        <Card style={{paddingVertical: spacing.md}}>
          <View style={{alignItems: 'center'}}>
            <Avatar
              uri={company?.logo_url}
              name={company?.company_name}
              size="lg"
              ringColor={company?.verified ? colors.primary : colors.border}
              showVerified={company?.verified}
            />
            <AppText variant="h3" align="center" style={{marginTop: spacing.sm}}>
              {company?.company_name ?? 'Your Company'}
            </AppText>
            <AppText variant="caption" color={colors.textSecondary} style={{marginTop: moderateScale(1)}}>
              {[company?.contact_person ?? user?.name, company?.contact_designation]
                .filter(Boolean)
                .join(' · ')}
            </AppText>
            {company?.verified && (
              <View style={{marginTop: spacing.xs}}>
                <Badge label="Verified Developer" tone="success" />
              </View>
            )}
          </View>

          {company && (
            <View
              style={{
                marginTop: spacing.md,
                paddingTop: spacing.sm,
                borderTopWidth: 1,
                borderTopColor: colors.border,
              }}>
              {/* No CP payout tile. The admin panel stopped collecting
                  `cp_payout_percent` — it is not on the create form, the edit dialog or
                  the developer sheet, and store() just stamps 2.50 on every new record.
                  A percentage nobody can change, shown as if it were this developer's
                  own rate, is worse than no tile. Per-project CP commission, which is
                  still collected, is on the project screen where it belongs. */}
              <StatRow
                stats={[
                  {value: String(company.properties_count ?? '—'), label: 'Listings'},
                  {value: company.city ?? '—', label: 'City'},
                ]}
              />
            </View>
          )}
        </Card>

        {/* ------------------------------------------------------------------ company */}
        <SectionTitle spacing={spacing}>Company</SectionTitle>
        <Card style={{paddingVertical: spacing.xxs}}>
          <InfoRow icon="business-outline" label="Company name" value={company?.company_name} />
          <InfoRow icon="shield-checkmark-outline" label="RERA / licence" value={company?.rera_number} />
          <InfoRow
            icon="globe-outline"
            label="Website"
            value={company?.website}
            valueColor={colors.primary}
            onPress={() => openLink(company?.website)}
          />
          <InfoRow
            icon="pulse-outline"
            label="Account status"
            value={company?.status ? company.status[0].toUpperCase() + company.status.slice(1) : null}
            valueColor={company?.status === 'active' ? colors.success : colors.textPrimary}
          />
        </Card>

        {/* ------------------------------------------------------------------ contact */}
        <SectionTitle spacing={spacing}>Contact</SectionTitle>
        <Card style={{paddingVertical: spacing.xxs}}>
          <InfoRow
            icon="person-outline"
            label="Contact person"
            value={company?.contact_person ?? user?.name}
          />
          <InfoRow icon="briefcase-outline" label="Designation" value={company?.contact_designation} />
          <InfoRow icon="call-outline" label="Mobile" value={company?.mobile ?? user?.mobile} />
          <InfoRow icon="mail-outline" label="Email" value={company?.email} />
          <InfoRow icon="key-outline" label="Login email" value={loginEmail} />
        </Card>

        {/* ------------------------------------------------------------------ location */}
        {!!(region || company?.address) && (
          <>
            <SectionTitle spacing={spacing}>Location</SectionTitle>
            <Card style={{paddingVertical: spacing.xxs}}>
              <InfoRow icon="map-outline" label="City, state, country" value={region || null} />
              <InfoRow icon="navigate-outline" label="Address" value={company?.address} />
            </Card>
          </>
        )}

        {/* ------------------------------------------------------------------ social */}
        {!!socialLinks.length && (
          <>
            <SectionTitle spacing={spacing}>Social media</SectionTitle>
            <Card style={{paddingVertical: spacing.xxs}}>
              {socialLinks.map(link => (
                <InfoRow
                  key={link.key}
                  icon={SOCIAL_ICONS[link.key] ?? 'link-outline'}
                  label={link.label}
                  value={link.value}
                  valueColor={colors.primary}
                  onPress={() => openLink(link.value)}
                />
              ))}
            </Card>
          </>
        )}

        {!!company?.about && (
          <>
            <SectionTitle spacing={spacing}>About the company</SectionTitle>
            <Card>
              <AppText variant="body" color={colors.textSecondary}>
                {company.about}
              </AppText>
            </Card>
          </>
        )}

        <Button
          label="Log Out"
          variant="outline"
          icon="log-out-outline"
          style={{marginTop: spacing.lg}}
          onPress={() => setConfirmLogout(true)}
        />
      </ScrollView>

      <ConfirmDialog
        visible={confirmLogout}
        icon="log-out-outline"
        tone="danger"
        title="Log out?"
        message="You'll need to sign in again to get back to your account."
        confirmLabel="Log out"
        onCancel={() => setConfirmLogout(false)}
        onConfirm={() => {
          setConfirmLogout(false);
          dispatch(logout());
          dispatch(showSnackbar('Signed out'));
        }}
      />
    </ScreenContainer>
  );
};

export default ProfileScreen;
