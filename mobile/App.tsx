import { StatusBar } from "expo-status-bar";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { MobileApp } from "./src/MobileApp";

export default function App() {
  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <MobileApp />
    </SafeAreaProvider>
  );
}
