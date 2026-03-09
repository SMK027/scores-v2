import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import { COLORS, SPACE_ROLE_LABELS } from '../utils/config';
import { spaces, invitations } from '../services/api';
import { showAlert } from '../utils/alert';
import Card from '../components/Card';
import Button from '../components/Button';
import Badge from '../components/Badge';
import LoadingScreen from '../components/LoadingScreen';
import ErrorMessage from '../components/ErrorMessage';
import { useFocusEffect } from '@react-navigation/native';

interface Props {
  navigation: any;
}

export default function SpacesListScreen({ navigation }: Props) {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const fetchSpaces = useCallback(async () => {
    try {
      setError('');
      const result = await spaces.list();
      setData(result);
    } catch (e: any) {
      setError(e.message || 'Erreur de chargement');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      fetchSpaces();
    }, [fetchSpaces])
  );

  const handleAccept = async (invId: number) => {
    try {
      const result = await invitations.accept(invId);
      showAlert('Succès', 'Vous avez rejoint l\'espace !');
      fetchSpaces();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    }
  };

  const handleDecline = async (invId: number) => {
    try {
      await invitations.decline(invId);
      fetchSpaces();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    }
  };

  if (loading) return <LoadingScreen />;
  if (error) return <ErrorMessage message={error} onRetry={fetchSpaces} />;

  const spaceList = data?.spaces || [];
  const pendingInvitations = data?.pending_invitations || [];

  return (
    <View style={styles.container}>
      <FlatList
        data={spaceList}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.list}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              fetchSpaces();
            }}
            tintColor={COLORS.primary}
          />
        }
        ListHeaderComponent={
          <>
            {pendingInvitations.length > 0 && (
              <View style={styles.invitationsSection}>
                <Text style={styles.sectionTitle}>
                  Invitations en attente ({pendingInvitations.length})
                </Text>
                {pendingInvitations.map((inv: any) => (
                  <Card key={inv.id} style={styles.invitationCard}>
                    <Text style={styles.invName}>
                      {inv.space_name || 'Espace #' + inv.space_id}
                    </Text>
                    <Text style={styles.invRole}>
                      Rôle proposé : {SPACE_ROLE_LABELS[inv.role] || inv.role}
                    </Text>
                    <View style={styles.invActions}>
                      <Button
                        title="Accepter"
                        onPress={() => handleAccept(inv.id)}
                        variant="success"
                        style={{ flex: 1, marginRight: 8 }}
                      />
                      <Button
                        title="Refuser"
                        onPress={() => handleDecline(inv.id)}
                        variant="danger"
                        style={{ flex: 1 }}
                      />
                    </View>
                  </Card>
                ))}
              </View>
            )}
            <Text style={styles.sectionTitle}>Mes espaces</Text>
          </>
        }
        renderItem={({ item }) => (
          <Card
            onPress={() =>
              navigation.navigate('SpaceDashboard', { spaceId: item.id })
            }
          >
            <View style={styles.spaceRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.spaceName}>{item.name}</Text>
                {item.description ? (
                  <Text style={styles.spaceDesc} numberOfLines={2}>
                    {item.description}
                  </Text>
                ) : null}
              </View>
              <Badge
                label={SPACE_ROLE_LABELS[item.role] || item.role}
                color={
                  item.role === 'admin'
                    ? COLORS.accent
                    : item.role === 'manager'
                    ? COLORS.secondary
                    : COLORS.primary
                }
              />
            </View>
          </Card>
        )}
        ListEmptyComponent={
          <View style={styles.empty}>
            <Text style={styles.emptyIcon}>📭</Text>
            <Text style={styles.emptyText}>Aucun espace</Text>
            <Text style={styles.emptySubtext}>
              Créez votre premier espace de jeu !
            </Text>
          </View>
        }
      />
      <TouchableOpacity
        style={styles.fab}
        onPress={() => navigation.navigate('SpaceCreate')}
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
  list: {
    padding: 16,
    paddingBottom: 80,
  },
  sectionTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 12,
    marginTop: 8,
  },
  invitationsSection: {
    marginBottom: 16,
  },
  invitationCard: {
    borderColor: COLORS.warning,
  },
  invName: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
  invRole: {
    color: COLORS.textSecondary,
    fontSize: 13,
    marginTop: 4,
    marginBottom: 12,
  },
  invActions: {
    flexDirection: 'row',
  },
  spaceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  spaceName: {
    color: COLORS.white,
    fontSize: 17,
    fontWeight: '600',
  },
  spaceDesc: {
    color: COLORS.textSecondary,
    fontSize: 13,
    marginTop: 4,
  },
  empty: {
    alignItems: 'center',
    paddingTop: 60,
  },
  emptyIcon: {
    fontSize: 48,
    marginBottom: 12,
  },
  emptyText: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '600',
  },
  emptySubtext: {
    color: COLORS.textSecondary,
    fontSize: 14,
    marginTop: 4,
  },
  fab: {
    position: 'absolute',
    right: 20,
    bottom: 20,
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 6,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.3,
    shadowRadius: 5,
  },
  fabText: {
    color: COLORS.white,
    fontSize: 28,
    fontWeight: '300',
    marginTop: -2,
  },
});
