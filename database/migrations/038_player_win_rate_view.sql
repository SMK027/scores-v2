-- -----------------------------------------------------------
-- Migration 038 : Vue du taux de victoire global par compte utilisateur
-- Calcule le ratio manches gagnées / manches totales jouées
-- pour chaque compte utilisateur, en consolidant tous les joueurs
-- rattachés à ce compte (players.user_id) toutes parties et espaces
-- confondus.
--
-- Un même utilisateur peut être représenté par plusieurs joueurs dans
-- des espaces différents : tous leurs résultats sont agrégés.
--
-- Colonnes exposées :
--   user_id             : identifiant du compte utilisateur
--   username            : nom d'utilisateur
--   total_rounds_played : nombre de manches complétées jouées (tous espaces)
--   rounds_won          : nombre de manches remportées
--   win_rate_percent    : taux de victoire en % (0-100, arrondi à 2 décimales)
--
-- Règles de victoire appliquées selon game_types.win_condition :
--   highest_score → le(s) joueur(s) avec le score le plus élevé remportent la manche
--   lowest_score  → le(s) joueur(s) avec le score le plus bas remportent la manche
--   win_loss      → le(s) joueur(s) avec le score le plus élevé (1) remportent la manche
--   ranking       → le(s) joueur(s) avec le score le plus bas remportent la manche
--
-- Seules les manches au statut 'completed' sont prises en compte
-- (indépendamment du statut de la partie — identique à computeGlobalWinRate).
-- L'utilisateur doit être membre de l'espace du joueur (space_members).
-- Les joueurs supprimés (soft-delete) et ceux sans compte lié sont exclus.
-- Filtrer sur un compte précis : WHERE user_id = ?
-- -----------------------------------------------------------

CREATE OR REPLACE VIEW `v_user_win_rate` AS
WITH round_winning_scores AS (
    -- Détermine le score gagnant de chaque manche complétée
    -- selon la condition de victoire du type de jeu associé
    SELECT
        r.id           AS round_id,
        gt.win_condition,
        CASE
            -- ranking et lowest_score : le score le plus bas gagne
            WHEN gt.win_condition IN ('lowest_score', 'ranking') THEN MIN(rs.score)
            -- highest_score et win_loss : le score le plus élevé gagne
            ELSE                                                       MAX(rs.score)
        END            AS winning_score
    FROM rounds       r
    JOIN games        g  ON g.id  = r.game_id
    JOIN game_types   gt ON gt.id = g.game_type_id
    JOIN round_scores rs ON rs.round_id = r.id
    WHERE r.status = 'completed'
    GROUP BY r.id, gt.win_condition
),
player_round_results AS (
    -- Pour chaque participation à une manche complétée,
    -- détermine si le joueur en est sorti vainqueur
    -- (victoire = score égal au score gagnant de la manche)
    SELECT
        rs.player_id,
        rs.round_id,
        IF(rs.score = rws.winning_score, 1, 0) AS is_winner
    FROM round_scores         rs
    JOIN round_winning_scores rws ON rws.round_id = rs.round_id
)
SELECT
    u.id                                                         AS user_id,
    u.username,
    COUNT(prr.round_id)                                          AS total_rounds_played,
    COALESCE(SUM(prr.is_winner), 0)                             AS rounds_won,
    ROUND(
        CASE
            WHEN COUNT(prr.round_id) = 0 THEN 0
            ELSE SUM(prr.is_winner) * 100.0 / COUNT(prr.round_id)
        END,
        2
    )                                                            AS win_rate_percent
FROM users                   u
JOIN players                 p   ON p.user_id    = u.id
                                 AND p.deleted_at IS NULL
JOIN space_members           sm  ON sm.space_id  = p.space_id
                                 AND sm.user_id   = u.id
LEFT JOIN player_round_results prr ON prr.player_id = p.id
GROUP BY u.id, u.username;
