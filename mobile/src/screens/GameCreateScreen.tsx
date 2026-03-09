import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { COLORS, WIN_CONDITION_LABELS } from '../utils/config';
import { gameTypes, players, games } from '../services/api';
import { showAlert } from '../utils/alert';
import Button from '../components/Button';
import Input from '../components/Input';
import Card from '../components/Card';
import Badge from '../components/Badge';
import LoadingScreen from '../components/LoadingScreen';

interface Props {
  navigation: any;
  route: any;
}

export default function GameCreateScreen({ navigation, route }: Props) {
  const { spaceId } = route.params;
  const [gtList, setGtList] = useState<any[]>([]);
  const [playerList, setPlayerList] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const [selectedGt, setSelectedGt] = useState<any>(null);
  const [selectedPlayers, setSelectedPlayers] = useState<number[]>([]);
  const [notes, setNotes] = useState('');
  const [creating, setCreating] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const [gtRes, pRes] = await Promise.all([
          gameTypes.list(spaceId),
          players.list(spaceId),
        ]);
        setGtList(gtRes.game_types || []);
        setPlayerList(pRes.players || []);
      } catch (e: any) {
        showAlert('Erreur', e.message);
      } finally {
        setLoading(false);
      }
    })();
  }, [spaceId]);

  const togglePlayer = (id: number) => {
    setSelectedPlayers((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    );
  };

  const handleCreate = async () => {
    if (!selectedGt) {
      showAlert('Erreur', 'Sélectionnez un type de jeu.');
      return;
    }
    if (selectedPlayers.length < (selectedGt.min_players || 2)) {
      showAlert(
        'Erreur',
        `Minimum ${selectedGt.min_players || 2} joueurs requis.`
      );
      return;
    }
    if (
      selectedGt.max_players &&
      selectedPlayers.length > selectedGt.max_players
    ) {
      showAlert(
        'Erreur',
        `Maximum ${selectedGt.max_players} joueurs autorisés.`
      );
      return;
    }

    setCreating(true);
    try {
      const result = await games.create(spaceId, {
        game_type_id: selectedGt.id,
        player_ids: selectedPlayers,
        notes: notes.trim() || undefined,
      });
      showAlert('Succès', 'Partie créée !');
      navigation.replace('GameDetail', {
        spaceId,
        gameId: result.game?.id || result.id,
      });
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setCreating(false);
    }
  };

  if (loading) return <LoadingScreen />;

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={styles.container}
    >
      <ScrollView contentContainerStyle={styles.scroll}>
        {/* Type de jeu */}
        <Text style={styles.sectionTitle}>Type de jeu</Text>
        {gtList.length === 0 ? (
          <Text style={styles.hint}>
            Aucun type de jeu. Créez-en un d'abord.
          </Text>
        ) : (
          <View style={styles.items}>
            {gtList.map((gt) => (
              <TouchableOpacity
                key={gt.id}
                style={[
                  styles.chip,
                  selectedGt?.id === gt.id && styles.chipSelected,
                ]}
                onPress={() => setSelectedGt(gt)}
              >
                <Text
                  style={[
                    styles.chipText,
                    selectedGt?.id === gt.id && styles.chipTextSelected,
                  ]}
                >
                  {gt.name}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        )}
        {selectedGt && (
          <Text style={styles.hint}>
            {WIN_CONDITION_LABELS[selectedGt.win_condition]} ·{' '}
            {selectedGt.min_players}-{selectedGt.max_players} joueurs
          </Text>
        )}

        {/* Joueurs */}
        <Text style={[styles.sectionTitle, { marginTop: 24 }]}>
          Joueurs ({selectedPlayers.length})
        </Text>
        {playerList.length === 0 ? (
          <Text style={styles.hint}>
            Aucun joueur. Ajoutez-en depuis la gestion des joueurs.
          </Text>
        ) : (
          <View style={styles.items}>
            {playerList.map((p) => (
              <TouchableOpacity
                key={p.id}
                style={[
                  styles.chip,
                  selectedPlayers.includes(p.id) && styles.chipSelected,
                ]}
                onPress={() => togglePlayer(p.id)}
              >
                <Text
                  style={[
                    styles.chipText,
                    selectedPlayers.includes(p.id) &&
                      styles.chipTextSelected,
                  ]}
                >
                  {p.name}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        )}

        {/* Notes */}
        <Input
          label="Notes (optionnel)"
          placeholder="Notes sur la partie..."
          value={notes}
          onChangeText={setNotes}
          multiline
          numberOfLines={2}
          style={{ marginTop: 20, height: 60, textAlignVertical: 'top' }}
        />

        <Button
          title="Créer la partie"
          onPress={handleCreate}
          loading={creating}
          style={{ marginTop: 20 }}
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  scroll: {
    padding: 20,
    paddingBottom: 40,
  },
  sectionTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 12,
  },
  items: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  chipSelected: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  chipText: {
    color: COLORS.textSecondary,
    fontSize: 14,
  },
  chipTextSelected: {
    color: COLORS.white,
    fontWeight: '600',
  },
  hint: {
    color: COLORS.textMuted,
    fontSize: 13,
    marginTop: 8,
  },
});
