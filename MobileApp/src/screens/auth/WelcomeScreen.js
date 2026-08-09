import React from 'react';
import {Image, StatusBar, StyleSheet, TouchableOpacity, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import LinearGradient from 'react-native-linear-gradient';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText} from '../../components';

/**
 * Landing screen for signed-out users, built to a supplied reference design rather
 * than the app's own gold/navy theme — a deliberate one-screen exception, the same
 * way `roundedRadius.*` already carves circular UI out of the "square everywhere"
 * rule. Every screen past this one (Login onward) stays on the shared theme; this
 * splash keeps the brand mark's own colourful palette instead of diluting it into
 * the functional app's amber. Bypasses `<ScreenContainer>` for the same reason: that
 * component ties one `backgroundColor` to both the safe area and the status bar,
 * and this screen needs the safe area transparent (so the gradient behind shows
 * through the notch/home-indicator insets) while the status bar gets its own solid
 * top-of-gradient colour — two different values `<ScreenContainer>` cannot express.
 *
 * The two cards below are two genuinely different sign-in mechanisms, not one shared
 * screen with a role flag: channel partners use mobile number + OTP (`screen:
 * 'MobileOtpLogin'`, see that screen and OtpVerifyScreen — no password anywhere in
 * that path), developers use email + password (`screen: 'Login'`, admin-provisioned
 * accounts, so no self-serve register option there either way).
 */

const INK = '#151A34';
const INDIGO = '#6C4FE0';
const MUTED = '#6B7280';
const HAIRLINE = 'rgba(21, 26, 52, 0.12)';

const ROLES = [
  {
    key: 'broker',
    icon: 'briefcase-outline',
    title: 'Channel partners',
    body: 'Browse live inventory, register interest, and track your leads.',
    accent: '#7B61FF',
    soft: '#EFE9FD',
    // Mobile number + OTP — see MobileOtpLoginScreen. No password, on either side.
    screen: 'MobileOtpLogin',
  },
  {
    key: 'developer',
    icon: 'business-outline',
    title: 'Developers',
    body: 'Publish projects and respond to the partners chasing your inventory.',
    accent: '#3E8EF7',
    soft: '#E8F1FE',
    // Email + password — admin-provisioned accounts, no self-serve path.
    screen: 'Login',
  },
];

