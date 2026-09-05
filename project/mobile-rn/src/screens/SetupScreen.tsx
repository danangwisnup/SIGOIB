// PENGATURAN PERANGKAT (one-time): checklist permission + test perangkat.
import React, {useState} from 'react';
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import {
  checkPermissions,
  requestAllPermissions,
  PermissionChecklist,
} from '../permissions/permissions';
import {api} from '../api/client';
import {getDeviceToken} from '../storage/secure';

type TestState = {[k: string]: boolean | null};

export default function SetupScreen({onReady}: {onReady: () => void}) {
  const [perms, setPerms] = useState<PermissionChecklist | null>(null);
  const [test, setTest] = useState<TestState>({GPS: null, Internet: null, Server: null, Permission: null});
  const [testing, setTesting] = useState(false);
  const [done, setDone] = useState(false);

  async function refresh() {
    setPerms(await checkPermissions());
  }

  async function requestAll() {
    setPerms(await requestAllPermissions());
  }

  async function runTest() {
    setTesting(true);
    const p = await requestAllPermissions();
    const t: TestState = {Permission: p.location, GPS: p.gps, Internet: null, Server: null};
    try {
      const token = await getDeviceToken();
      if (token) {
        await api.statusByToken(token);
        t.Internet = true;
        t.Server = true;
      }
    } catch (e) {
      const err = e as {status?: number};
      t.Internet = err.status !== 0; // server merespons tapi token bermasalah
      t.Server = err.status !== 0 && (err.status ?? 0) < 500;
    }
    setPerms(p);
    setTest(t);
    setTesting(false);
    setDone(!!(t.Permission && t.GPS && t.Internet && t.Server));
  }

  const rows: [string, boolean | null][] = [
    ['Location', perms?.location ?? null],
    ['Background Location', perms?.backgroundLocation ?? null],
    ['Notification', perms?.notification ?? null],
    ['GPS', test.GPS],
    ['Connection', test.Internet && test.Server],
  ];

  return (
    <View style={styles.container}>
      <Text style={styles.title}>PENGATURAN PERANGKAT</Text>
      <View style={styles.card}>
        {rows.map(([label, val]) => (
          <View key={label} style={styles.row}>
            <Text style={styles.rowLabel}>{label}</Text>
            <Text style={[styles.rowIcon, {color: val ? '#2e7d32' : val === false ? '#c62828' : '#9aa094'}]}>
              {val ? '✓' : val === false ? '✕' : '–'}
            </Text>
          </View>
        ))}
      </View>
      <TouchableOpacity style={styles.btnOutline} onPress={requestAll} testID="permission-btn">
        <Text style={styles.btnOutlineText}>IZINKAN SEMUA</Text>
      </TouchableOpacity>
      <TouchableOpacity style={styles.btn} onPress={runTest} disabled={testing} testID="test-btn">
        {testing ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.btnText}>TEST PERANGKAT</Text>
        )}
      </TouchableOpacity>
      {done && (
        <>
          <Text style={styles.ready}>PERANGKAT SIAP</Text>
          <TouchableOpacity style={styles.btn} onPress={onReady} testID="go-home-btn">
            <Text style={styles.btnText}>MASUK</Text>
          </TouchableOpacity>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f4f5ef', padding: 24},
  title: {fontSize: 19, fontWeight: '800', color: '#16241a', marginBottom: 18, marginTop: 24},
  card: {
    backgroundColor: '#fff', borderRadius: 12, padding: 8, marginBottom: 16,
    borderWidth: 1, borderColor: '#e0e4d6',
  },
  row: {
    flexDirection: 'row', justifyContent: 'space-between', padding: 13,
    borderBottomWidth: 1, borderBottomColor: '#f0f2e9',
  },
  rowLabel: {fontSize: 15, color: '#23281f'},
  rowIcon: {fontSize: 16, fontWeight: '800'},
  btn: {backgroundColor: '#3f5233', borderRadius: 10, padding: 15, alignItems: 'center', marginTop: 10},
  btnText: {color: '#fff', fontWeight: '700'},
  btnOutline: {
    borderWidth: 1, borderColor: '#3f5233', borderRadius: 10, padding: 14, alignItems: 'center',
  },
  btnOutlineText: {color: '#3f5233', fontWeight: '700'},
  ready: {
    color: '#2e7d32', fontWeight: '800', fontSize: 17, textAlign: 'center', marginTop: 20,
  },
});
