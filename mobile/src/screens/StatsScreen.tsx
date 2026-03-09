import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS } from '../utils/config';
import { stats } from '../services/api';
import Card from '../components/Card';
import LoadingScreen from '../components/LoadingScreen';
import ErrorMessage from '../components/ErrorMessage';

interface Props {
  route: any;
}

export default function StatsScreen({ route }: Props) {
  const { spaceId } = route.params;
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const fetchStats = useCallback(async () => {
    try {
      setError('');
      const result = await stats.get(spaceId);
      setData(result);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [spaceId]);

  useFocusEffect(
    useCallback(() => {
      fetchStats();
    }, [fetchStats])
  );

  if (loading) return <LoadingScreen />;
  if (error) return <ErrorMessage message={error} onRetry={fetchStats} />;

  const overview = data?.overview || {};
  const topPlayers = data?.top_players || [];
  const statsByType = data?.stats_by_game_type || [];

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.scroll}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => {
            setRefreshing(true);
            fetchStats();
          }}
          tintColor={COLORS.primary}
        />
      }
    >
      {/* Vue d'ensemble */}
      <Text style={styles.sectionTitle}>Vue d'ensemble</Text>
      <View style={styles.statsRow}>
        <View style={styles.statBox}>
          <Text style={styles.statValue}>{overview.total_games ?? 0}</Text>
          <Text style={styles.statLabel}>Parties</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statValue}>{overview.completed_games ?? 0}</Text>
          <Text style={styles.statLabel}>Terminées</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statValue}>{overview.total_rounds ?? 0}</Text>
          <Text style={styles.statLabel}>Manches</Text>
        </View>
      </View>

      {/* Top joueurs */}
      {topPlayers.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Meilleurs joueurs</Text>
          {topPlayers.map((p: any, i: number) => (
            <Card key={p.player_id || i} style={{ marginBottom: 6 }}>
              <View style={styles.playerRow}>
                <Text style={styles.rank}>#{i + 1}</Text>
                <Text style={styles.playerName}>{p.player_name}</Text>
                <View style={styles.playerStats}>
                  <Text style={styles.wins}>{p.win_count ?? 0} V</Text>
                  <Text style={styles.gamesPlayed}>
                    {p.games_played ?? 0} parties
                  </Text>
                </View>
              </View>
            </Card>
          ))}
        </View>
      )}

      {/* Stats par type de jeu */}
      {statsByType.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Par type de jeu</Text>
          {statsByType.map((gt: any) => (
            <Card key={gt.game_type_id} style={{ marginBottom: 6 }}>
              <Text style={styles.gtName}>{gt.game_type_name}</Text>
              <View style={styles.gtStatsRow}>
                <Text style={styles.gtStat}>
                  {gt.total_games ?? 0} parties
                </Text>
                <Text style={styles.gtStat}>
                  {gt.completed_games ?? 0} terminées
                </Text>
                <Text style={styles.gtStat}>
                  {gt.total_rounds ?? 0} manches
                </Text>
              </View>
            </Card>
          ))}
        </View>
      )}

      {topPlayers.length === 0 && statsByType.length === 0 && (
        <Text style={styles.emptyText}>
          Pas encore de données statistiques.
        </Text>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  scroll: {
    padding: 16,
    paddingBottom: 40,
  },
  sectionTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 12,
  },
  section: {
    marginTop: 24,
  },
  statsRow: {
    flexDirection: 'row',
    gap: 8,
  },
  statBox: {
    flex: 1,
    backgroundColor: COLORS.surface,
    borderRadius: 10,
    padding: 14,
    alignItems: 'center',
  },
  statValue: {
    color: COLORS.primary,
    fontSize: 24,
    fontWeight: '700',
  },
  statLabel: {
    color: COLORS.textSecondary,
    fontSize: 12,
    marginTop: 2,
  },
  playerRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  rank: {
    color: COLORS.warning,
    fontSize: 16,
    fontWeight: '700',
    width: 36,
  },
  playerName: {
    color: COLORS.white,
    fontSize: 15,
    flex: 1,
  },
  playerStats: {
    alignItems: 'flex-end',
  },
  wins: {
    color: COLORS.success,
    fontSize: 15,
    fontWeight: '700',
  },
  gamesPlayed: {
    color: COLORS.textMuted,
    fontSize: 11,
  },
  gtName: {
    color: COLORS.white,
    fontSize: 15,
    fontWeight: '600',
    marginBottom: 6,
  },
  gtStatsRow: {
    flexDirection: 'row',
    gap: 12,
  },
  gtStat: {
    color: COLORS.textSecondary,
    fontSize: 13,
  },
  emptyText: {
    color: COLORS.textSecondary,
    textAlign: 'center',
    fontSize: 15,
    marginTop: 40,
  },
});
