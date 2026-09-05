// State machine aplikasi (SERVER = SOURCE OF TRUTH untuk routing awal):
//
//   LOADING
//     -> token? tidak  -> ACTIVATION
//     -> token? ya     -> initTracking() (poll ringan, TANPA start FGS)
//                         -> tanya status ke server:
//                              REVOKED -> REVOKED (stop tracking, tidak crash)
//                              PENDING -> PENDING (menunggu approval)
//                              ACTIVE + setup belum -> SETUP
//                              ACTIVE + setup selesai -> HOME
//                         -> server offline/error -> state lokal aman (HOME/SETUP), TIDAK crash;
//                            loop tracking akan mengoreksi (mis. REVOKED) saat server kembali.
//
// FGS (foreground service) TIDAK pernah distart di startup; hanya oleh trackingController
// saat tracking_required=true. Ini memutus crash-loop force close setelah approval / reopen.
import React, {useEffect, useState} from 'react';
import {ActivityIndicator, StatusBar, View} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import ActivationScreen from './screens/ActivationScreen';
import SetupScreen from './screens/SetupScreen';
import HomeScreen from './screens/HomeScreen';
import RevokedScreen from './screens/RevokedScreen';
import PendingScreen from './screens/PendingScreen';
import {getDeviceToken} from './storage/secure';
import {initTracking, stopTracking, clearForReactivation} from './tracking/trackingController';
import {stopBackgroundTracking} from './background/backgroundTask';
import {subscribeUiState} from './services/uiState';
import {api} from './api/client';
import {ApiError} from './types';

type Screen = 'loading' | 'activation' | 'pending' | 'setup' | 'home' | 'revoked';

export default function App() {
  const [screen, setScreen] = useState<Screen>('loading');

  useEffect(() => {
    void bootstrap();
    // Revoked terdeteksi runtime (mis. saat offline di boot lalu server kembali & menolak).
    const unsub = subscribeUiState(s => {
      if (s.state === 'REVOKED') {
        setScreen('revoked');
      }
    });
    return unsub;
  }, []);

  async function bootstrap() {
    let token: string | null = null;
    try {
      token = await getDeviceToken();
    } catch {
      token = null;
    }
    if (!token) {
      setScreen('activation');
      return;
    }

    // Punya token: mulai loop tracking (poll ringan; FGS distart hanya bila perlu).
    safeInitTracking();

    // Routing awal berdasarkan status server (source of truth).
    try {
      const status = await api.statusByToken(token);
      await routeByServer(status.device_status);
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 401 || err.status === 403) {
        await enterRevoked();
        return;
      }
      // Offline / server error lain: gunakan state lokal yang aman, JANGAN crash.
      const setupDone = await safeGetSetupDone();
      setScreen(setupDone ? 'home' : 'setup');
    }
  }

  async function routeByServer(deviceStatus: string) {
    if (deviceStatus === 'REVOKED') {
      await enterRevoked();
      return;
    }
    if (deviceStatus === 'PENDING') {
      setScreen('pending');
      return;
    }
    // ACTIVE
    const setupDone = await safeGetSetupDone();
    setScreen(setupDone ? 'home' : 'setup');
  }

  function safeInitTracking() {
    try {
      initTracking();
    } catch {
      // kegagalan init tidak boleh memblokir UI
    }
  }

  async function enterRevoked() {
    try {
      await stopTracking();
    } catch {
      // abaikan
    }
    try {
      await stopBackgroundTracking();
    } catch {
      // abaikan
    }
    setScreen('revoked');
  }

  async function safeGetSetupDone(): Promise<boolean> {
    try {
      return (await AsyncStorage.getItem('setup_done')) === '1';
    } catch {
      return false;
    }
  }

  async function handleActivated() {
    safeInitTracking();
    setScreen('setup');
  }

  async function handleSetupDone() {
    try {
      await AsyncStorage.setItem('setup_done', '1');
    } catch {
      // abaikan
    }
    setScreen('home');
  }

  async function handleReactivate() {
    try {
      await clearForReactivation();
    } catch {
      // abaikan
    }
    try {
      await AsyncStorage.removeItem('setup_done');
    } catch {
      // abaikan
    }
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
      {screen === 'pending' && (
        <PendingScreen onActive={() => void routeByServer('ACTIVE')} onRevoked={() => void enterRevoked()} />
      )}
      {screen === 'setup' && <SetupScreen onReady={handleSetupDone} />}
      {screen === 'home' && <HomeScreen />}
      {screen === 'revoked' && <RevokedScreen onReactivate={handleReactivate} />}
    </>
  );
}
