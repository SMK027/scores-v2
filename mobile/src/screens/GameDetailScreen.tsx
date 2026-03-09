import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
  TextInput,
  TouchableOpacity,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import {
  COLORS,
  GAME_STATUS_LABELS,
  GAME_STATUS_COLORS,
  WIN_CONDITION_LABELS,
} from '../utils/config';
import { games, rounds, comments } from '../services/api';
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

export default function GameDetailScreen({ navigation, route }: Props) {
  const { spaceId, gameId } = route.params;
  const { user } = useAuth();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [commentText, setCommentText] = useState('');
  const [sendingComment, setSendingComment] = useState(false);

  const fetchGame = useCallback(async () => {
    try {
      setError('');
      const result = await games.get(spaceId, gameId);
      setData(result);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [spaceId, gameId]);

  useFocusEffect(
    useCallback(() => {
      fetchGame();
    }, [fetchGame])
  );

  const handleStatusChange = (status: string) => {
    crossAlert(
      'Changer le statut',
      `Passer la partie en "${GAME_STATUS_LABELS[status]}" ?`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Confirmer',
          onPress: async () => {
            try {
              await games.updateStatus(spaceId, gameId, status);
              fetchGame();
            } catch (e: any) {
              showAlert('Erreur', e.message);
            }
          },
        },
      ]
    );
  };

  const handleAddRound = async () => {
    try {
      await rounds.create(spaceId, gameId);
      fetchGame();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    }
  };

  const handleDeleteRound = (roundId: number, roundNum: number) => {
    crossAlert('Supprimer', `Supprimer la manche ${roundNum} ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: async () => {
          try {
            await rounds.delete(spaceId, gameId, roundId);
            fetchGame();
          } catch (e: any) {
            showAlert('Erreur', e.message);
          }
        },
      },
    ]);
  };

  const handleAddComment = async () => {
    if (!commentText.trim()) return;
    setSendingComment(true);
    try {
      await comments.add(spaceId, gameId, commentText.trim());
      setCommentText('');
      fetchGame();
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setSendingComment(false);
    }
  };

  const handleDeleteComment = (commentId: number) => {
    crossAlert('Supprimer ce commentaire ?', '', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: async () => {
          try {
            await comments.delete(spaceId, gameId, commentId);
            fetchGame();
          } catch (e: any) {
            showAlert('Erreur', e.message);
          }
        },
      },
    ]);
  };

  const handleDeleteGame = () => {
    crossAlert(
      'Supprimer la partie',
      'Cette action est irréversible.',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer',
          style: 'destructive',
          onPress: async () => {
            try {
              await games.delete(spaceId, gameId);
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
  if (error) return <ErrorMessage message={error} onRetry={fetchGame} />;
  if (!data?.game) return <ErrorMessage message="Partie introuvable" />;

  const game = data.game;
  const gameRounds = data.rounds || [];
  const gamePlayers = data.players || [];
  const gameComments = data.comments || [];
  const roundScoresMap = data.round_scores || {};
  const winCondition = game.win_condition || 'highest_score';
  const isActive = game.status !== 'completed';

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.scroll}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => {
            setRefreshing(true);
            fetchGame();
          }}
          tintColor={COLORS.primary}
        />
      }
    >
      {/* En-tête */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <Text style={styles.title}>{game.game_type_name}</Text>
          <Badge
            label={GAME_STATUS_LABELS[game.status] || game.status}
            color={GAME_STATUS_COLORS[game.status] || COLORS.textMuted}
          />
        </View>
        <Text style={styles.meta}>
          {WIN_CONDITION_LABELS[winCondition]} · {gamePlayers.length} joueurs
        </Text>
        <Text style={styles.date}>
          Créée le {game.created_at?.substring(0, 16).replace('T', ' ')}
        </Text>
        {game.notes ? <Text style={styles.notes}>{game.notes}</Text> : null}
        {game.total_duration ? (
          <Text style={styles.duration}>Durée : {game.total_duration}</Text>
        ) : null}
      </View>

      {/* Contrôles de statut */}
      {isActive && (
        <View style={styles.statusBtns}>
          {game.status === 'pending' && (
            <Button
              title="Démarrer"
              onPress={() => handleStatusChange('in_progress')}
              variant="success"
              style={{ flex: 1, marginRight: 8 }}
            />
          )}
          {game.status === 'in_progress' && (
            <>
              <Button
                title="Pause"
                onPress={() => handleStatusChange('paused')}
                variant="outline"
                style={{ flex: 1, marginRight: 8 }}
              />
              <Button
                title="Terminer"
                onPress={() => handleStatusChange('completed')}
                variant="success"
                style={{ flex: 1 }}
              />
            </>
          )}
          {game.status === 'paused' && (
            <Button
              title="Reprendre"
              onPress={() => handleStatusChange('in_progress')}
              variant="primary"
              style={{ flex: 1 }}
            />
          )}
        </View>
      )}

      {/* Classement / Totaux */}
      {gamePlayers.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Classement</Text>
          {gamePlayers.map((t: any, i: number) => (
            <View key={t.player_id || i} style={styles.totalRow}>
              <Text style={styles.rank}>#{t.rank || i + 1}</Text>
              <Text style={styles.playerName}>{t.player_name}</Text>
              <Text style={styles.totalScore}>
                {winCondition === 'win_loss'
                  ? t.is_winner ? 'Gagnant' : `${Number(t.total_score || 0)}V`
                  : t.total_score ?? '-'}
              </Text>
            </View>
          ))}
        </View>
      )}

      {/* Manches */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>
            Manches ({gameRounds.length})
          </Text>
          {isActive && (
            <Button
              title="+ Manche"
              onPress={handleAddRound}
              variant="outline"
              style={{ paddingHorizontal: 12 }}
            />
          )}
        </View>

        {gameRounds.map((round: any, roundIndex: number) => (
          <Card key={round.id} style={{ marginBottom: 10 }}>
            <View style={styles.roundHeader}>
              <Text style={styles.roundTitle}>
                Manche {round.round_number || roundIndex + 1}
              </Text>
              <View style={styles.roundActions}>
                {isActive && (
                  <TouchableOpacity
                    onPress={() => {
                      const roundScoresObj = roundScoresMap[round.id] || {};
                      const roundScoresArr = Object.values(roundScoresObj);
                      navigation.navigate('ScoreEntry', {
                        spaceId,
                        gameId,
                        roundId: round.id,
                        roundNumber: round.round_number || roundIndex + 1,
                        winCondition,
                        playersList: gamePlayers,
                        scores: roundScoresArr,
                      });
                    }}
                    style={styles.roundActionBtn}
                  >
                    <Text style={styles.editScoreText}>Scores</Text>
                  </TouchableOpacity>
                )}
                {isActive && (
                  <TouchableOpacity
                    onPress={() =>
                      handleDeleteRound(
                        round.id,
                        round.round_number || roundIndex + 1
                      )
                    }
                    style={styles.roundActionBtn}
                  >
                    <Text style={styles.deleteText}>×</Text>
                  </TouchableOpacity>
                )}
              </View>
            </View>

            {/* Scores de la manche */}
            {(() => {
              const roundScoresObj = roundScoresMap[round.id] || {};
              const scoresList = Object.values(roundScoresObj) as any[];
              return scoresList.length > 0 ? (
                <View style={styles.scoresGrid}>
                  {scoresList.map((s: any) => (
                    <View key={s.player_id} style={styles.scoreItem}>
                      <Text style={styles.scorePlayer}>{s.player_name}</Text>
                      <Text style={styles.scoreValue}>
                        {winCondition === 'win_loss'
                          ? s.score === 1 || s.score === '1'
                            ? 'V'
                            : 'D'
                          : s.score ?? '-'}
                      </Text>
                    </View>
                  ))}
                </View>
              ) : (
                <Text style={styles.noScore}>Pas de scores enregistrés</Text>
              );
            })()}
          </Card>
        ))}

        {gameRounds.length === 0 && (
          <Text style={styles.emptyText}>Aucune manche pour le moment.</Text>
        )}
      </View>

      {/* Commentaires */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>
          Commentaires ({gameComments.length})
        </Text>

        <View style={styles.commentInput}>
          <TextInput
            style={styles.commentField}
            placeholder="Ajouter un commentaire..."
            placeholderTextColor={COLORS.textMuted}
            value={commentText}
            onChangeText={setCommentText}
            multiline
          />
          <Button
            title="Envoyer"
            onPress={handleAddComment}
            loading={sendingComment}
            disabled={!commentText.trim()}
            style={{ marginLeft: 8 }}
          />
        </View>

        {gameComments.map((c: any) => (
          <Card key={c.id} style={{ marginBottom: 8 }}>
            <View style={styles.commentRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.commentAuthor}>
                  {c.username || 'Utilisateur'}
                </Text>
                <Text style={styles.commentContent}>{c.content}</Text>
                <Text style={styles.commentDate}>
                  {c.created_at?.substring(0, 16).replace('T', ' ')}
                </Text>
              </View>
              {c.user_id === user?.id && (
                <TouchableOpacity onPress={() => handleDeleteComment(c.id)}>
                  <Text style={styles.deleteText}>×</Text>
                </TouchableOpacity>
              )}
            </View>
          </Card>
        ))}
      </View>

      {/* Actions */}
      <View style={styles.section}>
        <Button
          title="Supprimer la partie"
          onPress={handleDeleteGame}
          variant="danger"
        />
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
    marginBottom: 16,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  title: {
    color: COLORS.white,
    fontSize: 22,
    fontWeight: '700',
    flex: 1,
  },
  meta: {
    color: COLORS.textSecondary,
    fontSize: 14,
    marginTop: 4,
  },
  date: {
    color: COLORS.textMuted,
    fontSize: 12,
    marginTop: 2,
  },
  notes: {
    color: COLORS.textSecondary,
    fontSize: 13,
    marginTop: 8,
    fontStyle: 'italic',
  },
  duration: {
    color: COLORS.primary,
    fontSize: 13,
    marginTop: 4,
  },
  statusBtns: {
    flexDirection: 'row',
    marginBottom: 16,
  },
  section: {
    marginTop: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
  },
  totalRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    paddingHorizontal: 12,
    backgroundColor: COLORS.surface,
    borderRadius: 8,
    marginBottom: 4,
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
  totalScore: {
    color: COLORS.primary,
    fontSize: 16,
    fontWeight: '700',
  },
  roundHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  roundTitle: {
    color: COLORS.white,
    fontSize: 15,
    fontWeight: '600',
  },
  roundActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  roundActionBtn: {
    padding: 4,
  },
  editScoreText: {
    color: COLORS.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  deleteText: {
    color: COLORS.danger,
    fontSize: 22,
    fontWeight: '700',
  },
  scoresGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  scoreItem: {
    backgroundColor: COLORS.surfaceLight,
    borderRadius: 8,
    paddingVertical: 6,
    paddingHorizontal: 10,
    alignItems: 'center',
    minWidth: 80,
  },
  scorePlayer: {
    color: COLORS.textSecondary,
    fontSize: 11,
  },
  scoreValue: {
    color: COLORS.white,
    fontSize: 18,
    fontWeight: '700',
  },
  noScore: {
    color: COLORS.textMuted,
    fontSize: 13,
    fontStyle: 'italic',
  },
  commentInput: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    marginBottom: 12,
  },
  commentField: {
    flex: 1,
    backgroundColor: COLORS.surface,
    color: COLORS.text,
    borderRadius: 10,
    padding: 12,
    fontSize: 14,
    maxHeight: 80,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  commentRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  commentAuthor: {
    color: COLORS.primary,
    fontSize: 13,
    fontWeight: '600',
  },
  commentContent: {
    color: COLORS.text,
    fontSize: 14,
    marginTop: 2,
  },
  commentDate: {
    color: COLORS.textMuted,
    fontSize: 11,
    marginTop: 4,
  },
  emptyText: {
    color: COLORS.textSecondary,
    textAlign: 'center',
    fontSize: 14,
    marginTop: 12,
  },
});
