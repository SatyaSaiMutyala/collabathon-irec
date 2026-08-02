/**
 * @format
 */

import 'react-native-gesture-handler';
import {AppRegistry} from 'react-native';
import App from './App';
import {name as appName} from './app.json';
import {registerBackgroundHandler} from './src/services/push';

// Outside the component tree on purpose: a message arriving while the app is killed runs
// in a headless task with no React around, so the handler has to exist at module scope.
registerBackgroundHandler();

AppRegistry.registerComponent(appName, () => App);
