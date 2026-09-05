// AKTIVASI PERANGKAT: NRP -> server cek -> PENDING -> poll -> ACTIVE.
// Reinstall di perangkat sama: server mengenali hardware ID -> langsung ACTIVE.
import React, {useEffect, useRef, useState} from 'react';
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import {registerWithNrp, pollStatusByUuid} from '../device/registration';
import {saveDeviceToken} from '../storage/secure';
import {ApiError} from '../types';

type Phase = 'input' | 'loading' | 'pending' | 'error';

export default function ActivationScreen({onActivated}: {onActivated: () => void}) {
  const [nrp, setNrp] = useState('');
  const [phase, setPhase] = useState<Phase>('input');
  const [message, setMessage] = useState('');
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    return () => {
      if (pollRef.current) {
        clearInterval(pollRef.current);
      }
    };
  }, []);

  async function handleResult(status: string, msg?: string, token?: string) {
    if (status === 'PENDING') {
      setPhase('pending');
      setMessage(msg || 'Menunggu persetujuan admin.');
      if (!pollRef.current) {
        pollRef.current = setInterval(checkStatus, 15000);
      }
    } else if (status === 'ACTIVE' && token) {
      await saveDeviceToken(token);
      if (pollRef.current) {
        clearInterval(pollRef.current);
      }
      onActivated();
    } else if (status === 'REVOKED') {
      setPhase('error');
      setMessage(msg || 'Perangkat ini sudah tidak aktif. Hubungi admin.');
    }
  }

  async function checkStatus() {
    try {
      const s = await pollStatusByUuid();
      await handleResult(s.device_status, s.message, s.device_token);
    } catch {
      // polling best-effort
    }
  }

  async function submit() {
    if (!nrp.trim()) {
      return;
    }
    setPhase('loading');
    setMessage('');
    try {
      const res = await registerWithNrp(nrp.trim());
      await handleResult(res.device_status, res.message, res.device_token);
      if (res.device_status !== 'PENDING' && res.device_status !== 'ACTIVE') {
        setPhase('error');
      }
    } catch (e) {
      setPhase('error');
      setMessage((e as ApiError).message || 'Tidak dapat terhubung ke server.');
    }
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>AKTIVASI PERANGKAT</Text>
      <Text style={styles.subtitle}>
        Masukkan NRP Anda untuk mendaftarkan perangkat ini.
      </Text>

      {phase === 'pending' ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#c9a227" />
          <Text style={styles.pendingText}>{message}</Text>
          <Text style={styles.hint}>
            Halaman ini otomatis lanjut setelah admin menyetujui.
          </Text>
        </View>
      ) : (
        <>
          <TextInput
            style={styles.input}
            placeholder="NRP"
            placeholderTextColor="#9aa094"
            keyboardType="number-pad"
            value={nrp}
            onChangeText={setNrp}
            editable={phase !== 'loading'}
            testID="nrp-input"
          />
          <TouchableOpacity
            style={[styles.button, phase === 'loading' && styles.buttonDisabled]}
            onPress={submit}
            disabled={phase === 'loading'}
            testID="lanjut-btn">
            {phase === 'loading' ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.buttonText}>LANJUT</Text>
            )}
          </TouchableOpacity>
          {phase === 'error' && message !== '' && (
            <Text style={styles.error} testID="activation-error">{message}</Text>
          )}
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#16241a', padding: 28, justifyContent: 'center'},
  title: {color: '#fff', fontSize: 22, fontWeight: '800', textAlign: 'center'},
  subtitle: {color: '#93a08c', textAlign: 'center', marginTop: 8, marginBottom: 32},
  input: {
    backgroundColor: '#fff', borderRadius: 10, padding: 14, fontSize: 17,
    color: '#23281f', marginBottom: 14,
  },
  button: {
    backgroundColor: '#3f5233', borderRadius: 10, padding: 16, alignItems: 'center',
  },
  buttonDisabled: {opacity: 0.6},
  buttonText: {color: '#fff', fontWeight: '700', fontSize: 15, letterSpacing: 1},
  error: {color: '#ff9a93', textAlign: 'center', marginTop: 16},
  center: {alignItems: 'center'},
  pendingText: {color: '#fff', fontSize: 16, marginTop: 16, textAlign: 'center'},
  hint: {color: '#93a08c', fontSize: 12, marginTop: 8, textAlign: 'center'},
});
