/**
 * Service de synchronisation hors connexion
 *
 * Traite la file d'attente offline_queue dans l'ordre (FIFO).
 * Les dépendances entre actions sont résolues via une table de correspondance
 * tempId → realId mise à jour au fil de la synchronisation.
 */

import { createGame, createRound, updateRoundScores } from "./api";
import {
  dequeue,
  getQueue,
  incrementAttempts,
  type QueueEntry,
} from "./db";

const MAX_ATTEMPTS = 5;

export type SyncResult = {
  synced: number;
  failed: number;
  errors: string[];
};

/**
 * Traite toutes les entrées de la file en attente.
 * Retourne un résumé des opérations effectuées.
 */
export async function processQueue(token: string): Promise<SyncResult> {
  const queue = await getQueue();
  if (queue.length === 0) return { synced: 0, failed: 0, errors: [] };

  // Table de correspondance tempId → realId (résolution des dépendances)
  const idMap = new Map<string, number>();

  let synced = 0;
  let failed = 0;
  const errors: string[] = [];

  for (const entry of queue) {
    if (entry.attempts >= MAX_ATTEMPTS) {
      // Trop de tentatives : on abandonne silencieusement cette entrée
      await dequeue(entry.id);
      failed++;
      errors.push(`Action #${entry.id} abandonnée après ${MAX_ATTEMPTS} tentatives.`);
      continue;
    }

    try {
      await processEntry(entry, idMap, token);
      await dequeue(entry.id);
      synced++;
    } catch (err) {
      await incrementAttempts(entry.id);
      failed++;
      errors.push(
        err instanceof Error ? err.message : `Erreur inconnue sur l'action #${entry.id}`,
      );
    }
  }

  return { synced, failed, errors };
}

async function processEntry(
  entry: QueueEntry,
  idMap: Map<string, number>,
  token: string,
): Promise<void> {
  const action = entry.action;

  switch (action.type) {
    case "create_game": {
      const game = await createGame(token, action.spaceId, {
        gameTypeId: action.gameTypeId,
        playerIds: action.playerIds,
        notes: action.notes,
      });
      // Enregistrer la correspondance tempId → real game.id
      idMap.set(action.tempId, game.id);
      break;
    }

    case "create_round": {
      // Résoudre l'éventuel tempId de la partie
      const realGameId =
        typeof action.gameId === "string"
          ? (idMap.get(action.gameId) ?? null)
          : action.gameId;

      if (realGameId === null) {
        throw new Error(`Impossible de résoudre l'ID de partie pour la manche (tempId: ${String(action.gameId)})`);
      }

      const roundId = await createRound(token, action.spaceId, realGameId);
      idMap.set(action.tempId, roundId);
      break;
    }

    case "update_scores": {
      const realGameId =
        typeof action.gameId === "string"
          ? (idMap.get(action.gameId) ?? null)
          : action.gameId;
      const realRoundId =
        typeof action.roundId === "string"
          ? (idMap.get(action.roundId) ?? null)
          : action.roundId;

      if (realGameId === null || realRoundId === null) {
        throw new Error(
          `Impossible de résoudre les IDs pour la saisie de scores (gameId: ${String(action.gameId)}, roundId: ${String(action.roundId)})`,
        );
      }

      const normalizedScores: Record<number, number> = {};
      for (const [pid, score] of Object.entries(action.scores)) {
        if (score !== null) normalizedScores[Number(pid)] = score;
      }

      await updateRoundScores(token, action.spaceId, realGameId, realRoundId, normalizedScores);
      break;
    }

    default:
      // Action inconnue : on la supprime pour ne pas bloquer la file
      break;
  }
}
