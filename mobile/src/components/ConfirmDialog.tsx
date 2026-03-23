import { Modal, Pressable, StyleSheet, Text, View } from "react-native";
import { useMemo } from "react";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";

type Props = {
  visible: boolean;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  destructive?: boolean;
  onCancel: () => void;
  onConfirm: () => void;
};

export function ConfirmDialog({
  visible,
  title,
  message,
  confirmText = "Confirmer",
  cancelText = "Annuler",
  destructive = false,
  onCancel,
  onConfirm,
}: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCancel}>
      <View style={styles.overlay}>
        <View style={styles.card}>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.message}>{message}</Text>

          <View style={styles.actionsRow}>
            <Pressable style={styles.cancelButton} onPress={onCancel}>
              <Text style={styles.cancelText}>{cancelText}</Text>
            </Pressable>

            <Pressable
              style={[styles.confirmButton, destructive ? styles.confirmButtonDanger : undefined]}
              onPress={onConfirm}
            >
              <Text style={styles.confirmText}>{confirmText}</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const createStyles = (theme: AppTheme) =>
  StyleSheet.create({
    overlay: {
      flex: 1,
      backgroundColor: "rgba(0,0,0,0.45)",
      alignItems: "center",
      justifyContent: "center",
      paddingHorizontal: 18,
    },
    card: {
      width: "100%",
      maxWidth: 420,
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.lg,
      padding: 16,
      ...theme.shadow.card,
    },
    title: {
      color: theme.colors.text,
      fontSize: 16,
      fontWeight: "800",
      marginBottom: 8,
    },
    message: {
      color: theme.colors.mutedText,
      fontSize: 14,
      lineHeight: 20,
    },
    actionsRow: {
      flexDirection: "row",
      justifyContent: "flex-end",
      gap: 10,
      marginTop: 16,
    },
    cancelButton: {
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: theme.radius.md,
      backgroundColor: theme.colors.background,
      paddingHorizontal: 14,
      paddingVertical: 10,
    },
    cancelText: {
      color: theme.colors.mutedText,
      fontWeight: "700",
    },
    confirmButton: {
      borderWidth: 1,
      borderColor: theme.colors.primary,
      borderRadius: theme.radius.md,
      backgroundColor: theme.colors.primary,
      paddingHorizontal: 14,
      paddingVertical: 10,
    },
    confirmButtonDanger: {
      borderColor: theme.colors.danger,
      backgroundColor: theme.colors.danger,
    },
    confirmText: {
      color: "#fff",
      fontWeight: "700",
    },
  });
