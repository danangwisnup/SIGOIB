// PERANGKAT DICABUT: token direvoke server -> tracking berhenti total.
import React from 'react';
import {StyleSheet, Text, TouchableOpacity, View} from 'react-native';

export default function RevokedScreen({onReactivate}: {onReactivate: () => void}) {
  return (
    <View style={styles.container}>
      <Text style={styles.icon}>⛔</Text>
      <Text style={styles.title}>PERANGKAT DICABUT</Text>
      <Text style={styles.text}>Hubungi admin untuk aktivasi ulang.</Text>
      <TouchableOpacity style={styles.btn} onPress={onReactivate} testID="reactivate-btn">
        <Text style={styles.btnText}>AKTIVASI ULANG</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#16241a', alignItems: 'center', justifyContent: 'center', padding: 28},
  icon: {fontSize: 52},
  title: {color: '#ff9a93', fontSize: 20, fontWeight: '800', marginTop: 16},
  text: {color: '#93a08c', marginTop: 8, textAlign: 'center'},
  btn: {marginTop: 28, backgroundColor: '#3f5233', borderRadius: 10, padding: 15, paddingHorizontal: 32},
  btnText: {color: '#fff', fontWeight: '700'},
});
