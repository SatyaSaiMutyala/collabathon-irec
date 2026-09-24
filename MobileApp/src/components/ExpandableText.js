import React, {useCallback, useState} from 'react';
import {TouchableOpacity, View} from 'react-native';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * Long copy (a developer's "About", a project note) collapsed to a fixed number of
 * lines with a "Read more" toggle — instead of either truncating silently or pushing a
 * multi-paragraph block to the top of every card. Measures its own overflow via
 * `onTextLayout` rather than a caller-supplied flag, so the toggle only ever appears
 * when the text actually clips at `numberOfLines`; short copy renders with no control
 * at all.
 */
const ExpandableText = ({children, numberOfLines = 3, variant = 'body', color}) => {
  const {colors, spacing} = useAppTheme();
  const [expanded, setExpanded] = useState(false);
  // Undetermined until the first layout pass reports how many lines the full text
  // actually needs — starts false so the toggle never flashes on text that fits.
  const [canExpand, setCanExpand] = useState(false);

  const handleTextLayout = useCallback(
    e => {
      if (!expanded && e.nativeEvent.lines.length > numberOfLines) {
        setCanExpand(true);
      }
    },
    [expanded, numberOfLines],
  );

  if (!children) {
    return null;
  }

  return (
    <View>
      <AppText
        variant={variant}
        color={color ?? colors.textSecondary}
        numberOfLines={expanded ? undefined : numberOfLines}
        onTextLayout={handleTextLayout}>
        {children}
      </AppText>
      {canExpand && (
        <TouchableOpacity
          hitSlop={8}
          onPress={() => setExpanded(prev => !prev)}
          style={{marginTop: spacing.xxs}}>
          <AppText variant="captionMedium" color={colors.primary}>
            {expanded ? 'Read less' : 'Read more'}
          </AppText>
        </TouchableOpacity>
      )}
    </View>
  );
};

export default ExpandableText;
