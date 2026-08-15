import React, {useEffect, useRef, useState} from 'react';
import {Animated, Modal, Pressable, StyleSheet, useWindowDimensions, View} from 'react-native';
import {SafeAreaProvider, SafeAreaView} from 'react-native-safe-area-context';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';

/** Slide-in-from-right panel (Modal + Animated.View, since RN's built-in Modal slide is vertical-only). */
const RightDrawer = ({visible, onClose, children}) => {
  const {colors, radius} = useAppTheme();
  // useWindowDimensions, not a module-level Dimensions.get(): that reads the window
  // once at import and never again, so on a device that rotated — or an iPad window
  // the user resized — the drawer kept the width of the orientation the app happened
  // to launch in. The hook re-renders on every window change. The 300pt ceiling still
  // does the real work on a tablet, where 80% of the width would be a drawer wider
  // than a whole phone.
  const {width: windowWidth} = useWindowDimensions();
  const drawerWidth = Math.min(windowWidth * 0.8, moderateScale(300));

  const translateX = useRef(new Animated.Value(drawerWidth)).current;
  const [isMounted, setIsMounted] = useState(visible);

  useEffect(() => {
    if (visible) {
      setIsMounted(true);
      Animated.timing(translateX, {toValue: 0, duration: 240, useNativeDriver: true}).start();
    } else {
      // Also re-runs when drawerWidth changes while closed, which re-parks the panel
      // fully off-screen at the new width instead of leaving a sliver of it showing.
      Animated.timing(translateX, {toValue: drawerWidth, duration: 200, useNativeDriver: true}).start(
        ({finished}) => finished && setIsMounted(false),
      );
    }
  }, [visible, translateX, drawerWidth]);

  if (!isMounted) {
    return null;
  }

  return (
    <Modal visible transparent animationType="none" onRequestClose={onClose} statusBarTranslucent>
      <SafeAreaProvider>
        <View style={styles.row}>
          <Pressable style={styles.backdrop} onPress={onClose} />
          <Animated.View
            style={[
              styles.drawer,
              {
                width: drawerWidth,
                backgroundColor: colors.card,
                borderTopLeftRadius: radius.lg,
                borderBottomLeftRadius: radius.lg,
                transform: [{translateX}],
              },
            ]}>
            <SafeAreaView
              edges={['top', 'bottom']}
              style={[styles.safeArea, {backgroundColor: colors.card}]}>
              {children}
            </SafeAreaView>
          </Animated.View>
        </View>
      </SafeAreaProvider>
    </Modal>
  );
};

const styles = StyleSheet.create({
  row: {
    flex: 1,
    flexDirection: 'row',
  },
  backdrop: {
    flex: 1,
  },
  drawer: {
    shadowColor: '#000',
    shadowOffset: {width: -3, height: 0},
    shadowOpacity: 0.18,
    shadowRadius: 16,
    elevation: 16,
  },
  safeArea: {
    flex: 1,
  },
});

export default RightDrawer;
