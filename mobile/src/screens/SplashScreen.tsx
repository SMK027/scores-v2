import { useEffect, useMemo, useRef } from "react";
import { Animated, StyleSheet, Text, View } from "react-native";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";

export function SplashScreen() {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const scale = useRef(new Animated.Value(0.9)).current;
  const opacity = useRef(new Animated.Value(0.7)).current;

  useEffect(() => {
    const pulse = Animated.loop(
      Animated.sequence([
        Animated.parallel([
          Animated.timing(scale, {
            toValue: 1,
            duration: 700,
            useNativeDriver: true,
          }),
          Animated.timing(opacity, {
            toValue: 1,
            duration: 700,
            useNativeDriver: true,
          }),
        ]),
        Animated.parallel([
          Animated.timing(scale, {
            toValue: 0.94,
            duration: 700,
            useNativeDriver: true,
          }),
          Animated.timing(opacity, {
            toValue: 0.85,
            duration: 700,
            useNativeDriver: true,
          }),
        ]),
      ])
    );

    pulse.start();
    return () => pulse.stop();
  }, [opacity, scale]);

  return (
    <View style={styles.container}>
      <View style={styles.logoWrap}>
        <View style={styles.logoPulse} />
        <Animated.View style={[styles.logoCircle, { opacity, transform: [{ scale }] }]}>
          <Text style={styles.logoGlyph}>#</Text>
        </Animated.View>
      </View>
      <Text style={styles.title}>Scores</Text>
      <Text style={styles.subtitle}>Préparation de votre espace de jeu...</Text>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  container: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: theme.colors.background,
    gap: 16,
  },
  logoWrap: {
    position: "relative",
    alignItems: "center",
    justifyContent: "center",
  },
  logoPulse: {
    position: "absolute",
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: theme.colors.brandSoft,
    zIndex: -1,
  },
  logoCircle: {
    width: 96,
    height: 96,
    borderRadius: 48,
    backgroundColor: theme.colors.brand,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: theme.colors.brand,
    shadowOpacity: 0.3,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 8 },
    elevation: 10,
  },
  logoGlyph: {
    color: "#ffffff",
    fontSize: 44,
    fontWeight: theme.font.weight.heavy,
  },
  title: {
    fontSize: theme.font.size.xxxl,
    color: theme.colors.text,
    fontWeight: theme.font.weight.heavy,
    letterSpacing: -0.5,
  },
  subtitle: {
    color: theme.colors.mutedText,
    fontSize: theme.font.size.md,
  },
});
