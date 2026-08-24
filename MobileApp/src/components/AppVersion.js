import React from 'react';
import {View} from 'react-native';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import {version as APP_VERSION} from '../../package.json';

/**
 * The build number, shown once at the foot of each Profile screen.
 *
 * Read straight out of package.json rather than a hand-maintained constant, so it
 * cannot drift from what actually gets bumped at release time. The native
 * `versionName` in android/app/build.gradle is still bumped by hand alongside it,
 * same as any other release step.
 *
 * It used to sit as a floating watermark over the channel-partner home screen, where
 * it overlapped the developer cards and appeared on only one of the two interfaces.
 * The end of Profile is where someone actually looks for a version number when
 * reporting a problem.
 */
const AppVersion = () => {
  const {colors, spacing} = useAppTheme();

  return (
    <View style={{alignItems: 'center', marginTop: spacing.xl, marginBottom: spacing.lg}}>
      <AppText variant="caption" color={colors.textMuted}>
        Version {APP_VERSION}
      </AppText>
    </View>
  );
};

export default AppVersion;
