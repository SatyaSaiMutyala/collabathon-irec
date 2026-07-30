import React, {createContext, useContext, useEffect, useMemo, useState} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {buildPalette, DEFAULT_PRIMARY, withAlpha} from './palette';
import {fontFamily, fontWeight, textVariants} from './typography';
import {avatarSize, iconSize, radius, spacing} from './metrics';

const THEME_STORAGE_KEY = '@collabathon/theme-primary-color';

const ThemeContext = createContext(undefined);

export const ThemeProvider = ({children}) => {
  const [primaryColor, setPrimaryColorState] = useState(DEFAULT_PRIMARY);
  const [isThemeLoaded, setIsThemeLoaded] = useState(false);

  useEffect(() => {
    AsyncStorage.getItem(THEME_STORAGE_KEY)
      .then(stored => {
        if (stored) {
          setPrimaryColorState(stored);
        }
      })
      .finally(() => setIsThemeLoaded(true));
  }, []);

  const setPrimaryColor = hex => {
    setPrimaryColorState(hex);
    AsyncStorage.setItem(THEME_STORAGE_KEY, hex).catch(() => {});
  };

  const colors = useMemo(() => buildPalette(primaryColor), [primaryColor]);

  const value = useMemo(
    () => ({
      colors,
      fontFamily,
      fontWeight,
      textVariants,
      spacing,
      radius,
      iconSize,
      avatarSize,
      withAlpha,
      setPrimaryColor,
      primaryColor,
      isThemeLoaded,
    }),
    [colors, primaryColor, isThemeLoaded],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
};

export function useAppTheme() {
  const ctx = useContext(ThemeContext);
  if (!ctx) {
    throw new Error('useAppTheme must be used within a ThemeProvider');
  }
  return ctx;
}
