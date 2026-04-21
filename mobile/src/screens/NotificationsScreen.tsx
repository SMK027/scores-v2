import { useCallback, useMemo } from "react";
import {
  ActivityIndicator,
  FlatList,
  Linking,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useAppTheme } from "../context/ThemeContext";
import { useNotifications } from "../context/NotificationContext";
import type { AppTheme } from "../styles";
import type { NotificationItem } from "../types/api";

type Props = {
  onBack: () => void;
};

function getTypeIcon(type: string): string {
  switch (type) {
    case "lobby_join":
      return "🎮";
    case "lobby_invite":
    case "space_invite":
      return "📨";
    case "lobby_invite_accepted":
    case "space_invite_accepted":
      return "✅";
    case "space_invite_declined":
      return "❌";
    case "lobby_launch":
      return "🚀";
    default:
      return "🔔";
  }
}

function formatRelativeDate(dateStr: string): string {
  const now = Date.now();
  const then = new Date(dateStr).getTime();
  const diff = Math.floor((now - then) / 1000);

  if (diff < 60) return "À l'instant";
  if (diff < 3600) return `Il y a ${Math.floor(diff / 60)} min`;
  if (diff < 86400) return `Il y a ${Math.floor(diff / 3600)} h`;
  if (diff < 604800) return `Il y a ${Math.floor(diff / 86400)} j`;
  return new Date(dateStr).toLocaleDateString("fr-FR", { day: "2-digit", month: "short" });
}

export function NotificationsScreen({ onBack }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);
  const { notifications, unreadCount, markRead, markAllRead, refresh } = useNotifications();

  const handlePressItem = useCallback(
    async (item: NotificationItem) => {
      if (!item.is_read) {
        await markRead(item.id);
      }
      if (item.url) {
        await Linking.openURL(item.url);
      }
    },
    [markRead]
  );

  const renderItem = useCallback(
    ({ item }: { item: NotificationItem }) => {
      const unread = !item.is_read;
      return (
        <Pressable
          style={[styles.item, unread ? styles.itemUnread : undefined]}
          onPress={() => void handlePressItem(item)}
        >
          <View style={styles.itemIconContainer}>
            <Text style={styles.itemIcon}>{getTypeIcon(item.type)}</Text>
            {unread ? <View style={styles.unreadDot} /> : null}
          </View>
          <View style={styles.itemContent}>
            <Text style={[styles.itemTitle, unread ? styles.itemTitleUnread : undefined]}>
              {item.title}
            </Text>
            <Text style={styles.itemMessage} numberOfLines={2}>
              {item.message}
            </Text>
            <Text style={styles.itemDate}>{formatRelativeDate(item.created_at)}</Text>
          </View>
        </Pressable>
      );
    },
    [styles, handlePressItem]
  );

  return (
    <View style={styles.container}>
      {/* En-tête */}
      <View style={styles.header}>
        <Pressable style={styles.backButton} onPress={onBack}>
          <Text style={styles.backText}>← Retour</Text>
        </Pressable>
        <Text style={styles.headerTitle}>
          Notifications{unreadCount > 0 ? ` (${unreadCount})` : ""}
        </Text>
        {unreadCount > 0 ? (
          <Pressable style={styles.markAllBtn} onPress={() => void markAllRead()}>
            <Text style={styles.markAllText}>Tout lire</Text>
          </Pressable>
        ) : (
          <View style={styles.headerSpacer} />
        )}
      </View>

      {notifications.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyIcon}>🔔</Text>
          <Text style={styles.emptyText}>Aucune notification</Text>
          <Text style={styles.emptySubtext}>
            Vous serez notifié des invitations et activités importantes.
          </Text>
        </View>
      ) : (
        <FlatList
          data={notifications}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderItem}
          onRefresh={() => void refresh()}
          refreshing={false}
          contentContainerStyle={styles.listContent}
          ItemSeparatorComponent={() => <View style={styles.separator} />}
        />
      )}
    </View>
  );
}

const createStyles = (theme: AppTheme) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.colors.background,
    },
    header: {
      flexDirection: "row",
      alignItems: "center",
      paddingHorizontal: 16,
      paddingVertical: 14,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.border,
      backgroundColor: theme.colors.card,
    },
    backButton: {
      paddingVertical: 4,
      paddingRight: 12,
    },
    backText: {
      color: theme.colors.primary,
      fontWeight: "700",
      fontSize: 14,
    },
    headerTitle: {
      flex: 1,
      color: theme.colors.text,
      fontWeight: "800",
      fontSize: 16,
      textAlign: "center",
    },
    markAllBtn: {
      paddingVertical: 4,
      paddingLeft: 12,
    },
    markAllText: {
      color: theme.colors.primary,
      fontWeight: "700",
      fontSize: 13,
    },
    headerSpacer: {
      width: 60,
    },
    listContent: {
      paddingBottom: 24,
    },
    item: {
      flexDirection: "row",
      paddingVertical: 14,
      paddingHorizontal: 16,
      backgroundColor: theme.colors.background,
    },
    itemUnread: {
      backgroundColor: theme.colors.primarySoft,
    },
    itemIconContainer: {
      width: 42,
      alignItems: "center",
      paddingTop: 2,
      position: "relative",
    },
    itemIcon: {
      fontSize: 22,
    },
    unreadDot: {
      position: "absolute",
      top: 0,
      right: 6,
      width: 8,
      height: 8,
      borderRadius: 4,
      backgroundColor: theme.colors.primary,
    },
    itemContent: {
      flex: 1,
      paddingLeft: 4,
    },
    itemTitle: {
      fontSize: 14,
      fontWeight: "600",
      color: theme.colors.text,
      marginBottom: 2,
    },
    itemTitleUnread: {
      fontWeight: "800",
      color: theme.colors.primaryStrong,
    },
    itemMessage: {
      fontSize: 13,
      color: theme.colors.mutedText,
      lineHeight: 18,
      marginBottom: 4,
    },
    itemDate: {
      fontSize: 11,
      color: theme.colors.mutedText,
    },
    separator: {
      height: 1,
      backgroundColor: theme.colors.border,
      marginLeft: 58,
    },
    empty: {
      flex: 1,
      alignItems: "center",
      justifyContent: "center",
      paddingHorizontal: 32,
    },
    emptyIcon: {
      fontSize: 44,
      marginBottom: 12,
    },
    emptyText: {
      fontSize: 17,
      fontWeight: "700",
      color: theme.colors.text,
      marginBottom: 6,
    },
    emptySubtext: {
      fontSize: 14,
      color: theme.colors.mutedText,
      textAlign: "center",
      lineHeight: 20,
    },
  });
