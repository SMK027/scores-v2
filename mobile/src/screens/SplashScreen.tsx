import { useEffect, useRef } from "react";
import { Animated, StyleSheet, Text, View } from "react-native";
import { theme } from "../styles/theme";

export function SplashScreen() {
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
      <Animated.View style={[styles.logoCircle, { opacity, transform: [{ scale }] }]}>
        <Text style={styles.logoGlyph}>#</Text>
      </Animated.View>
      <Text style={styles.title}>Scores</Text>
      <Text style={styles.subtitle}>Préparation de votre espace de jeu...</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#f7f9ff",
  },
  logoCircle: {
    width: 92,
    height: 92,
    borderRadius: 46,
    backgroundColor: "#635bff",
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#312e81",
    shadowOpacity: 0.2,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 8 },
    elevation: 8,
  },
  logoGlyph: {
    color: "#ffffff",
    fontSize: 42,
    fontWeight: "900",
  },
  title: {
    marginTop: 20,
    fontSize: 30,
    color: theme.colors.text,
    fontWeight: "800",
  },
  subtitle: {
    marginTop: 8,
    color: theme.colors.mutedText,
    fontSize: 14,
  },
});