const WelcomeScreen = ({navigation}) => {
  const {spacing} = useAppTheme();

  return (
    <View style={styles.flex}>
      {/* A whisper of hue, not a tint — the reference reads as near-white with the
          faintest lavender/blue drift, not the more visibly-coloured wash this had
          before. */}
      <LinearGradient
        colors={['#FBFAFE', '#FDF6F8', '#F5F8FE']}
        start={{x: 0, y: 0}}
        end={{x: 1, y: 1}}
        style={StyleSheet.absoluteFillObject}
      />

      {/* Two soft colour washes low in the composition — the reference's background
          gets noticeably richer near the bottom corners than the plain diagonal
          gradient above covers on its own. Same "fade to fully transparent, peak
          colour pushed off-screen" trick as the logo's glow: each shape's highest
          opacity sits at the corner it bleeds off of, so the visible portion is
          already most of the way faded and no circular edge shows. */}
      <LinearGradient
        pointerEvents="none"
        colors={['rgba(255, 138, 178, 0.30)', 'rgba(255, 138, 178, 0)']}
        start={{x: 0, y: 1}}
        end={{x: 0.85, y: 0.15}}
        style={styles.blobPink}
      />
      <LinearGradient
        pointerEvents="none"
        colors={['rgba(120, 162, 255, 0.28)', 'rgba(120, 162, 255, 0)']}
        start={{x: 1, y: 1}}
        end={{x: 0.15, y: 0.15}}
        style={styles.blobBlue}
      />

      {/* Purely atmospheric — a loose scatter echoing the reference's confetti dots.
          Not tied to any data, so plain absolutely-positioned circles are enough;
          no need for the zero-dependency chart techniques used elsewhere. */}
      <View style={[styles.dot, styles.dotGreen]} />
      <View style={[styles.dot, styles.dotOrange]} />
      <View style={[styles.dot, styles.dotBlue]} />
      <View style={[styles.dot, styles.dotLavender]} />
      <DotGrid style={styles.gridTopLeft} />
      <DotGrid style={styles.gridBottomRight} />

      <StatusBar barStyle="dark-content" backgroundColor="#FBFAFE" translucent={false} />
      <SafeAreaView edges={['top', 'bottom']} style={styles.safe}>
        <View style={[styles.content, {paddingHorizontal: spacing.lg}]}>
          <View style={styles.hero}>
            {/* Four concentric, barely-there rings fake the mockup's blurred halo — a
                real radial blur needs a native library this app deliberately avoids
                (see the zero-dependency notes elsewhere in this codebase). One hue
                only, and opacity steps small enough (~0.02 apart) that no ring edge
                is visible; two contrasting hues here read as "extra colour" rather
                than ambient light, which was the whole point of a glow. */}
            <View style={styles.markGlow1}>
              <View style={styles.markGlow2}>
                <View style={styles.markGlow3}>
                  <View style={styles.markTile}>
                    <Image
                      source={require('../../assets/images/logo-mark.png')}
                      style={styles.mark}
                      resizeMode="contain"
                      accessibilityRole="image"
                      accessibilityLabel="Collabathon"
                    />
                  </View>
                </View>
              </View>
            </View>

            <View style={[styles.welcomeRow, {marginTop: spacing.xl}]}>
              <View style={styles.dashDot} />
              <LinearGradient
                colors={['#F5A623', 'rgba(245,166,35,0)']}
                start={{x: 0, y: 0}}
                end={{x: 1, y: 0}}
                style={styles.dashLine}
              />
              <AppText variant="overline" color={INDIGO} style={styles.welcomeText}>
                WELCOME TO
              </AppText>
              <LinearGradient
                colors={['rgba(245,166,35,0)', '#F5A623']}
                start={{x: 0, y: 0}}
                end={{x: 1, y: 0}}
                style={styles.dashLine}
              />
              <View style={styles.dashDot} />
            </View>

            <AppText variant="display" align="center" color={INK} style={{marginTop: spacing.xxs}}>
              Collabathon
            </AppText>

            <LinearGradient
              colors={['#3E8EF7', '#7B61FF', '#FF6B9D', '#F5A623']}
              start={{x: 0, y: 0}}
              end={{x: 1, y: 0}}
              style={styles.underline}
            />

            <AppText
              variant="body"
              color={MUTED}
              align="center"
              style={{marginTop: spacing.md, paddingHorizontal: spacing.sm}}>
              Where developers and channel partners work the same inventory, in one place.
            </AppText>
          </View>

          <View style={{marginTop: spacing.xl}}>
            {ROLES.map(role => (
              <TouchableOpacity
                key={role.key}
                activeOpacity={0.85}
                onPress={() => navigation.navigate(role.screen)}
                style={[styles.card, {borderLeftColor: role.accent, marginBottom: spacing.md}]}>
                <View style={[styles.iconRing, {borderColor: role.accent + '55'}]}>
                  <View style={[styles.iconBubble, {backgroundColor: role.soft}]}>
                    <Icon name={role.icon} size={moderateScale(22)} color={role.accent} />
                  </View>
                </View>

                <View style={styles.cardBody}>
                  <AppText variant="h3" color={INK}>
                    {role.title}
                  </AppText>
                  <AppText variant="caption" color={MUTED} style={{marginTop: moderateScale(3)}}>
                    {role.body}
                  </AppText>
                </View>

                <View style={[styles.chevronButton, {backgroundColor: role.soft}]}>
                  <Icon name="chevron-forward" size={moderateScale(16)} color={role.accent} />
                </View>
              </TouchableOpacity>
            ))}
          </View>

          <View style={{flex: 1}} />

          <View style={styles.footerRow}>
            <View style={styles.footerLine} />
            <View style={styles.footerBadge}>
              <Icon name="shield-checkmark-outline" size={moderateScale(16)} color={INDIGO} />
            </View>
            <View style={styles.footerLine} />
          </View>
          <AppText
            variant="caption"
            color="#8B90A0"
            align="center"
            style={{marginTop: spacing.sm, marginBottom: spacing.md}}>
            Developer accounts are created by the Collabathon team.
          </AppText>
        </View>
      </SafeAreaView>
    </View>
  );
};

/** The reference's small corner dot-matrix texture — nine 3px dots on a loose grid. */
const DotGrid = ({style}) => (
  <View style={[styles.dotGrid, style]}>
    {Array.from({length: 9}).map((_, i) => (
      <View key={i} style={styles.dotGridItem} />
    ))}
  </View>
);

