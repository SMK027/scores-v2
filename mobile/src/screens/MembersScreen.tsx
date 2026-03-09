import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS, SPACE_ROLE_LABELS } from '../utils/config';
import { members, spaces } from '../services/api';
import { crossAlert, showAlert } from '../utils/alert';
import Card from '../components/Card';
import Button from '../components/Button';
import Badge from '../components/Badge';
import Input from '../components/Input';
import LoadingScreen from '../components/LoadingScreen';
import ErrorMessage from '../components/ErrorMessage';

interface Props {
  navigation: any;
  route: any;
}

const ROLES = ['admin', 'manager', 'member', 'guest'];

export default function MembersScreen({ route }: Props) {
  const { spaceId, myRole: passedRole } = route.params;
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [username, setUsername] = useState('');
  const [inviting, setInviting] = useState(false);
  const [myRole, setMyRole] = useState<string>(passedRole || '');

  const isAdmin = myRole === 'admin';
  const isManager = myRole === 'manager' || isAdmin;

  const fetchMembers = useCallback(async () => {
    try {
      setError('');
      const [membersResult, spaceResult] = await Promise.all([
        members.list(spaceId),
        !passedRole ? spaces.get(spaceId) : Promise.resolve(null),
      ]);
      setData(membersResult);
      if (spaceResult?.role) {
        setMyRole(spaceResult.role);
      }
    } catch (e: any) {
      setError(e.message || 'Erreur de chargement');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [spaceId, passedRole]);

  useFocusEffect(
    useCallback(() => {
      fetchMembers();
    }, [fetchMembers])
  );

  const handleInvite = async () => {
    if (!username.trim()) return;
    setInviting(true);
    try {
      await members.add(spaceId, username.trim());
      showAlert('Succès', `Invitation envoyée à ${username.trim()}`);
      setUsername('');
      fetchMembers();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setInviting(false);
    }
  };

  const handleChangeRole = (member: any) => {
    const otherRoles = ROLES.filter((r) => r !== member.role);
    crossAlert(
      `Changer le rôle de ${member.username}`,
      `Rôle actuel : ${SPACE_ROLE_LABELS[member.role]}`,
      [
        ...otherRoles.map((role) => ({
          text: SPACE_ROLE_LABELS[role],
          onPress: async () => {
            try {
              await members.updateRole(spaceId, member.id, role);
              fetchMembers();
            } catch (e: any) {
              showAlert('Erreur', e.message);
            }
          },
        })),
        { text: 'Annuler', style: 'cancel' as const },
      ]
    );
  };

  const handleRemove = (member: any) => {
    crossAlert(
      'Retirer le membre',
      `Retirer ${member.username} de l'espace ?`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Retirer',
          style: 'destructive',
          onPress: async () => {
            try {
              await members.remove(spaceId, member.id);
              fetchMembers();
            } catch (e: any) {
              showAlert('Erreur', e.message);
            }
          },
        },
      ]
    );
  };

  if (loading) return <LoadingScreen />;
  if (error) return <ErrorMessage message={error} onRetry={fetchMembers} />;

  const memberList = data?.members || [];
  const pendingInvitations = data?.pending_invitations || [];

  return (
    <View style={styles.container}>
      <FlatList
        data={memberList}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.list}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              fetchMembers();
            }}
            tintColor={COLORS.primary}
          />
        }
        ListHeaderComponent={
          <>
            {/* Inviter */}
            {isManager && (
              <View style={styles.inviteSection}>
                <Input
                  label="Inviter un utilisateur"
                  placeholder="Nom d'utilisateur"
                  value={username}
                  onChangeText={setUsername}
                />
                <Button
                  title="Envoyer l'invitation"
                  onPress={handleInvite}
                  loading={inviting}
                  disabled={!username.trim()}
                />
              </View>
            )}

            {/* Invitations en attente */}
            {pendingInvitations.length > 0 && (
              <View style={styles.section}>
                <Text style={styles.sectionTitle}>
                  Invitations en attente ({pendingInvitations.length})
                </Text>
                {pendingInvitations.map((inv: any) => (
                  <Card key={inv.id}>
                    <View style={styles.memberRow}>
                      <Text style={styles.memberName}>
                        {inv.username || inv.email || `#${inv.user_id}`}
                      </Text>
                      <Badge label="En attente" color={COLORS.warning} />
                    </View>
                  </Card>
                ))}
              </View>
            )}

            <Text style={styles.sectionTitle}>
              Membres ({memberList.length})
            </Text>
          </>
        }
        renderItem={({ item }) => (
          <Card>
            <View style={styles.memberRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.memberName}>{item.username}</Text>
                <Text style={styles.memberJoined}>
                  {item.is_creator ? 'Créateur · ' : ''}
                  Depuis le {item.joined_at?.substring(0, 10)}
                </Text>
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
            {isAdmin && !item.is_creator && (
              <View style={styles.memberActions}>
                <Button
                  title="Rôle"
                  onPress={() => handleChangeRole(item)}
                  variant="outline"
                  style={{ flex: 1, marginRight: 8 }}
                />
                <Button
                  title="Retirer"
                  onPress={() => handleRemove(item)}
                  variant="danger"
                  style={{ flex: 1 }}
                />
              </View>
            )}
          </Card>
        )}
        ListEmptyComponent={
          <Text style={styles.emptyText}>Aucun membre</Text>
        }
      />
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
    paddingBottom: 40,
  },
  inviteSection: {
    marginBottom: 20,
  },
  section: {
    marginBottom: 16,
  },
  sectionTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 12,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  memberName: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
  memberJoined: {
    color: COLORS.textSecondary,
    fontSize: 12,
    marginTop: 2,
  },
  memberActions: {
    flexDirection: 'row',
    marginTop: 12,
  },
  emptyText: {
    color: COLORS.textSecondary,
    textAlign: 'center',
    marginTop: 40,
    fontSize: 15,
  },
});
