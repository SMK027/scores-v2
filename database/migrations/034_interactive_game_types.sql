-- Migration 034: Types de jeux globaux pour les jeux interactifs (morpion, yams)
-- Les résultats contre de vrais joueurs seront comptabilisés dans le leaderboard.

INSERT INTO game_types (space_id, is_global, name, description, win_condition, min_players, max_players)
VALUES
    (NULL, 1, 'Morpion', 'Le classique Tic-Tac-Toe en ligne ! Alignez 3 symboles pour gagner.', 'win_loss', 2, 2),
    (NULL, 1, 'YAMS', 'Lancez 5 dés et réalisez les meilleures combinaisons !', 'highest_score', 1, 4);
