import React from 'react';
import {StyleSheet, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Button, ScreenContainer} from '../../components';

/**
 * Landing screen for signed-out users. Both roles share one sign-in — the server
 * resolves the role from the credentials — so this screen sets expectations rather
 * than asking the user to pick a side. Registration is broker-only by design:
 * developer accounts are provisioned by an admin.
 */

const ROLE_NOTES = [
  {
    icon: 'briefcase-outline',
    title: 'Channel partners',
    body: 'Browse live inventory, register interest, and track your leads.',
  },
  {
    icon: 'business-outline',
    title: 'Developers',
    body: 'Publish projects and respond to the partners chasing your inventory.',
  },
];

const WelcomeScreen = ({navigation}) => {
  const {colors, spacing, radius} = useAppTheme();

  return (
    <ScreenContainer edges={['top', 'bottom']} style={styles.screen}>
      <View style={{flex: 1, justifyContent: 'center'}}>
        <View
          style={[
            styles.mark,
            {backgroundColor: colors.primarySoft, borderRadius: radius.lg},
          ]}>
          <Icon name="sparkles" size={moderateScale(32)} color={colors.primaryDark} />
        </View>

        <AppText variant="overline" color={colors.primary} style={{marginTop: spacing.xl}}>
          WELCOME TO
        </AppText>
        <AppText variant="display" style={{marginTop: spacing.xxs}}>
          Collabathon
        </AppText>
        <AppText variant="body" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
          Where developers and channel partners work the same inventory, in one place.
        </AppText>

        <View style={{marginTop: spacing.xxl}}>
          {ROLE_NOTES.map(note => (
            <View key={note.title} style={[styles.noteRow, {marginBottom: spacing.lg}]}>
              <View
                style={[
                  styles.noteIcon,
                  {backgroundColor: colors.surface, borderRadius: radius.md},
                ]}>
                <Icon name={note.icon} size={moderateScale(18)} color={colors.primaryDark} />
              </View>
              <View style={{flex: 1, marginLeft: spacing.sm}}>
                <AppText variant="h3">{note.title}</AppText>
                <AppText
                  variant="caption"
                  color={colors.textSecondary}
                  style={{marginTop: moderateScale(2)}}>
                  {note.body}
                </AppText>
              </View>
            </View>
          ))}
        </View>
      </View>

      <View style={{paddingBottom: spacing.md}}>
        <Button
          label="Log In"
          icon="log-in-outline"
          onPress={() => navigation.navigate('Login')}
        />
        <Button
          label="Register as Channel Partner"
          variant="outline"
          onPress={() => navigation.navigate('Register')}
          style={{marginTop: spacing.sm}}
        />
        <AppText
          variant="caption"
          color={colors.textMuted}
          align="center"
          style={{marginTop: spacing.md}}>
          Developer accounts are created by the Collabathon team.
        </AppText>
      </View>
    </ScreenContainer>
  );
};

const styles = StyleSheet.create({
  screen: {
    justifyContent: 'space-between',
  },
  mark: {
    width: moderateScale(64),
    height: moderateScale(64),
    alignItems: 'center',
    justifyContent: 'center',
  },
  noteRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  noteIcon: {
    width: moderateScale(38),
    height: moderateScale(38),
    alignItems: 'center',
    justifyContent: 'center',
  },
});

export default WelcomeScreen;