const styles = StyleSheet.create({
  flex: {flex: 1},
  safe: {flex: 1},
  content: {flex: 1},

  hero: {alignItems: 'center', marginTop: moderateScale(8)},

  // One hue (a muted lavender), opacity climbing in small ~0.02 steps toward the
  // centre — that gradual a ramp reads as soft light falling off, not as a ring.
  markGlow1: {
    width: moderateScale(232),
    height: moderateScale(232),
    borderRadius: moderateScale(116),
    backgroundColor: 'rgba(184, 168, 219, 0.05)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  markGlow2: {
    width: moderateScale(196),
    height: moderateScale(196),
    borderRadius: moderateScale(98),
    backgroundColor: 'rgba(184, 168, 219, 0.06)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  markGlow3: {
    width: moderateScale(160),
    height: moderateScale(160),
    borderRadius: moderateScale(80),
    backgroundColor: 'rgba(184, 168, 219, 0.07)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  markTile: {
    width: moderateScale(132),
    height: moderateScale(132),
    borderRadius: moderateScale(66),
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#3A2E66',
    shadowOffset: {width: 0, height: moderateScale(10)},
    shadowOpacity: 0.14,
    shadowRadius: moderateScale(20),
    elevation: 6,
  },
  // 76:68 is the artwork's own 385:345, so `contain` has nothing to letterbox.
  mark: {
    width: moderateScale(76),
    height: moderateScale(68),
  },

  welcomeRow: {flexDirection: 'row', alignItems: 'center'},
  welcomeText: {letterSpacing: moderateScale(2), marginHorizontal: moderateScale(8)},
  dashLine: {width: moderateScale(22), height: moderateScale(2), borderRadius: moderateScale(1)},
  dashDot: {
    width: moderateScale(4),
    height: moderateScale(4),
    borderRadius: moderateScale(2),
    backgroundColor: '#F5A623',
  },

  underline: {
    width: moderateScale(72),
    height: moderateScale(3),
    borderRadius: moderateScale(2),
    marginTop: moderateScale(10),
  },

  card: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: moderateScale(20),
    borderLeftWidth: moderateScale(4),
    paddingVertical: moderateScale(14),
    paddingHorizontal: moderateScale(14),
    shadowColor: '#3A2E66',
    shadowOffset: {width: 0, height: moderateScale(8)},
    shadowOpacity: 0.07,
    shadowRadius: moderateScale(16),
    elevation: 3,
  },
  iconRing: {
    width: moderateScale(62),
    height: moderateScale(62),
    borderRadius: moderateScale(31),
    borderWidth: 1,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: moderateScale(14),
  },
  iconBubble: {
    width: moderateScale(50),
    height: moderateScale(50),
    borderRadius: moderateScale(25),
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardBody: {flex: 1, marginRight: moderateScale(8)},
  chevronButton: {
    width: moderateScale(34),
    height: moderateScale(34),
    borderRadius: moderateScale(17),
    alignItems: 'center',
    justifyContent: 'center',
  },

  footerRow: {flexDirection: 'row', alignItems: 'center'},
  footerLine: {flex: 1, height: 1, backgroundColor: HAIRLINE},
  footerBadge: {
    width: moderateScale(32),
    height: moderateScale(32),
    borderRadius: moderateScale(16),
    backgroundColor: '#EFE9FD',
    alignItems: 'center',
    justifyContent: 'center',
    marginHorizontal: moderateScale(10),
  },

  // Large circles bled mostly off-screen at the bottom corners — see the fade
  // direction on each LinearGradient above for why no edge shows.
  blobPink: {
    position: 'absolute',
    width: moderateScale(420),
    height: moderateScale(420),
    borderRadius: moderateScale(210),
    bottom: moderateScale(-200),
    left: moderateScale(-170),
  },
  blobBlue: {
    position: 'absolute',
    width: moderateScale(380),
    height: moderateScale(380),
    borderRadius: moderateScale(190),
    bottom: moderateScale(-180),
    right: moderateScale(-150),
  },

  dot: {position: 'absolute', borderRadius: moderateScale(10)},
  dotGreen: {
    top: '13%',
    left: '9%',
    width: moderateScale(9),
    height: moderateScale(9),
    backgroundColor: '#9AD39A',
  },
  dotOrange: {
    top: '9%',
    right: '9%',
    width: moderateScale(13),
    height: moderateScale(13),
    backgroundColor: '#F5A623',
  },
  dotBlue: {
    top: '31%',
    right: '5%',
    width: moderateScale(11),
    height: moderateScale(11),
    backgroundColor: '#A9CFF2',
  },
  dotLavender: {
    top: '27%',
    left: '11%',
    width: moderateScale(13),
    height: moderateScale(13),
    backgroundColor: '#C9BFEE',
  },

  dotGrid: {
    position: 'absolute',
    width: moderateScale(34),
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  dotGridItem: {
    width: moderateScale(3),
    height: moderateScale(3),
    borderRadius: moderateScale(2),
    backgroundColor: 'rgba(21, 26, 52, 0.14)',
    margin: moderateScale(3),
  },
  gridTopLeft: {top: '3%', left: '2%'},
  gridBottomRight: {bottom: '4%', right: '3%'},
});

export default WelcomeScreen;
