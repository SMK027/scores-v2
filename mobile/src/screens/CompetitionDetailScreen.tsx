import { useCallback, useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { ApiError, fetchCompetitionDetails } from "../services/api";
import { useAppTheme } from "../context/ThemeContext";
import type { AppTheme } from "../styles";
import type { Competition, CompetitionParticipant, CompetitionStats, Space } from "../types/api";

type Props = {
  token: string;
  space: Space;
  competitionId: number;
  onBack: () => void;
};

function formatDateTime(value?: string | null): string {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return parsed.toLocaleString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatDuration(seconds: number): string {
  const safe = Math.max(0, Math.floor(seconds));
  const h = Math.floor(safe / 3600);
  const m = Math.floor((safe % 3600) / 60);
  const s = safe % 60;

  if (h > 0) {
    return `${h}h ${m}m`;
  }
  if (m > 0) {
    return `${m}m ${s}s`;
  }
  return `${s}s`;
}

function getStatusMeta(
  status: Competition["status"],
  theme: AppTheme
): { label: string; backgroundColor: string; textColor: string } {
  switch (status) {
    case "active":
      return { label: "Active", backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.success };
    case "paused":
      return { label: "En pause", backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.warning };
    case "closed":
      return { label: "Clôturée", backgroundColor: theme.colors.primarySoft, textColor: theme.colors.primary };
    case "planned":
    default:
      return { label: "Planifiée", backgroundColor: theme.colors.backgroundSoft, textColor: theme.colors.mutedText };
  }
}

export function CompetitionDetailScreen({ token, space, competitionId, onBack }: Props) {
  const { theme } = useAppTheme();
  const styles = useMemo(() => createStyles(theme), [theme]);

  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [competition, setCompetition] = useState<Competition | null>(null);
  const [participants, setParticipants] = useState<CompetitionParticipant[]>([]);
  const [stats, setStats] = useState<CompetitionStats | null>(null);

  const loadDetails = useCallback(async () => {
    try {
      setError(null);
      const data = await fetchCompetitionDetails(token, space.id, competitionId);
      setCompetition(data.competition);
      setParticipants(data.participants);
      setStats(data.stats);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Impossible de charger les détails de la compétition.");
      }
    }
  }, [competitionId, space.id, token]);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      await loadDetails();
      setLoading(false);
    };

    void run();
  }, [loadDetails]);

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator />
      </View>
    );
  }

  if (!competition || !stats) {
    return (
      <View style={styles.centered}>
        <Text style={styles.error}>{error || "Compétition introuvable."}</Text>
        <Pressable style={styles.secondaryButton} onPress={onBack}>
          <Text style={styles.secondaryText}>Retour</Text>
        </Pressable>
      </View>
    );
  }

  const statusMeta = getStatusMeta(competition.status, theme);

  return (
    <ScrollView style={styles.container} contentContainerStyle={[styles.content, { paddingTop: Math.max(insets.top, 12) + 8 }]}> 
      <View style={styles.header}>
        <Pressable style={styles.navButton} onPress={onBack}>
          <Text style={styles.navButtonText}>← Retour</Text>
        </Pressable>
        <Pressable style={styles.navButton} onPress={loadDetails}>
          <Text style={styles.navButtonText}>↻ Rafraîchir</Text>
        </Pressable>
      </View>

      <Text style={styles.title}>{competition.name}</Text>
      {competition.description ? <Text style={styles.meta}>{competition.description}</Text> : null}

      <View style={styles.rowBetween}>
        <Text style={styles.meta}>Statut</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusMeta.backgroundColor }]}> 
          <Text style={[styles.statusText, { color: statusMeta.textColor }]}>{statusMeta.label}</Text>
        </View>
      </View>

      <Text style={styles.meta}>Début : {formatDateTime(competition.starts_at)}</Text>
      <Text style={styles.meta}>Fin : {formatDateTime(competition.ends_at)}</Text>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.block}>
        <Text style={styles.blockTitle}>Statistiques</Text>
        <View style={styles.statRow}>
          <Text style={styles.statLabel}>Participants</Text>
          <Text style={styles.statValue}>{participants.length}</Text>
        </View>
        <View style={styles.statRow}>
          <Text style={styles.statLabel}>Parties</Text>
          <Text style={styles.statValue}>{stats.completed_games}/{stats.total_games}</Text>
        </View>
        <View style={styles.statRow}>
          <Text style={styles.statLabel}>Manches totales</Text>
          <Text style={styles.statValue}>{stats.total_rounds}</Text>
        </View>
        <View style={styles.statRow}>
          <Text style={styles.statLabel}>Taux de victoire moyen</Text>
          <Text style={styles.statValue}>{stats.avg_win_rate.toFixed(1)}%</Text>
        </View>
        <View style={styles.statRow}>
          <Text style={styles.statLabel}>Temps moyen de jeu</Text>
          <Text style={styles.statValue}>{formatDuration(stats.avg_play_seconds_per_game)}</Text>
        </View>
        <View style={styles.statRow}>
          <Text style={styles.statLabel}>Manches moyennes / compétiteur</Text>
          <Text style={styles.statValue}>{stats.avg_rounds_per_competitor.toFixed(2)}</Text>
        </View>
      </View>

      <View style={styles.block}>
        <Text style={styles.blockTitle}>Participants</Text>
        {participants.length === 0 ? <Text style={styles.meta}>Aucun participant inscrit.</Text> : null}

        {participants.map((participant) => (
          <View key={participant.player_id} style={styles.participantCard}>
            <View style={styles.rowBetween}>
              <Text style={styles.participantName}>{participant.name}</Text>
              <Text style={styles.participantRate}>{participant.win_rate.toFixed(1)}%</Text>
            </View>
            {participant.linked_username ? (
              <Text style={styles.participantMeta}>Compte: {participant.linked_username}</Text>
            ) : null}
            <Text style={styles.participantMeta}>
              {participant.rounds_won} manche(s) gagnée(s) sur {participant.rounds_played}
            </Text>
          </View>
        ))}
      </View>
    </ScrollView>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: theme.colors.background,
    padding: 14,
  },
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: 14,
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 8,
  },
  navButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.md,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  navButtonText: {
    color: theme.colors.primary,
    fontWeight: "700",
    fontSize: 12,
  },
  title: {
    fontSize: 22,
    fontWeight: "700",
    color: theme.colors.text,
  },
  meta: {
    color: theme.colors.mutedText,
    marginTop: 6,
  },
  rowBetween: {
    marginTop: 6,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  statusBadge: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  statusText: {
    fontWeight: "700",
    fontSize: 12,
  },
  error: {
    color: theme.colors.danger,
    marginTop: 10,
    textAlign: "center",
  },
  block: {
    marginTop: 14,
    backgroundColor: theme.colors.card,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 12,
  },
  blockTitle: {
    fontSize: 16,
    fontWeight: "700",
    color: theme.colors.text,
    marginBottom: 8,
  },
  statRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 8,
  },
  statLabel: {
    color: theme.colors.text,
    fontWeight: "600",
  },
  statValue: {
    color: theme.colors.mutedText,
    fontWeight: "700",
  },
  participantCard: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.sm,
    padding: 10,
    backgroundColor: theme.colors.backgroundSoft,
    marginBottom: 8,
  },
  participantName: {
    color: theme.colors.text,
    fontWeight: "700",
    flex: 1,
    marginRight: 8,
  },
  participantRate: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
  participantMeta: {
    color: theme.colors.mutedText,
    marginTop: 4,
  },
  secondaryButton: {
    marginTop: 12,
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    paddingVertical: 10,
    paddingHorizontal: 14,
    alignItems: "center",
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  secondaryText: {
    color: theme.colors.primary,
    fontWeight: "700",
  },
});
