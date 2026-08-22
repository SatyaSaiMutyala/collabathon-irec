import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Avatar from './Avatar';
import Card from './Card';

const MetaItem = ({icon, label, color}) => {
  const {colors} = useAppTheme();
  return (
    <View style={{flexDirection: 'row', alignItems: 'center'}}>
      <Icon name={icon} size={moderateScale(12)} color={color ?? colors.textMuted} />
      <AppText
        variant="caption"
        color={color ?? colors.textMuted}
        weight={color ? 'semiBold' : undefined}
        style={{marginLeft: moderateScale(3)}}>
        {label}
      </AppText>
    </View>
  );
};

/**
 * Reads the API's developer shape: company_name / logo_url / properties_count.
 * `properties_count` is a server-side aggregate — the card must never rely on a
 * loaded projects array, because a developer's listings are paginated separately.
 */
const DeveloperCard = ({developer, onPress}) => {
  const {colors, spacing} = useAppTheme();

  const name = developer.company_name;
  const count = developer.properties_count ?? 0;
  const projectLabel = `${count} ${count === 1 ? 'Project' : 'Projects'}`;

  return (
    <TouchableOpacity activeOpacity={0.85} onPress={onPress} style={{marginBottom: spacing.sm}}>
      <Card style={{paddingVertical: spacing.sm, borderWidth: 0}}>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          {/* A logo box, not a circle. The admin's upload cropper fixes every logo to
              5:2 before it is saved (see app.js's cropper), and Avatar's `square` shape
              is that same 5:2 frame with `contain` — so the logo lands here exactly as
              it was cropped, edge to edge. The circle default was cropping a wide
              wordmark to its middle with `cover`, which cut the ends off every logo.
              `md` rather than `lg`: at 5:2 an `lg` box is 160pt wide and leaves the
              name nowhere to go on a row this size. */}
          <Avatar
            uri={developer.logo_url}
            name={name}
            size="md"
            shape="square"
            showVerified={developer.verified}
          />

          <View style={{flex: 1, marginLeft: spacing.sm}}>
            <AppText variant="h3" numberOfLines={1}>
              {name}
            </AppText>

            <View style={{marginTop: moderateScale(4)}}>
              <MetaItem icon="location-outline" label={developer.city ?? '—'} />
              <View style={{marginTop: moderateScale(4)}}>
                <MetaItem icon="business-outline" label={projectLabel} />
              </View>
            </View>
          </View>

          <Icon name="chevron-forward" size={moderateScale(18)} color={colors.textMuted} />
        </View>
      </Card>
    </TouchableOpacity>
  );
};

export default DeveloperCard;
