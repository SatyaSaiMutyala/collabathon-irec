import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
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

const DeveloperCard = ({developer, onPress}) => {
  const {colors, spacing} = useAppTheme();
  const projectLabel = `${developer.projects.length} ${
    developer.projects.length === 1 ? 'Project' : 'Projects'
  }`;

  return (
    <TouchableOpacity activeOpacity={0.85} onPress={onPress} style={{marginBottom: spacing.sm}}>
      <Card style={{paddingVertical: spacing.sm}}>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          <Avatar
            uri={developer.logo}
            name={developer.name}
            size="lg"
            ringColor={developer.verified ? colors.primary : colors.border}
            showVerified={developer.verified}
          />

          <View style={{flex: 1, marginLeft: spacing.sm}}>
            <AppText variant="h3" numberOfLines={1}>
              {developer.name}
            </AppText>

            <View style={{marginTop: moderateScale(4)}}>
              <MetaItem icon="location-outline" label={developer.city} />
              <View style={{flexDirection: 'row', marginTop: moderateScale(4)}}>
                <MetaItem icon="business-outline" label={projectLabel} />
                <View style={{width: moderateScale(12)}} />
                <MetaItem
                  icon="pricetag-outline"
                  label={`${developer.cpPayoutPercent}% CP`}
                  color={colors.primaryDark}
                />
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
