import AsyncStorage from '@react-native-async-storage/async-storage';

const AUTH_STORAGE_KEY = '@collabathon/auth-session';

export async function saveAuthState(auth) {
  try {
    await AsyncStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(auth));
  } catch {
    // Persistence is a nice-to-have; a failed write shouldn't break the session.
  }
}

export async function loadAuthState() {
  try {
    const raw = await AsyncStorage.getItem(AUTH_STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}
