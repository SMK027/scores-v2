import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS, SPACE_ROLE_LABELS, GAME_STATUS_LABELS, GAME_STATUS_COLORS } from '../utils/config';
import { spaces } from '../services/api';
import Card from '../components/Card';
import Button from '../components/Button';
import Badge from '../components/Badge';
import LoadingScreen from '../components/LoadingScreen';
import ErrorMessage from '../components/ErrorMessage';
import { useAuth } from '../context/AuthContext';
import { crossAlert, showAlert } from '../utils/alert';

interface Props {
  navigation: any;
  route: any;
}

export default function SpaceDashboardScreen({ navigation, route }: Props) {
  const { spaceId } = route.params;
  const { user } = useAuth();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const fetchSpace = useCallback(async () => {
    try {
      setError('');
      const result = await spaces.get(spaceId);
      setData(result);
    } catch (e: any) {
      setError(e.message || 'Erreur de chargement');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [spaceId]);

  useFocusEffect(
    useCallback(() => {
      fetchSpace();
    }, [fetchSpace])
  );

  const handleLeave = () => {
    crossAlert(
      'Quitter l\'espace',
      'Êtes-vous sûr de vouloir quitter cet espace ?',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Quitter',
          style: 'destructive',
          onPress: async () => {
            try {
              await spaces.leave(spaceId);
              navigation.goBack();
            } catch (e: any) {
              showAlert('Erreur', e.message);
            }
          },
        },
      ]
    );
  };

  const handleDelete = () => {
    crossAlert(
      'Supprimer l\'espace',
      'Cette action est irréversible. Toutes les données seront supprimées.',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer',
          style: 'destructive',
          onPress: async () => {
            try {
              await spaces.delete(spaceId);
              navigation.goBack();
            } catch (e: any) {
              showAlert('Erreur', e.message);
            }
          },
        },
      ]
    );
  };

  if (loading) return <LoadingScreen />;
  if (error) return <ErrorMessage message={error} onRetry={fetchSpace} />;
  if (!data?.space) return <ErrorMessage message="Espace introuvable" />;

  const space = data.space;
  const recentGames = data.recent_games || [];
  const membersList = data.members || [];
  const stats = data.stats || {};
  const myRole = data.role;
  const isAdmin = myRole === 'admin';
  const isManager = myRole === 'manager' || isAdmin;

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.scroll}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => {
            setRefreshing(true);
            fetchSpace();
          }}
          tintColor={COLORS.primary}
        />
      }
    >
      {/* En-tête */}
      <View style={styles.header}>
        <Text style={styles.title}>{space.name}</Text>
        {space.description ? (
          <Text style={styles.description}>{space.description}</Text>
        ) : null}
        <Badge
          label={SPACE_ROLE_LABELS[myRole] || myRole}
          color={isAdmin ? COLORS.accent : isManager ? COLORS.secondary : COLORS.primary}
        />
      </View>

      {/* Statistiques rapides */}
      <View style={styles.statsRow}>
        <View style={styles.statBox}>
          <Text style={styles.statValue}>{recentGames.length}</Text>
          <Text style={styles.statLabel}>Parties</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statValue}>{stats.member_count ?? membersList.length}</Text>
          <Text style={styles.statLabel}>Membres</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statValue}>{stats.game_type_count ?? 0}</Text>
          <Text style={styles.statLabel}>Types</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statValue}>{stats.player_count ?? 0}</Text>
          <Text style={styles.statLabel}>Joueurs</Text>
        </View>
      </View>

      {/* Navigation rapide */}
      <View style={styles.quickActions}>
        <Button
          title="Parties"
          onPress={() => navigation.navigate('GamesList', { spaceId })}
          style={styles.actionBtn}
        />
        <Button
          title="Membres"
          onPress={() => navigation.navigate('Members', { spaceId, myRole })}
          variant="secondary"
          style={styles.actionBtn}
        />
      </View>
      <View style={styles.quickActions}>
        <Button
          title="Types de jeux"
          onPress={() => navigation.navigate('GameTypesList', { spaceId, myRole })}
          variant="outline"
          style={styles.actionBtn}
        />
        <Button
          title="Joueurs"
          onPress={() => navigation.navigate('PlayersList', { spaceId, myRole })}
          variant="outline"
          style={styles.actionBtn}
        />
      </View>
      <Button
        title="Statistiques"
        onPress={() => navigation.navigate('Stats', { spaceId })}
        variant="outline"
        style={{ marginTop: 8 }}
      />

      {/* Parties récentes */}
      {recentGames.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Parties récentes</Text>
          {recentGames.map((game: any) => (
            <Card
              key={game.id}
              onPress={() =>
                navigation.navigate('GameDetail', { spaceId, gameId: game.id })
              }
            >
              <View style={styles.gameRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.gameType}>
                    {game.game_type_name || 'Type inconnu'}
                  </Text>
                  <Text style={styles.gameDate}>
                    {game.created_at?.substring(0, 10)}
                  </Text>
                </View>
                <Badge
                  label={GAME_STATUS_LABELS[game.status] || game.status}
                  color={GAME_STATUS_COLORS[game.status] || COLORS.textMuted}
                />
              </View>
            </Card>
          ))}
        </View>
      )}

      {/* Actions admin */}
      <View style={styles.section}>
        {isAdmin && (
          <>
            <Button
              title="Modifier l'espace"
              onPress={() =>
                navigation.navigate('SpaceEdit', {
                  spaceId,
                  name: space.name,
                  description: space.description,
                })
              }
              variant="outline"
              style={{ marginBottom: 8 }}
            />
            <Button
              title="Supprimer l'espace"
              onPress={handleDelete}
              variant="danger"
              style={{ marginBottom: 8 }}
            />
          </>
        )}
        {!isAdmin && (
          <Button
            title="Quitter l'espace"
            onPress={handleLeave}
            variant="danger"
          />
        )}
      </View>
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
  header: {
    marginBottom: 20,
    alignItems: 'flex-start',
  },
  title: {
    color: COLORS.white,
    fontSize: 24,
    fontWeight: '700',
    marginBottom: 6,
  },
  description: {
    color: COLORS.textSecondary,
    fontSize: 14,
    marginBottom: 8,
  },
  statsRow: {
    flexDirection: 'row',
    marginBottom: 16,
  },
  statBox: {
    flex: 1,
    backgroundColor: COLORS.surface,
    borderRadius: 10,
    padding: 12,
    marginHorizontal: 4,
    alignItems: 'center',
  },
  statValue: {
    color: COLORS.primary,
    fontSize: 22,
    fontWeight: '700',
  },
  statLabel: {
    color: COLORS.textSecondary,
    fontSize: 11,
    marginTop: 2,
  },
  quickActions: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 8,
  },
  actionBtn: {
    flex: 1,
  },
  section: {
    marginTop: 24,
  },
  sectionTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 12,
  },
  gameRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  gameType: {
    color: COLORS.white,
    fontSize: 15,
    fontWeight: '600',
  },
  gameDate: {
    color: COLORS.textSecondary,
    fontSize: 12,
    marginTop: 2,
  },
});
