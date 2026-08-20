import React from 'react';
import {ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {roundedRadius, useAppTheme} from '../../theme';
import {AppText, ScreenContainer} from '../../components';

/**
 * The app's own Terms & Conditions — bundled as static content rather than an
 * external link. iREC/Collabathon collects KYC documents (PAN, Aadhaar, RERA, GST)
 * during registration, so this needs to actually exist and actually work; a broken
 * or missing terms link is exactly the kind of thing App Review calls out, on top of
 * being the wrong thing to ship on a screen that is asking someone to hand over ID
 * documents. Static text, not a WebView: no network dependency, so it can never be
 * the thing that's down when a reviewer — or a real user — checks it.
 *
 * This copy is a reasonable starting point, not legal advice: have it reviewed by
 * actual counsel before this ships to real users, the same way any other app's terms
 * would be.
 */
const SECTIONS = [
  {
    title: '1. Acceptance of Terms',
    body:
      'By creating an account or using Collabathon ("the App", "the Platform"), you agree to be bound by these Terms & Conditions. If you do not agree, please do not register for or use the Platform.',
  },
  {
    title: '2. What Collabathon Is',
    body:
      'Collabathon is a private marketplace connecting real estate developers with channel partners (brokers). Developers list projects; channel partners browse listings, send requests, and follow up on introductions through the Platform. Collabathon is not a party to any transaction between a developer and a channel partner, and does not itself buy, sell, or broker property.',
  },
  {
    title: '3. Account Registration & Verification',
    body:
      'Channel partner accounts are self-registered and reviewed before activation. You agree to provide accurate, current information, including identity and business documents (such as PAN, Aadhaar, RERA registration, and GST details) requested for verification. Collabathon may accept, reject, or request more information about any registration at its discretion. Developer accounts are provisioned directly by the Collabathon team.',
  },
  {
    title: '4. Your Responsibilities',
    body:
      'You are responsible for the accuracy of the information and documents you submit, for keeping your account credentials confidential, and for all activity under your account. You agree not to misuse the Platform — including submitting false documents, impersonating another person or business, or attempting to access another account without authorization.',
  },
  {
    title: '5. Commission & Payments',
    body:
      'Any commission percentage shown against a project is set by the developer and is informational, shown to help a channel partner evaluate a listing. Collabathon does not process or guarantee commission payments between developers and channel partners; any such payment is a matter between those two parties.',
  },
  {
    title: '6. Data We Collect',
    body:
      'We collect the information you provide during registration (including KYC documents), your activity on listings (views and requests sent), and device information needed to operate the app (such as push-notification tokens). This data is used to operate the Platform, verify accounts, and connect developers with channel partners. We do not sell your personal data.',
  },
  {
    title: '7. Account Deletion',
    body:
      'You may delete your account at any time from the Profile screen. Deleting your account deactivates it immediately and permanently — you will be signed out, and the account cannot be used to sign in again.',
  },
  {
    title: '8. Intellectual Property',
    body:
      'The Collabathon name, logo, and app design belong to Collabathon. Project listings, photos, and documents remain the property of the developer who submitted them.',
  },
  {
    title: '9. Limitation of Liability',
    body:
      'Collabathon provides the Platform "as is". We do not guarantee the accuracy of listing details supplied by developers, and we are not liable for any dispute, loss, or damage arising from a transaction or communication between a developer and a channel partner.',
  },
  {
    title: '10. Changes to These Terms',
    body:
      'We may update these Terms from time to time. Continued use of the Platform after a change means you accept the updated Terms.',
  },
  {
    title: '11. Contact',
    body: 'Questions about these Terms can be sent to the Collabathon team through the app.',
  },
];

const TermsScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();

  return (
    <ScreenContainer edges={['top']}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          marginTop: spacing.sm,
          marginBottom: spacing.sm,
        }}>
        <TouchableOpacity
          activeOpacity={0.7}
          onPress={() => navigation.goBack()}
          hitSlop={10}
          style={{
            width: moderateScale(34),
            height: moderateScale(34),
            borderRadius: roundedRadius.control,
            alignItems: 'center',
            justifyContent: 'center',
            marginRight: spacing.xs,
          }}>
          <Icon name="chevron-back" size={moderateScale(22)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h2">Terms &amp; Conditions</AppText>
      </View>

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <AppText variant="caption" color={colors.textMuted} style={{marginBottom: spacing.md}}>
          Last updated: August 2026
        </AppText>

        {SECTIONS.map(section => (
          <View key={section.title} style={{marginBottom: spacing.lg}}>
            <AppText variant="h3" style={{marginBottom: spacing.xs}}>
              {section.title}
            </AppText>
            <AppText variant="body" color={colors.textSecondary}>
              {section.body}
            </AppText>
          </View>
        ))}
      </ScrollView>
    </ScreenContainer>
  );
};

export default TermsScreen;
