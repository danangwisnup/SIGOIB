// Layar utama: MONITORING AKTIF / MENUNGGU MONITORING.
// Tanpa tombol start/stop — semua mengikuti status server.
import React, {useEffect, useState} from 'react';
import {StyleSheet, Text, View} from 'react-native';
import {getUiState, subscribeUiState, UiState} from '../services/uiState';
import StatusPill from '../components/StatusPill';

export default function HomeScreen() {
  const [ui, setUi] = useState<UiState>(getUiState());

  useEffect(() => subscribeUiState(setUi), []);

  const tracking = ui.state === 'TRACKING';

  return (
    <View style={styles.container}>
      <Text style={styles.title}>MONITORING IB</Text>
      <View style={styles.statusWrap}>
        <View style={[styles.bigDot, {backgroundColor: tracking ? '#2e7d32' : '#9aa094'}]} />
        <StatusPill
          label={tracking ? 'MONITORING AKTIF' : 'MENUNGGU MONITORING'}
          color={tracking ? 'green' : 'gray'}
        />
        {tracking && ui.sessions.length > 0 && (
          <Text style={styles.sessions}>{ui.sessions.join(', ')}</Text>
        )}
        {!tracking && (
          <Text style={styles.standbyText}>
            Tidak ada monitoring aktif saat ini.{'\n'}Perangkat siap.
          </Text>
        )}
      </View>

      <View style={styles.card}>
        {ui.nrp !== '' && (
          <>
            <Row label="Nama" value={ui.personnelName} />
            <Row label="NRP" value={ui.nrp} />
            {ui.rank !== '' && <Row label="Pangkat" value={ui.rank} />}
          </>
        )}
        <Row label="GPS" value={ui.gpsOk ? '✓' : '✕'} valueColor={ui.gpsOk ? '#2e7d32' : '#c62828'} />
        <Row label="Internet" value={ui.netOk ? '✓' : '✕'} valueColor={ui.netOk ? '#2e7d32' : '#c62828'} />
        <Row label="Battery" value={ui.battery !== null ? `${ui.battery}%` : '-'} />
        <Row label="Last Sync" value={ui.lastSync} />
        {ui.queueCount > 0 && <Row label="Antrian Offline" value={String(ui.queueCount)} valueColor="#8a6d00" />}
      </View>
    </View>
  );
}

function Row({label, value, valueColor}: {label: string; value: string; valueColor?: string}) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={[styles.rowValue, valueColor ? {color: valueColor} : null]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f4f5ef', padding: 24},
  title: {
    fontSize: 17, fontWeight: '800', color: '#16241a',
    textAlign: 'center', marginTop: 20, letterSpacing: 1,
  },
  statusWrap: {alignItems: 'center', marginVertical: 30},
  bigDot: {width: 64, height: 64, borderRadius: 32, marginBottom: 14},
  sessions: {marginTop: 12, fontSize: 15, color: '#23281f', textAlign: 'center'},
  standbyText: {marginTop: 12, fontSize: 14, color: '#6b7263', textAlign: 'center'},
  card: {
    backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e0e4d6',
  },
  row: {
    flexDirection: 'row', justifyContent: 'space-between', padding: 14,
    borderBottomWidth: 1, borderBottomColor: '#f0f2e9',
  },
  rowLabel: {fontSize: 14, color: '#6b7263'},
  rowValue: {fontSize: 14, fontWeight: '700', color: '#23281f'},
});
