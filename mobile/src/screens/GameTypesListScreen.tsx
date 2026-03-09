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
import { COLORS, WIN_CONDITION_LABELS } from '../utils/config';
import { gameTypes, spaces } from '../services/api';
import { crossAlert, showAlert } from '../utils/alert';
import Card from '../components/Card';
import Badge from '../components/Badge';
import LoadingScreen from '../components/LoadingScreen';
import ErrorMessage from '../components/ErrorMessage';

interface Props {
  navigation: any;
  route: any;
}

export default function GameTypesListScreen({ navigation, route }: Props) {
  const { spaceId, myRole: passedRole } = route.params;
  const [list, setList] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [myRole, setMyRole] = useState<string>(passedRole || '');

  const isManager = myRole === 'admin' || myRole === 'manager';

  const fetchList = useCallback(async () => {
    try {
      setError('');
      const [typesResult, spaceResult] = await Promise.all([
        gameTypes.list(spaceId),
        !passedRole ? spaces.get(spaceId) : Promise.resolve(null),
      ]);
      setList(typesResult.game_types || []);
      if (spaceResult?.role) {
        setMyRole(spaceResult.role);
      }
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [spaceId, passedRole]);

  useFocusEffect(
    useCallback(() => {
      fetchList();
    }, [fetchList])
  );

  const handleDelete = (gt: any) => {
    crossAlert('Supprimer', `Supprimer le type "${gt.name}" ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: async () => {
          try {
            await gameTypes.delete(spaceId, gt.id);
            fetchList();
          } catch (e: any) {
            showAlert('Erreur', e.message);
          }
        },
      },
    ]);
  };

  if (loading) return <LoadingScreen />;
  if (error) return <ErrorMessage message={error} onRetry={fetchList} />;

  return (
    <View style={styles.container}>
      <FlatList
        data={list}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.list}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              fetchList();
            }}
            tintColor={COLORS.primary}
          />
        }
        renderItem={({ item }) => (
          <Card
            onPress={() =>
              navigation.navigate('GameTypeForm', {
                spaceId,
                gameType: item,
              })
            }
          >
            <View style={styles.row}>
              <View style={{ flex: 1 }}>
                <Text style={styles.name}>{item.name}</Text>
                {item.description ? (
                  <Text style={styles.desc} numberOfLines={2}>
                    {item.description}
                  </Text>
                ) : null}
                <Text style={styles.meta}>
                  {item.min_players ?? '?'} - {item.max_players ?? '?'} joueurs
                </Text>
              </View>
              <Badge
                label={WIN_CONDITION_LABELS[item.win_condition] || item.win_condition}
                color={COLORS.secondary}
              />
            </View>
            {isManager && (
              <TouchableOpacity
                onPress={() => handleDelete(item)}
                style={styles.deleteBtn}
              >
                <Text style={styles.deleteTxt}>Supprimer</Text>
              </TouchableOpacity>
            )}
          </Card>
        )}
        ListEmptyComponent={
          <Text style={styles.emptyText}>Aucun type de jeu configuré.</Text>
        }
      />
      {isManager && (
        <TouchableOpacity
          style={styles.fab}
          onPress={() => navigation.navigate('GameTypeForm', { spaceId })}
          activeOpacity={0.8}
        >
          <Text style={styles.fabText}>+</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  list: {
    padding: 16,
    paddingBottom: 80,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  name: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
  desc: {
    color: COLORS.textSecondary,
    fontSize: 13,
    marginTop: 2,
  },
  meta: {
    color: COLORS.textMuted,
    fontSize: 12,
    marginTop: 4,
  },
  deleteBtn: {
    marginTop: 10,
    alignSelf: 'flex-end',
  },
  deleteTxt: {
    color: COLORS.danger,
    fontSize: 13,
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
