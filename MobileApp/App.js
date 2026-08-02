/**
 * Collabathon Mobile App
 * @format
 */

import React from 'react';
import {GestureHandlerRootView} from 'react-native-gesture-handler';
import {Provider as StoreProvider} from 'react-redux';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {store} from './src/store';
import {ThemeProvider} from './src/theme';
import RootNavigator from './src/navigation/RootNavigator';
import {Snackbar} from './src/components';

function App() {
  return (
    <GestureHandlerRootView style={{flex: 1}}>
      <StoreProvider store={store}>
        <ThemeProvider>
          <SafeAreaProvider>
            <RootNavigator />
            {/* Above the navigator, so it floats over whatever screen is mounted. */}
            <Snackbar />
          </SafeAreaProvider>
        </ThemeProvider>
      </StoreProvider>
    </GestureHandlerRootView>
  );
}

export default App;
