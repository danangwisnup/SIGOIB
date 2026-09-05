// MENUNGGU PERSETUJUAN: token ada tetapi server melaporkan status PENDING.
// Polling ringan ke server (source of truth). Tidak menjalankan tracking.
import React, {useEffect, useRef} from 'react';
import {ActivityIndicator, StyleSheet, Text, View} from 'react-native';
import {api} from '../api/client';
import {getDeviceToken} from '../storage/secure';
import {ApiError} from '../types';

export default function PendingScreen({
  onActive,
  onRevoked,
}: {
  onActive: () => void;
  onRevoked: () => void;
}) {
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    const check = async () => {
      try {
        const token = await getDeviceToken();
        if (!token) {
          return;
        }
        const s = await api.statusByToken(token);
        if (s.device_status === 'ACTIVE') {
          onActive();
        } else if (s.device_status === 'REVOKED') {
          onRevoked();
        }
      } catch (e) {
        const err = e as ApiError;
        if (err.status === 401 || err.status === 403) {
          onRevoked();
        }
        // offline / error lain: tetap menunggu, coba lagi tick berikutnya
      }
    };
    timer.current = setInterval(check, 15000);
    void check();
    return () => {
      if (timer.current) {
        clearInterval(timer.current);
      }
    };
  }, [onActive, onRevoked]);

  return (
    <View style={styles.container}>
      <ActivityIndicator size="large" color="#c9a227" />
      <Text style={styles.title}>MENUNGGU PERSETUJUAN</Text>
      <Text style={styles.text}>
        Perangkat Anda menunggu persetujuan admin.{'\n'}Halaman ini otomatis lanjut setelah disetujui.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#16241a', alignItems: 'center', justifyContent: 'center', padding: 28},
  title: {color: '#fff', fontSize: 18, fontWeight: '800', marginTop: 18},
  text: {color: '#93a08c', marginTop: 10, textAlign: 'center', lineHeight: 20},
});
