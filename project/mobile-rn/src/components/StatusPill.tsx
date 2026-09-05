import React from 'react';
import {Text, StyleSheet, View} from 'react-native';

const COLORS: Record<string, {bg: string; fg: string}> = {
  green: {bg: '#e3f1e4', fg: '#2e7d32'},
  yellow: {bg: '#f7f0d0', fg: '#8a6d00'},
  red: {bg: '#fae3e1', fg: '#c62828'},
  gray: {bg: '#eceee6', fg: '#5c6255'},
};

export default function StatusPill({
  label,
  color,
}: {
  label: string;
  color: 'green' | 'yellow' | 'red' | 'gray';
}) {
  const c = COLORS[color];
  return (
    <View style={[styles.pill, {backgroundColor: c.bg}]}>
      <Text style={[styles.text, {color: c.fg}]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 6,
    alignSelf: 'center',
  },
  text: {fontWeight: '700', fontSize: 13, letterSpacing: 0.5},
});
