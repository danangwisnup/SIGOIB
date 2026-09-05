// State machine aplikasi: Activation -> Setup -> Home, atau Revoked.
import React, {useEffect, useState} from 'react';
import {ActivityIndicator, StatusBar, View} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import BackgroundService from 'react-native-background-actions';
import ActivationScreen from './screens/ActivationScreen';
import SetupScreen from './screens/SetupScreen';
import HomeScreen from './screens/HomeScreen';
import RevokedScreen from './screens/RevokedScreen';
import {getDeviceToken, clearDeviceToken} from './storage/secure';
import {initTracking} from './tracking/trackingController';
import {startBackgroundTracking, stopBackgroundTracking} from './background/backgroundTask';
import {getUiState, subscribeUiState} from './services/uiState';

type Screen = 'loading' | 'activation' | 'setup' | 'home' | 'revoked';

export default function App() {
  const [screen, setScreen] = useState<Screen>('loading');

  useEffect(() => {
    void (async () => {
      const token = await getDeviceToken();
      if (!token) {
        setScreen('activation');
        return;
      }
      await startServices();
      const setupDone = await AsyncStorage.getItem('setup_done');
      setScreen(setupDone === '1' ? 'home' : 'setup');
    })();
  }, []);

  // Deteksi revoked dari tracking controller (401/403).
  useEffect(() => {
    return subscribeUiState(s => {
      if (s.state === 'REVOKED') {
        void stopBackgroundTracking();
        setScreen('revoked');
      }
    });
  }, []);

  async function startServices() {
    initTracking();
    if (!BackgroundService.isRunning()) {
      try {
        await startBackgroundTracking();
      } catch {
        // foreground interval tetap berjalan sebagai fallback
      }
    }
  }

  async function handleActivated() {
    await startServices();
    setScreen('setup');
  }

  async function handleSetupDone() {
    await AsyncStorage.setItem('setup_done', '1');
    setScreen('home');
  }

  async function handleReactivate() {
    await clearDeviceToken();
    await AsyncStorage.removeItem('setup_done');
    setScreen('activation');
  }

  if (screen === 'loading') {
    return (
      <View style={{flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#16241a'}}>
        <ActivityIndicator size="large" color="#c9a227" />
      </View>
    );
  }

  return (
    <>
      <StatusBar barStyle={screen === 'home' || screen === 'setup' ? 'dark-content' : 'light-content'} />
      {screen === 'activation' && <ActivationScreen onActivated={handleActivated} />}
      {screen === 'setup' && <SetupScreen onReady={handleSetupDone} />}
      {screen === 'home' && <HomeScreen />}
      {screen === 'revoked' && <RevokedScreen onReactivate={handleReactivate} />}
      {getUiState().state === 'REVOKED' && screen !== 'revoked' && null}
    </>
  );
}
