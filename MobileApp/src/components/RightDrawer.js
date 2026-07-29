import React, {useEffect, useRef, useState} from 'react';
import {Animated, Dimensions, Modal, Pressable, StyleSheet, View} from 'react-native';
import {SafeAreaProvider, SafeAreaView} from 'react-native-safe-area-context';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';

const SCREEN_WIDTH = Dimensions.get('window').width;
const DRAWER_WIDTH = Math.min(SCREEN_WIDTH * 0.8, moderateScale(300));

/** Slide-in-from-right panel (Modal + Animated.View, since RN's built-in Modal slide is vertical-only). */
const RightDrawer = ({visible, onClose, children}) => {
  const {colors, radius} = useAppTheme();
  const translateX = useRef(new Animated.Value(DRAWER_WIDTH)).current;
  const [isMounted, setIsMounted] = useState(visible);

  useEffect(() => {
    if (visible) {
      setIsMounted(true);
      Animated.timing(translateX, {toValue: 0, duration: 240, useNativeDriver: true}).start();
    } else {
      Animated.timing(translateX, {toValue: DRAWER_WIDTH, duration: 200, useNativeDriver: true}).start(
        ({finished}) => finished && setIsMounted(false),
      );
    }
  }, [visible, translateX]);

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
                width: DRAWER_WIDTH,
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
