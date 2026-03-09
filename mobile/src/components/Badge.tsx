import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { COLORS } from '../utils/config';

interface Props {
  label: string;
  color?: string;
}

export default function Badge({ label, color = COLORS.primary }: Props) {
  return (
    <View style={[styles.badge, { backgroundColor: color }]}>
      <Text style={styles.text}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  text: {
    color: COLORS.white,
    fontSize: 12,
    fontWeight: '600',
  },
});
