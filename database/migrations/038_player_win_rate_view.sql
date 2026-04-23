-- -----------------------------------------------------------
-- Migration 038 : Vue du taux de victoire global par joueur
-- Calcule le ratio manches gagnées / manches totales jouées
-- pour chaque joueur actif, toutes parties terminées confondues.
--
-- Colonnes exposées :
--   player_id           : identifiant du joueur
--   player_name         : nom du joueur
--   space_id            : espace auquel appartient le joueur
--   total_rounds_played : nombre de manches complétées jouées
--   rounds_won          : nombre de manches remportées
--   win_rate_percent    : taux de victoire en % (0-100, arrondi à 2 décimales)
--
-- Règles de victoire appliquées selon game_types.win_condition :
--   highest_score → le(s) joueur(s) avec le score le plus élevé remportent la manche
--   lowest_score  → le(s) joueur(s) avec le score le plus bas remportent la manche
--   win_loss      → un score de 1 indique une victoire (0 = défaite)
--   ranking       → même logique que highest_score (score le plus élevé gagne)
--
-- Seules les manches au statut 'completed' appartenant à des parties
-- au statut 'completed' sont prises en compte.
-- Les joueurs supprimés (soft-delete) sont exclus.
-- -----------------------------------------------------------

CREATE OR REPLACE VIEW `v_player_win_rate` AS
WITH round_winning_scores AS (
    -- Détermine le score gagnant de chaque manche complétée
    -- selon la condition de victoire du type de jeu associé
    SELECT
        r.id           AS round_id,
        gt.win_condition,
        CASE
            WHEN gt.win_condition = 'lowest_score' THEN MIN(rs.score)
            ELSE                                        MAX(rs.score)
        END            AS winning_score
    FROM rounds       r
    JOIN games        g  ON g.id  = r.game_id
    JOIN game_types   gt ON gt.id = g.game_type_id
    JOIN round_scores rs ON rs.round_id = r.id
    WHERE r.status = 'completed'
      AND g.status = 'completed'
    GROUP BY r.id, gt.win_condition
),
player_round_results AS (
    -- Pour chaque participation à une manche complétée,
    -- détermine si le joueur en est sorti vainqueur
    SELECT
        rs.player_id,
        rs.round_id,
        CASE
            -- win_loss : victoire explicitement codée par un score de 1
            WHEN rws.win_condition = 'win_loss' THEN IF(rs.score = 1, 1, 0)
            -- autres conditions : comparaison au score gagnant de la manche
            ELSE                                     IF(rs.score = rws.winning_score, 1, 0)
        END AS is_winner
    FROM round_scores         rs
    JOIN round_winning_scores rws ON rws.round_id = rs.round_id
)
SELECT
    p.id                                                         AS player_id,
    p.name                                                       AS player_name,
    p.space_id,
    COUNT(prr.round_id)                                          AS total_rounds_played,
    COALESCE(SUM(prr.is_winner), 0)                             AS rounds_won,
    ROUND(
        CASE
            WHEN COUNT(prr.round_id) = 0 THEN 0
            ELSE SUM(prr.is_winner) * 100.0 / COUNT(prr.round_id)
        END,
        2
    )                                                            AS win_rate_percent
FROM players               p
LEFT JOIN player_round_results prr ON prr.player_id = p.id
WHERE p.deleted_at IS NULL
GROUP BY p.id, p.name, p.space_id;
