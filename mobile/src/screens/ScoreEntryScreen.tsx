import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  Switch,
} from 'react-native';
import { COLORS } from '../utils/config';
import { rounds } from '../services/api';
import { showAlert } from '../utils/alert';
import Input from '../components/Input';
import Button from '../components/Button';

interface Props {
  navigation: any;
  route: any;
}

export default function ScoreEntryScreen({ navigation, route }: Props) {
  const { spaceId, gameId, roundId, roundNumber, winCondition, playersList, scores } =
    route.params;

  // Initialiser les scores depuis les données existantes
  const initialScores: Record<string, string> = {};
  const initialWins: Record<string, boolean> = {};

  for (const p of playersList) {
    const existing = (scores || []).find(
      (s: any) => String(s.player_id) === String(p.player_id)
    );
    if (winCondition === 'win_loss') {
      initialWins[String(p.player_id)] = existing ? existing.score === 1 : false;
    } else {
      initialScores[String(p.player_id)] = existing?.score != null ? String(existing.score) : '';
    }
  }

  const [scoreValues, setScoreValues] = useState<Record<string, string>>(initialScores);
  const [winValues, setWinValues] = useState<Record<string, boolean>>(initialWins);
  const [loading, setLoading] = useState(false);

  const handleSave = async () => {
    const scoresPayload: Record<string, number> = {};

    for (const p of playersList) {
      const pid = String(p.player_id);
      if (winCondition === 'win_loss') {
        scoresPayload[pid] = winValues[pid] ? 1 : 0;
      } else {
        const val = scoreValues[pid];
        if (val === '' || val === undefined) {
          showAlert('Erreur', `Entrez un score pour ${p.player_name || p.name}.`);
          return;
        }
        const num = parseFloat(val);
        if (isNaN(num)) {
          showAlert('Erreur', `Score invalide pour ${p.player_name || p.name}.`);
          return;
        }
        scoresPayload[pid] = num;
      }
    }

    setLoading(true);
    try {
      await rounds.updateScores(spaceId, gameId, roundId, scoresPayload);
      showAlert('Succès', 'Scores enregistrés !');
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
        <Text style={styles.title}>Manche {roundNumber}</Text>
        <Text style={styles.subtitle}>
          {winCondition === 'win_loss'
            ? 'Indiquez qui a gagné'
            : winCondition === 'ranking'
            ? 'Entrez la position de chaque joueur (1 = premier)'
            : winCondition === 'lowest_score'
            ? 'Entrez les scores (le plus bas gagne)'
            : 'Entrez les scores (le plus élevé gagne)'}
        </Text>

        {playersList.map((player: any) => {
          const pid = String(player.player_id);
          const playerName = player.player_name || player.name || `Joueur #${player.player_id || player.id}`;

          if (winCondition === 'win_loss') {
            return (
              <View key={pid} style={styles.winRow}>
                <Text style={styles.playerName}>{playerName}</Text>
                <View style={styles.winToggle}>
                  <Text
                    style={[
                      styles.winLabel,
                      !winValues[pid] && styles.winLabelActive,
                    ]}
                  >
                    Défaite
                  </Text>
                  <Switch
                    value={winValues[pid] || false}
                    onValueChange={(val) =>
                      setWinValues((prev) => ({ ...prev, [pid]: val }))
                    }
                    trackColor={{ false: COLORS.danger, true: COLORS.success }}
                    thumbColor={COLORS.white}
                  />
                  <Text
                    style={[
                      styles.winLabel,
                      winValues[pid] && styles.winLabelActive,
                    ]}
                  >
                    Victoire
                  </Text>
                </View>
              </View>
            );
          }

          return (
            <View key={pid} style={styles.scoreRow}>
              <Text style={styles.playerName}>{playerName}</Text>
              <Input
                placeholder={winCondition === 'ranking' ? 'Position' : 'Score'}
                value={scoreValues[pid] || ''}
                onChangeText={(val: string) =>
                  setScoreValues((prev) => ({ ...prev, [pid]: val }))
                }
                keyboardType="numeric"
                style={styles.scoreInput}
              />
            </View>
          );
        })}

        <Button
          title="Enregistrer les scores"
          onPress={handleSave}
          loading={loading}
          style={{ marginTop: 24 }}
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
  title: {
    color: COLORS.white,
    fontSize: 22,
    fontWeight: '700',
    marginBottom: 4,
  },
  subtitle: {
    color: COLORS.textSecondary,
    fontSize: 14,
    marginBottom: 24,
  },
  scoreRow: {
    marginBottom: 16,
  },
  playerName: {
    color: COLORS.text,
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 6,
  },
  scoreInput: {
    fontSize: 20,
    textAlign: 'center',
  },
  winRow: {
    backgroundColor: COLORS.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
  },
  winToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 10,
    gap: 12,
  },
  winLabel: {
    color: COLORS.textMuted,
    fontSize: 14,
  },
  winLabelActive: {
    color: COLORS.white,
    fontWeight: '700',
  },
});
