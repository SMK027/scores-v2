import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { COLORS, WIN_CONDITION_LABELS } from '../utils/config';
import { gameTypes } from '../services/api';
import { showAlert } from '../utils/alert';
import Input from '../components/Input';
import Button from '../components/Button';

interface Props {
  navigation: any;
  route: any;
}

const WIN_CONDITIONS = ['highest_score', 'lowest_score', 'ranking', 'win_loss'];

export default function GameTypeFormScreen({ navigation, route }: Props) {
  const { spaceId, gameType } = route.params;
  const isEdit = !!gameType;

  const [name, setName] = useState(gameType?.name || '');
  const [description, setDescription] = useState(gameType?.description || '');
  const [winCondition, setWinCondition] = useState(
    gameType?.win_condition || 'highest_score'
  );
  const [minPlayers, setMinPlayers] = useState(
    String(gameType?.min_players ?? '2')
  );
  const [maxPlayers, setMaxPlayers] = useState(
    String(gameType?.max_players ?? '10')
  );
  const [loading, setLoading] = useState(false);

  const handleSave = async () => {
    if (!name.trim()) {
      showAlert('Erreur', 'Le nom est requis.');
      return;
    }
    setLoading(true);
    try {
      const payload = {
        name: name.trim(),
        description: description.trim(),
        win_condition: winCondition,
        min_players: parseInt(minPlayers) || 2,
        max_players: parseInt(maxPlayers) || 10,
      };
      if (isEdit) {
        await gameTypes.update(spaceId, gameType.id, payload);
        showAlert('Succès', 'Type de jeu modifié.');
      } else {
        await gameTypes.create(spaceId, payload);
        showAlert('Succès', 'Type de jeu créé.');
      }
      navigation.goBack();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={styles.container}
    >
      <ScrollView contentContainerStyle={styles.scroll}>
        <Input
          label="Nom du type de jeu"
          placeholder="ex: Uno, Tarot, Échecs..."
          value={name}
          onChangeText={setName}
          maxLength={100}
        />
        <Input
          label="Description (optionnel)"
          placeholder="Décrivez les règles..."
          value={description}
          onChangeText={setDescription}
          multiline
          numberOfLines={3}
          style={{ height: 80, textAlignVertical: 'top' }}
        />

        {/* Sélection condition de victoire */}
        <Text style={styles.label}>Condition de victoire</Text>
        <View style={styles.pills}>
          {WIN_CONDITIONS.map((wc) => (
            <Button
              key={wc}
              title={WIN_CONDITION_LABELS[wc]}
              onPress={() => setWinCondition(wc)}
              variant={winCondition === wc ? 'primary' : 'outline'}
              style={styles.pill}
            />
          ))}
        </View>

        <View style={styles.row}>
          <View style={{ flex: 1, marginRight: 8 }}>
            <Input
              label="Min. joueurs"
              value={minPlayers}
              onChangeText={setMinPlayers}
              keyboardType="number-pad"
            />
          </View>
          <View style={{ flex: 1 }}>
            <Input
              label="Max. joueurs"
              value={maxPlayers}
              onChangeText={setMaxPlayers}
              keyboardType="number-pad"
            />
          </View>
        </View>

        <Button
          title={isEdit ? 'Enregistrer' : 'Créer'}
          onPress={handleSave}
          loading={loading}
          style={{ marginTop: 12 }}
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
  },
  label: {
    color: COLORS.text,
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 8,
    marginTop: 8,
  },
  pills: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 16,
  },
  pill: {
    paddingHorizontal: 12,
  },
  row: {
    flexDirection: 'row',
  },
});
