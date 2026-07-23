import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Card, ScreenContainer} from '../../components';
import {useAppSelector} from '../../store/hooks';

const RoleCard = ({icon, title, description, onPress}) => {
  const {colors, radius, spacing} = useAppTheme();
  return (
    <TouchableOpacity activeOpacity={0.85} onPress={onPress} style={{marginBottom: spacing.md}}>
      <Card>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          <View
            style={{
              width: moderateScale(52),
              height: moderateScale(52),
              borderRadius: radius.md,
              backgroundColor: colors.primarySoft,
              alignItems: 'center',
              justifyContent: 'center',
            }}>
            <Icon name={icon} size={moderateScale(24)} color={colors.primaryDark} />
          </View>
          <View style={{flex: 1, marginLeft: spacing.md}}>
            <AppText variant="h3">{title}</AppText>
            <AppText variant="caption" color={colors.textMuted} style={{marginTop: moderateScale(2)}}>
              {description}
            </AppText>
          </View>
          <Icon name="chevron-forward" size={moderateScale(18)} color={colors.textMuted} />
        </View>
      </Card>
    </TouchableOpacity>
  );
};

const RoleSelectScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const registrationStatus = useAppSelector(state => state.auth.registrationStatus);

  const goToBroker = () => {
    navigation.navigate(registrationStatus === 'guest' ? 'Register' : 'Login');
  };

  return (
    <ScreenContainer edges={['top', 'bottom']} style={{justifyContent: 'center'}}>
      <View style={{marginBottom: spacing.xxl}}>
        <AppText variant="overline" color={colors.primary}>
          WELCOME TO COLLABATHON
        </AppText>
        <AppText variant="display" style={{marginTop: spacing.xxs}}>
          Continue as
        </AppText>
        <AppText variant="body" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
          Choose how you'd like to use the app.
        </AppText>
      </View>

      <RoleCard
        icon="people-outline"
        title="Broker / CP / Agent"
        description="Browse verified developers and mark projects you're interested in."
        onPress={goToBroker}
      />
      <RoleCard
        icon="business-outline"
        title="Developer / Builder"
        description="Manage your properties and respond to broker interest."
        onPress={() => navigation.navigate('DeveloperLogin')}
      />
    </ScreenContainer>
  );
};

export default RoleSelectScreen;
