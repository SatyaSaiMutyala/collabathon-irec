import React, {useCallback, useMemo, useState} from 'react';
import {ActivityIndicator, Alert, Linking, Platform, Share, TouchableOpacity, View} from 'react-native';
import {WebView} from 'react-native-webview';
import Pdf from 'react-native-pdf';
import ReactNativeBlobUtil from 'react-native-blob-util';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {roundedRadius, useAppTheme} from '../../theme';
import {AppText, Button, EmptyState, ScreenContainer} from '../../components';
import {useAppDispatch} from '../../store/hooks';
import {showSnackbar} from '../../store/slices/uiSlice';

/**
 * The developer's terms for one project — reached from the Terms button on the project
 * details of both the broker and the developer app.
 *
 * Two shapes, decided by the admin at intake:
 *   document → a PDF/DOC the developer signed. PDFs render inline; anything else has no
 *              in-app viewer and is handed to the OS.
 *   text     → rich text typed in the admin panel, rendered as HTML.
 *
 * The screen takes the whole `terms` object through route params rather than refetching:
 * it is only ever opened from a details screen that already has the property loaded, and
 * a second request would show a spinner for data already in memory.
 */
const ProjectTermsScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const {terms, projectName} = route.params ?? {};

  const [isDownloading, setIsDownloading] = useState(false);
  const [pdfError, setPdfError] = useState(null);

  const isDocument = terms?.type === 'document';
  const isPdf = isDocument && terms?.documentExtension === 'pdf';

  /**
   * The stored markup is a fragment, so it needs a document around it. The styles here
   * mirror the app's own type scale — without them a WebView falls back to Times at
   * browser defaults, which looks nothing like the screen it was opened from.
   *
   * `user-scalable=no` and the fixed width keep it from behaving like a desktop page.
   */
  const html = useMemo(() => {
    if (isDocument || !terms?.content) {
      return null;
    }

    return `<!doctype html><html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<style>
  :root { color-scheme: light; }
  body {
    margin: 0; padding: 16px 18px 48px;
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 15px; line-height: 1.6;
    color: ${colors.textPrimary}; background: ${colors.background};
    -webkit-text-size-adjust: 100%;
  }
  h1, h2, h3, h4 { font-weight: 600; line-height: 1.3; margin: 1.4em 0 .5em; }
  h1 { font-size: 1.3em; } h2 { font-size: 1.18em; } h3 { font-size: 1.06em; } h4 { font-size: 1em; }
  :is(h1,h2,h3,h4):first-child { margin-top: 0; }
  p { margin: 0 0 .85em; }
  ul, ol { margin: 0 0 .85em; padding-left: 1.35em; }
  li { margin-bottom: .3em; }
  a { color: ${colors.primaryDark}; }
  blockquote {
    margin: 0 0 .85em; padding-left: .9em;
    border-left: 2px solid ${colors.border}; color: ${colors.textSecondary};
  }
  hr { border: 0; border-top: 1px solid ${colors.border}; margin: 1.4em 0; }
  table { width: 100%; border-collapse: collapse; margin: 0 0 .85em; font-size: .95em; }
  :is(th, td) { border: 1px solid ${colors.border}; padding: 7px 9px; text-align: left; }
  th { background: ${colors.surface}; font-weight: 600; }
  img { max-width: 100%; }
</style></head><body>${terms.content}</body></html>`;
  }, [terms, isDocument, colors]);

  /** Saves to the OS downloads/documents area and offers to open it straight away. */
  const download = useCallback(async () => {
    if (!terms?.documentUrl) {
      return;
    }

    setIsDownloading(true);

    try {
      const {dirs} = ReactNativeBlobUtil.fs;
      // iOS has no user-visible Downloads directory; DocumentDir is what the Files app
      // exposes for an app, so that is the equivalent destination.
      const directory = Platform.OS === 'android' ? dirs.DownloadDir : dirs.DocumentDir;
      const name = terms.documentName || 'terms.pdf';
      const path = `${directory}/${name}`;

      const result = await ReactNativeBlobUtil.config({
        path,
        fileCache: true,
        // Registers the file with Android's download manager so it shows in the
        // notification shade and in Files, rather than landing somewhere invisible.
        addAndroidDownloads: {
          useDownloadManager: true,
          notification: true,
          title: name,
          description: projectName ? `Terms — ${projectName}` : 'Project terms',
          mime: 'application/pdf',
          path,
        },
      }).fetch('GET', terms.documentUrl);

      dispatch(showSnackbar({message: `Saved ${name}`, tone: 'success'}));

      if (Platform.OS === 'ios') {
        // iOS gives no download UI of its own, so the share sheet is how the file
        // actually reaches Files, Mail or anywhere else the user wants it.
        await Share.share({url: result.path()});
      }
    } catch (error) {
      dispatch(
        showSnackbar({
          message: 'Could not download the document. Check your connection and try again.',
          tone: 'danger',
        }),
      );
    } finally {
      setIsDownloading(false);
    }
  }, [terms, projectName, dispatch]);

  /** Hands the file to whatever the OS uses for it — the fallback for non-PDF types. */
  const openExternally = useCallback(async () => {
    try {
      await Linking.openURL(terms.documentUrl);
    } catch {
      Alert.alert(
        'Cannot open',
        'No app on this device could open this file, or the link is no longer valid.',
      );
    }
  }, [terms]);

  if (!terms) {
    return (
      <ScreenContainer edges={['top']}>
        <Header title="Terms" onBack={() => navigation.goBack()} />
        <EmptyState
          icon="document-text-outline"
          title="No terms available"
          message="The developer has not published terms for this project yet."
        />
      </ScreenContainer>
    );
  }

  return (
    <ScreenContainer edges={['top']} style={{paddingHorizontal: 0}}>
      <View style={{paddingHorizontal: spacing.lg}}>
        <Header
          title={terms.title}
          subtitle={projectName}
          onBack={() => navigation.goBack()}
        />
      </View>

      <View style={{flex: 1, backgroundColor: colors.background}}>
        {isPdf && !pdfError && (
          <Pdf
            source={{uri: terms.documentUrl, cache: true}}
            trustAllCerts={false}
            onError={error => setPdfError(String(error?.message ?? error))}
            style={{flex: 1, width: '100%', backgroundColor: colors.background}}
            renderActivityIndicator={() => <ActivityIndicator color={colors.primary} size="large" />}
          />
        )}

        {isPdf && pdfError && (
          <EmptyState
            icon="alert-circle-outline"
            title="Could not display the document"
            message="It may still download or open in another app."
          />
        )}

        {/* A DOC/DOCX has no in-app viewer worth shipping, so the screen is a handoff
            rather than a broken preview. */}
        {isDocument && !isPdf && (
          <EmptyState
            icon="document-outline"
            title={terms.documentName ?? 'Document'}
            message="This format opens in another app on your device. You can also download it."
          />
        )}

        {!isDocument && !!html && (
          <WebView
            originWhitelist={['*']}
            source={{html}}
            // The content is sanitised server-side; disabling JS as well means a bypass
            // would still have nothing to execute in.
            javaScriptEnabled={false}
            // Links go to the system browser instead of navigating this view, which has
            // no chrome to get back from.
            onShouldStartLoadWithRequest={request => {
              if (request.url === 'about:blank' || request.url.startsWith('data:')) {
                return true;
              }
              Linking.openURL(request.url).catch(() => {});
              return false;
            }}
            style={{flex: 1, backgroundColor: colors.background}}
            showsVerticalScrollIndicator={false}
          />
        )}
      </View>

      {isDocument && (
        <View
          style={{
            flexDirection: 'row',
            paddingHorizontal: spacing.lg,
            paddingTop: spacing.sm,
            paddingBottom: spacing.md,
            borderTopWidth: 1,
            borderTopColor: colors.border,
            backgroundColor: colors.card,
          }}>
          <View style={{flex: 1, marginRight: spacing.xs}}>
            <Button label="Open" variant="outline" icon="open-outline" onPress={openExternally} />
          </View>
          <View style={{flex: 1, marginLeft: spacing.xs}}>
            <Button
              label={isDownloading ? 'Downloading…' : 'Download'}
              icon="download-outline"
              loading={isDownloading}
              disabled={isDownloading}
              onPress={download}
            />
          </View>
        </View>
      )}
    </ScreenContainer>
  );
};

/** Local to this screen — the terms view is the only place with a title/subtitle bar. */
const Header = ({title, subtitle, onBack}) => {
  const {colors, spacing} = useAppTheme();

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: spacing.sm,
        marginBottom: spacing.sm,
      }}>
      <TouchableOpacity
        activeOpacity={0.7}
        onPress={onBack}
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
      <View style={{flex: 1}}>
        <AppText variant="h3" numberOfLines={1}>
          {title}
        </AppText>
        {!!subtitle && (
          <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
            {subtitle}
          </AppText>
        )}
      </View>
    </View>
  );
};

export default ProjectTermsScreen;
