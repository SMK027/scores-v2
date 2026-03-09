import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS, GAME_STATUS_LABELS, GAME_STATUS_COLORS } from '../utils/config';
import { games } from '../services/api';
import Card from '../components/Card';
import Badge from '../components/Badge';
import LoadingScreen from '../components/LoadingScreen';
import ErrorMessage from '../components/ErrorMessage';

interface Props {
  navigation: any;
  route: any;
}

const STATUS_FILTERS = [
  { key: '', label: 'Toutes' },
  { key: 'in_progress', label: 'En cours' },
  { key: 'pending', label: 'En attente' },
  { key: 'paused', label: 'En pause' },
  { key: 'completed', label: 'Terminées' },
];

export default function GamesListScreen({ navigation, route }: Props) {
  const { spaceId } = route.params;
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);

  const fetchGames = useCallback(
    async (p = 1) => {
      try {
        setError('');
        const params: any = { page: String(p) };
        if (statusFilter) params.status = statusFilter;
        const result = await games.list(spaceId, params);
        setData(result);
        setPage(p);
      } catch (e: any) {
        setError(e.message);
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [spaceId, statusFilter]
  );

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      fetchGames(1);
    }, [fetchGames])
  );

  if (loading) return <LoadingScreen />;
  if (error) return <ErrorMessage message={error} onRetry={() => fetchGames(1)} />;

  const gameList = data?.data || [];
  const totalPages = data?.lastPage || 1;

  return (
    <View style={styles.container}>
      {/* Filtres */}
      <View style={styles.filters}>
        {STATUS_FILTERS.map((f) => (
          <TouchableOpacity
            key={f.key}
            style={[
              styles.filterBtn,
              statusFilter === f.key && styles.filterActive,
            ]}
            onPress={() => setStatusFilter(f.key)}
          >
            <Text
              style={[
                styles.filterText,
                statusFilter === f.key && styles.filterTextActive,
              ]}
            >
              {f.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <FlatList
        data={gameList}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.list}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              fetchGames(1);
            }}
            tintColor={COLORS.primary}
          />
        }
        renderItem={({ item }) => (
          <Card
            onPress={() =>
              navigation.navigate('GameDetail', { spaceId, gameId: item.id })
            }
          >
            <View style={styles.gameRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.gameType}>
                  {item.game_type_name || 'Type inconnu'}
                </Text>
                <Text style={styles.meta}>
                  {item.player_count ?? '?'} joueurs · {item.round_count ?? 0}{' '}
                  manches
                </Text>
                <Text style={styles.date}>
                  {item.created_at?.substring(0, 16).replace('T', ' ')}
                </Text>
              </View>
              <Badge
                label={GAME_STATUS_LABELS[item.status] || item.status}
                color={GAME_STATUS_COLORS[item.status] || COLORS.textMuted}
              />
            </View>
          </Card>
        )}
        ListFooterComponent={
          totalPages > 1 ? (
            <View style={styles.pagination}>
              {page > 1 && (
                <TouchableOpacity
                  onPress={() => fetchGames(page - 1)}
                  style={styles.pageBtn}
                >
                  <Text style={styles.pageText}>← Précédent</Text>
                </TouchableOpacity>
              )}
              <Text style={styles.pageInfo}>
                {page} / {totalPages}
              </Text>
              {page < totalPages && (
                <TouchableOpacity
                  onPress={() => fetchGames(page + 1)}
                  style={styles.pageBtn}
                >
                  <Text style={styles.pageText}>Suivant →</Text>
                </TouchableOpacity>
              )}
            </View>
          ) : null
        }
        ListEmptyComponent={
          <Text style={styles.emptyText}>Aucune partie trouvée.</Text>
        }
      />

      <TouchableOpacity
        style={styles.fab}
        onPress={() => navigation.navigate('GameCreate', { spaceId })}
        activeOpacity={0.8}
      >
        <Text style={styles.fabText}>+</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  filters: {
    flexDirection: 'row',
    padding: 12,
    paddingBottom: 0,
    flexWrap: 'wrap',
    gap: 6,
  },
  filterBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: COLORS.surface,
  },
  filterActive: {
    backgroundColor: COLORS.primary,
  },
  filterText: {
    color: COLORS.textSecondary,
    fontSize: 13,
  },
  filterTextActive: {
    color: COLORS.white,
    fontWeight: '600',
  },
  list: {
    padding: 16,
    paddingBottom: 80,
  },
  gameRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  gameType: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
  meta: {
    color: COLORS.textSecondary,
    fontSize: 13,
    marginTop: 2,
  },
  date: {
    color: COLORS.textMuted,
    fontSize: 11,
    marginTop: 2,
  },
  pagination: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 16,
    gap: 16,
  },
  pageBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  pageText: {
    color: COLORS.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  pageInfo: {
    color: COLORS.textSecondary,
    fontSize: 14,
  },
  emptyText: {
    color: COLORS.textSecondary,
    textAlign: 'center',
    marginTop: 40,
    fontSize: 15,
  },
  fab: {
    position: 'absolute',
    bottom: 20,
    right: 20,
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: COLORS.primary,
    justifyContent: 'center',
    alignItems: 'center',
    elevation: 5,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.3,
    shadowRadius: 4,
  },
  fabText: {
    color: COLORS.white,
    fontSize: 28,
    lineHeight: 30,
  },
});
