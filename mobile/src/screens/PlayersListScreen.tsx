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
import { COLORS } from '../utils/config';
import { players, spaces } from '../services/api';
import { crossAlert, showAlert } from '../utils/alert';
import Card from '../components/Card';
import Button from '../components/Button';
import Input from '../components/Input';
import LoadingScreen from '../components/LoadingScreen';
import ErrorMessage from '../components/ErrorMessage';

interface Props {
  navigation: any;
  route: any;
}

export default function PlayersListScreen({ route }: Props) {
  const { spaceId, myRole: passedRole } = route.params;
  const [list, setList] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [newName, setNewName] = useState('');
  const [adding, setAdding] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editName, setEditName] = useState('');
  const [myRole, setMyRole] = useState<string>(passedRole || '');

  const isManager = myRole === 'admin' || myRole === 'manager';

  const fetchList = useCallback(async () => {
    try {
      setError('');
      const [playersResult, spaceResult] = await Promise.all([
        players.list(spaceId),
        !passedRole ? spaces.get(spaceId) : Promise.resolve(null),
      ]);
      setList(playersResult.players || []);
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

  const handleAdd = async () => {
    if (!newName.trim()) return;
    setAdding(true);
    try {
      await players.create(spaceId, { name: newName.trim() });
      setNewName('');
      fetchList();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setAdding(false);
    }
  };

  const handleUpdate = async (id: number) => {
    if (!editName.trim()) return;
    try {
      await players.update(spaceId, id, { name: editName.trim() });
      setEditingId(null);
      fetchList();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    }
  };

  const handleDelete = (player: any) => {
    crossAlert('Supprimer', `Supprimer le joueur "${player.name}" ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: async () => {
          try {
            await players.delete(spaceId, player.id);
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
        ListHeaderComponent={
          isManager ? (
            <View style={styles.addSection}>
              <Input
                label="Ajouter un joueur"
                placeholder="Nom du joueur"
                value={newName}
                onChangeText={setNewName}
              />
              <Button
                title="Ajouter"
                onPress={handleAdd}
                loading={adding}
                disabled={!newName.trim()}
              />
            </View>
          ) : null
        }
        renderItem={({ item }) => (
          <Card>
            {editingId === item.id ? (
              <View>
                <Input
                  value={editName}
                  onChangeText={setEditName}
                  placeholder="Nouveau nom"
                />
                <View style={styles.editActions}>
                  <Button
                    title="Enregistrer"
                    onPress={() => handleUpdate(item.id)}
                    style={{ flex: 1, marginRight: 8 }}
                  />
                  <Button
                    title="Annuler"
                    onPress={() => setEditingId(null)}
                    variant="outline"
                    style={{ flex: 1 }}
                  />
                </View>
              </View>
            ) : (
              <View style={styles.row}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.name}>{item.name}</Text>
                  {item.username && (
                    <Text style={styles.linked}>
                      Lié à @{item.username}
                    </Text>
                  )}
                </View>
                {isManager && (
                  <View style={styles.actions}>
                    <TouchableOpacity
                      onPress={() => {
                        setEditingId(item.id);
                        setEditName(item.name);
                      }}
                      style={styles.actionBtn}
                    >
                      <Text style={styles.editTxt}>Modifier</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      onPress={() => handleDelete(item)}
                      style={styles.actionBtn}
                    >
                      <Text style={styles.deleteTxt}>Supprimer</Text>
                    </TouchableOpacity>
                  </View>
                )}
              </View>
            )}
          </Card>
        )}
        ListEmptyComponent={
          <Text style={styles.emptyText}>Aucun joueur dans cet espace.</Text>
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
  addSection: {
    marginBottom: 20,
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
  linked: {
    color: COLORS.textSecondary,
    fontSize: 12,
    marginTop: 2,
  },
  actions: {
    flexDirection: 'row',
  },
  actionBtn: {
    marginLeft: 12,
  },
  editTxt: {
    color: COLORS.primary,
    fontSize: 13,
  },
  deleteTxt: {
    color: COLORS.danger,
    fontSize: 13,
  },
  editActions: {
    flexDirection: 'row',
    marginTop: 8,
  },
  emptyText: {
    color: COLORS.textSecondary,
    textAlign: 'center',
    marginTop: 40,
    fontSize: 15,
  },
});
