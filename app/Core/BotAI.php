<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Intelligence artificielle pour les jeux interactifs (morpion et YAMS).
 */
class BotAI
{
    public const DIFFICULTY_EASY   = 'easy';
    public const DIFFICULTY_MEDIUM = 'medium';
    public const DIFFICULTY_HARD   = 'hard';

    public const DIFFICULTIES = [
        self::DIFFICULTY_EASY   => '🟢 Facile',
        self::DIFFICULTY_MEDIUM => '🟡 Moyen',
        self::DIFFICULTY_HARD   => '🔴 Difficile',
    ];

    // ═══════════════════════════════════════════════════════════════
    //  MORPION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Retourne la profondeur max de minimax selon la taille de grille et l'alignement.
     * Quand alignCount est faible vs gridSize, le jeu se termine plus vite → on peut explorer plus.
     */
    private static function getMaxDepth(int $gridSize, int $alignCount): int
    {
        return match (true) {
            $gridSize === 3                          => 100, // 3×3 align 3 : résolution complète
            $gridSize === 4 && $alignCount === 3     => 6,   // 4×4 align 3 : 24 lignes
            $gridSize === 4 && $alignCount >= 4      => 6,   // 4×4 align 4 : 10 lignes
            $gridSize === 5 && $alignCount === 3     => 4,   // 5×5 align 3 : 48 lignes
            $gridSize === 5 && $alignCount >= 4      => 4,   // 5×5 align 4 : 24 lignes
            default                                  => 6,
        };
    }

    /**
     * Retourne l'index de la meilleure case à jouer.
     */
    public static function morpionMove(array $board, string $botSymbol, string $difficulty = self::DIFFICULTY_HARD, int $gridSize = 3, int $alignCount = 3): int
    {
        return match ($difficulty) {
            self::DIFFICULTY_EASY   => self::morpionMoveEasy($board, $gridSize),
            self::DIFFICULTY_MEDIUM => self::morpionMoveMedium($board, $botSymbol, $gridSize, $alignCount),
            default                => self::morpionMoveHard($board, $botSymbol, $gridSize, $alignCount),
        };
    }

    /**
     * Facile : coup aléatoire pondéré.
     * Sur grilles > 3×3, préfère les cases proches du centre et des pièces existantes
     * pour éviter un jeu trop absurde, tout en restant imprévisible.
     */
    private static function morpionMoveEasy(array $board, int $gridSize, int $alignCount = 0): int
    {
        $total = $gridSize * $gridSize;
        $free = [];
        for ($i = 0; $i < $total; $i++) {
            if ($board[$i] === null) {
                $free[] = $i;
            }
        }

        // Sur 3×3, purement aléatoire (le jeu est simple)
        if ($gridSize <= 3) {
            return $free[array_rand($free)];
        }

        // Sur grilles plus grandes : pondération vers le centre et les voisins occupés
        $center = ($gridSize - 1) / 2.0;
        $maxDist = $center * M_SQRT2;
        $weights = [];
        foreach ($free as $idx) {
            $r = intdiv($idx, $gridSize);
            $c = $idx % $gridSize;
            // Bonus proximité centre (1.0 au centre, ~0.3 aux coins)
            $dist = sqrt(($r - $center) ** 2 + ($c - $center) ** 2);
            $w = 1.0 + (1.0 - $dist / $maxDist);
            // Bonus voisinage : chaque case adjacente occupée ajoute du poids
            foreach ([[-1,0],[1,0],[0,-1],[0,1],[-1,-1],[-1,1],[1,-1],[1,1]] as [$dr,$dc]) {
                $nr = $r + $dr;
                $nc = $c + $dc;
                if ($nr >= 0 && $nr < $gridSize && $nc >= 0 && $nc < $gridSize) {
                    if ($board[$nr * $gridSize + $nc] !== null) {
                        $w += 0.5;
                    }
                }
            }
            $weights[] = $w;
        }

        // Sélection pondérée
        $sum = array_sum($weights);
        $rand = mt_rand() / mt_getrandmax() * $sum;
        $cumul = 0;
        foreach ($free as $i => $idx) {
            $cumul += $weights[$i];
            if ($cumul >= $rand) {
                return $idx;
            }
        }
        return $free[count($free) - 1];
    }

    /**
     * Moyen : minimax la plupart du temps, coup aléatoire occasionnel.
     * Le pourcentage d'aléatoire varie : plus la combinaison est complexe, plus le bot
     * peut se permettre d'être aléatoire sans que ça paraisse trop faible.
     */
    private static function morpionMoveMedium(array $board, string $botSymbol, int $gridSize, int $alignCount): int
    {
        // Plus la grille est grande et l'alignement court, plus il faut être précis
        // car les parties se gagnent vite avec peu d'alignement.
        $randomPct = match (true) {
            $gridSize === 3                          => 40, // Classique : 40 % aléatoire
            $gridSize === 4 && $alignCount === 3     => 25, // Align 3 sur 4×4 → parties rapides, moins d'erreurs
            $gridSize === 4 && $alignCount >= 4      => 35, // Align 4 sur 4×4 → un peu plus de marge
            $gridSize === 5 && $alignCount === 3     => 20, // Align 3 sur 5×5 → très tactique
            $gridSize === 5 && $alignCount >= 4      => 30, // Align 4 sur 5×5 → stratégique
            default                                  => 35,
        };

        if (random_int(1, 100) <= $randomPct) {
            return self::morpionMoveEasy($board, $gridSize, $alignCount);
        }
        return self::morpionMoveHard($board, $botSymbol, $gridSize, $alignCount);
    }

    /**
     * Difficile : minimax avec élagage alpha-bêta et tri des coups.
     */
    private static function morpionMoveHard(array $board, string $botSymbol, int $gridSize, int $alignCount): int
    {
        $opponent = $botSymbol === 'X' ? 'O' : 'X';
        $lines = self::generateWinLines($gridSize, $alignCount);
        $maxDepth = self::getMaxDepth($gridSize, $alignCount);
        $bestScore = -100000;
        $bestMove = -1;

        // Tri des coups pour améliorer l'élagage α-β :
        // 1) Centre, 2) Cases menaçantes, 3) Cases avec voisins
        $candidates = self::orderMoves($board, $botSymbol, $lines, $gridSize);

        foreach ($candidates as $i) {
            $board[$i] = $botSymbol;
            $score = self::minimax($board, false, $botSymbol, $opponent, $lines, $gridSize, $maxDepth - 1, -100000, 100000);
            $board[$i] = null;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMove = $i;
            }
        }

        return $bestMove;
    }

    /**
     * Trie les cases libres par pertinence pour favoriser les coupures alpha-bêta.
     * @return int[] indices triés par score décroissant
     */
    private static function orderMoves(array $board, string $bot, array $lines, int $gridSize): array
    {
        $opp = $bot === 'X' ? 'O' : 'X';
        $total = $gridSize * $gridSize;
        $center = ($gridSize - 1) / 2.0;
        $maxDist = $center * M_SQRT2;
        $scores = [];

        // Pré-calculer la participation de chaque case aux lignes
        $cellLines = array_fill(0, $total, []);
        foreach ($lines as $li => $line) {
            foreach ($line as $idx) {
                $cellLines[$idx][] = $li;
            }
        }

        for ($i = 0; $i < $total; $i++) {
            if ($board[$i] !== null) continue;

            $s = 0.0;
            // Bonus centre
            $r = intdiv($i, $gridSize);
            $c = $i % $gridSize;
            $dist = sqrt(($r - $center) ** 2 + ($c - $center) ** 2);
            $s += 3.0 * (1.0 - $dist / $maxDist);

            // Évaluer les lignes traversant cette case
            foreach ($cellLines[$i] as $li) {
                $line = $lines[$li];
                $botCount = 0;
                $oppCount = 0;
                foreach ($line as $idx) {
                    if ($board[$idx] === $bot) $botCount++;
                    elseif ($board[$idx] === $opp) $oppCount++;
                }
                $lineLen = count($line);
                // Coup gagnant → priorité maximale
                if ($botCount === $lineLen - 1 && $oppCount === 0) $s += 1000;
                // Bloquer victoire adverse → très haute priorité
                elseif ($oppCount === $lineLen - 1 && $botCount === 0) $s += 900;
                // Prolonger une séquence bot
                elseif ($oppCount === 0 && $botCount > 0) $s += $botCount * 5;
                // Couper une séquence adverse
                elseif ($botCount === 0 && $oppCount > 0) $s += $oppCount * 4;
            }

            $scores[$i] = $s;
        }

        arsort($scores);
        return array_keys($scores);
    }

    private static function minimax(array $board, bool $isMaximizing, string $bot, string $opp, array $lines, int $gridSize, int $depth, int $alpha, int $beta): int
    {
        $winner = self::checkWinner($board, $lines);
        if ($winner === $bot) {
            return 1000 + $depth;
        }
        if ($winner === $opp) {
            return -1000 - $depth;
        }

        $total = $gridSize * $gridSize;
        if (!in_array(null, $board, true)) {
            return 0;
        }
        if ($depth <= 0) {
            return self::evaluateBoard($board, $bot, $opp, $lines);
        }

        // Tri léger : coups gagnants/bloquants d'abord, puis le reste
        $player = $isMaximizing ? $bot : $opp;
        $moves = self::quickOrderMoves($board, $player, $player === $bot ? $opp : $bot, $lines, $total);

        if ($isMaximizing) {
            $best = -100000;
            foreach ($moves as $i) {
                $board[$i] = $bot;
                $score = self::minimax($board, false, $bot, $opp, $lines, $gridSize, $depth - 1, $alpha, $beta);
                $board[$i] = null;
                $best = max($best, $score);
                $alpha = max($alpha, $best);
                if ($beta <= $alpha) break;
            }
            return $best;
        }

        $best = 100000;
        foreach ($moves as $i) {
            $board[$i] = $opp;
            $score = self::minimax($board, true, $bot, $opp, $lines, $gridSize, $depth - 1, $alpha, $beta);
            $board[$i] = null;
            $best = min($best, $score);
            $beta = min($beta, $best);
            if ($beta <= $alpha) break;
        }
        return $best;
    }

    /**
     * Tri rapide des coups : gagnants → bloquants → reste.
     * Beaucoup plus léger que orderMoves(), O(L) au lieu de O(N*L).
     * @return int[]
     */
    private static function quickOrderMoves(array $board, string $me, string $them, array $lines, int $total): array
    {
        $winMoves = [];
        $blockMoves = [];

        foreach ($lines as $line) {
            $myCount = 0;
            $theirCount = 0;
            $emptyIdx = -1;
            $empties = 0;
            foreach ($line as $idx) {
                if ($board[$idx] === $me) $myCount++;
                elseif ($board[$idx] === $them) $theirCount++;
                else { $emptyIdx = $idx; $empties++; }
            }
            // Coup gagnant : il ne reste qu'une case vide et le reste est à moi
            if ($empties === 1 && $myCount === count($line) - 1 && $theirCount === 0) {
                $winMoves[$emptyIdx] = true;
            }
            // Coup bloquant : l'adversaire est à un coup de gagner
            if ($empties === 1 && $theirCount === count($line) - 1 && $myCount === 0) {
                $blockMoves[$emptyIdx] = true;
            }
        }

        $result = array_keys($winMoves);
        foreach (array_keys($blockMoves) as $idx) {
            if (!isset($winMoves[$idx])) $result[] = $idx;
        }
        $seen = $winMoves + $blockMoves;
        for ($i = 0; $i < $total; $i++) {
            if ($board[$i] === null && !isset($seen[$i])) {
                $result[] = $i;
            }
        }
        return $result;
    }

    /**
     * Évaluation heuristique quand la profondeur max est atteinte.
     * Pondère exponentiellement les lignes proches de la victoire
     * et ajoute un bonus/malus fort pour les menaces imminentes.
     */
    private static function evaluateBoard(array $board, string $bot, string $opp, array $lines): int
    {
        $score = 0;
        $lineLen = count($lines[0] ?? []);

        foreach ($lines as $line) {
            $botCount = 0;
            $oppCount = 0;
            foreach ($line as $idx) {
                if ($board[$idx] === $bot) $botCount++;
                elseif ($board[$idx] === $opp) $oppCount++;
            }

            // Ligne bloquée (les deux joueurs y sont) → sans intérêt
            if ($botCount > 0 && $oppCount > 0) {
                continue;
            }

            if ($botCount > 0) {
                // Victoire imminente : alignCount - 1 symboles → très forte valeur
                if ($botCount === $lineLen - 1) {
                    $score += 500;
                } else {
                    // Progression exponentielle : 1→1, 2→4, 3→27...
                    $score += (int) ($botCount ** 3);
                }
            } elseif ($oppCount > 0) {
                // Menace adverse imminente → forte pénalité (bloquer !)
                if ($oppCount === $lineLen - 1) {
                    $score -= 400;
                } else {
                    $score -= (int) ($oppCount ** 3);
                }
            }
        }
        return $score;
    }

    /**
     * Génère les lignes gagnantes pour grille NxN avec alignement K.
     */
    private static function generateWinLines(int $gridSize, int $alignCount): array
    {
        $lines = [];
        $n = $gridSize;
        $k = $alignCount;

        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c <= $n - $k; $c++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) $line[] = $r * $n + ($c + $i);
                $lines[] = $line;
            }
        }
        for ($c = 0; $c < $n; $c++) {
            for ($r = 0; $r <= $n - $k; $r++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) $line[] = ($r + $i) * $n + $c;
                $lines[] = $line;
            }
        }
        for ($r = 0; $r <= $n - $k; $r++) {
            for ($c = 0; $c <= $n - $k; $c++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) $line[] = ($r + $i) * $n + ($c + $i);
                $lines[] = $line;
            }
        }
        for ($r = 0; $r <= $n - $k; $r++) {
            for ($c = $k - 1; $c < $n; $c++) {
                $line = [];
                for ($i = 0; $i < $k; $i++) $line[] = ($r + $i) * $n + ($c - $i);
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function checkWinner(array $board, array $lines): ?string
    {
        foreach ($lines as $line) {
            $first = $board[$line[0]];
            if ($first === null) continue;
            $win = true;
            for ($i = 1, $len = count($line); $i < $len; $i++) {
                if ($board[$line[$i]] !== $first) {
                    $win = false;
                    break;
                }
            }
            if ($win) return $first;
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    //  YAMS — Stratégie heuristique
    // ═══════════════════════════════════════════════════════════════

    /**
     * Joue un tour complet de YAMS (3 lancers + choix de catégorie).
     * @return array{dice: int[], category: string}
     */
    public static function yamsTurn(array $state, string $playerKey, string $difficulty = self::DIFFICULTY_HARD): array
    {
        return match ($difficulty) {
            self::DIFFICULTY_EASY   => self::yamsTurnEasy($state, $playerKey),
            self::DIFFICULTY_MEDIUM => self::yamsTurnMedium($state, $playerKey),
            default                => self::yamsTurnHard($state, $playerKey),
        };
    }

    /**
     * YAMS facile : ne garde aucun dé, choisit une catégorie aléatoire.
     */
    private static function yamsTurnEasy(array $state, string $playerKey): array
    {
        $scores = $state['scores'][$playerKey] ?? [];
        $dice = self::rollAll();
        $dice = self::rollAll(); // relance tout 2 fois
        $dice = self::rollAll(); // relance tout 3 fois

        $category = self::yamsRandomCategory($scores);

        return ['dice' => $dice, 'category' => $category];
    }

    /**
     * YAMS moyen : stratégie correcte pour les dés, mais choix de catégorie parfois sous-optimal.
     */
    private static function yamsTurnMedium(array $state, string $playerKey): array
    {
        $scores = $state['scores'][$playerKey] ?? [];
        $dice = self::rollAll();

        $kept = self::yamsKeepStrategy($dice, $scores);
        $dice = self::reroll($dice, $kept);

        $kept = self::yamsKeepStrategy($dice, $scores);
        $dice = self::reroll($dice, $kept);

        // 35 % de chance de choisir une catégorie aléatoire au lieu de la meilleure
        if (random_int(1, 100) <= 35) {
            $category = self::yamsRandomCategory($scores);
        } else {
            $category = self::yamsBestCategory($dice, $scores);
        }

        return ['dice' => $dice, 'category' => $category];
    }

    /**
     * YAMS difficile : stratégie optimale (original).
     */
    private static function yamsTurnHard(array $state, string $playerKey): array
    {
        $scores = $state['scores'][$playerKey] ?? [];

        // Lancer 1 : tous les dés
        $dice = self::rollAll();

        // Lancer 2 : garder les meilleurs, relancer le reste
        $kept = self::yamsKeepStrategy($dice, $scores);
        $dice = self::reroll($dice, $kept);

        // Lancer 3 : idem
        $kept = self::yamsKeepStrategy($dice, $scores);
        $dice = self::reroll($dice, $kept);

        // Choisir la meilleure catégorie disponible
        $category = self::yamsBestCategory($dice, $scores);

        return ['dice' => $dice, 'category' => $category];
    }

    // ── Helpers Dés ────────────────────────────────────────────

    /**
     * Choisit une catégorie aléatoire parmi celles encore disponibles.
     */
    private static function yamsRandomCategory(array $playerScores): string
    {
        $allCats = [
            'ones', 'twos', 'threes', 'fours', 'fives', 'sixes',
            'three_of_kind', 'four_of_kind', 'full_house',
            'small_straight', 'large_straight', 'yams', 'chance',
        ];

        $available = [];
        foreach ($allCats as $cat) {
            if (!isset($playerScores[$cat])) {
                $available[] = $cat;
            }
        }

        if (empty($available)) {
            return 'chance';
        }

        return $available[array_rand($available)];
    }

    private static function rollAll(): array
    {
        $dice = [];
        for ($i = 0; $i < 5; $i++) {
            $dice[] = random_int(1, 6);
        }
        return $dice;
    }

    private static function reroll(array $dice, array $kept): array
    {
        for ($i = 0; $i < 5; $i++) {
            if (empty($kept[$i])) {
                $dice[$i] = random_int(1, 6);
            }
        }
        return $dice;
    }

    // ── Stratégie : quels dés garder ──────────────────────────

    private static function yamsKeepStrategy(array $dice, array $playerScores): array
    {
        $counts = array_count_values($dice);
        arsort($counts);
        $maxCount = max($counts);
        $maxValue = array_key_first($counts);
        $kept = [false, false, false, false, false];

        // 4 ou 5 identiques → garder tous les identiques (viser YAMS / carré)
        if ($maxCount >= 4) {
            for ($i = 0; $i < 5; $i++) {
                if ($dice[$i] === $maxValue) {
                    $kept[$i] = true;
                }
            }
            return $kept;
        }

        // 3 identiques + paire → full house, tout garder
        if ($maxCount === 3 && count($counts) === 2) {
            return [true, true, true, true, true];
        }

        // 3 identiques → garder le brelan
        if ($maxCount === 3) {
            for ($i = 0; $i < 5; $i++) {
                if ($dice[$i] === $maxValue) {
                    $kept[$i] = true;
                }
            }
            return $kept;
        }

        // Suite potentielle (≥ 4 valeurs consécutives)
        $seq = self::longestConsecutive($dice);
        if (count($seq) >= 4) {
            $seqSet = array_flip($seq);
            $used = [];
            for ($i = 0; $i < 5; $i++) {
                if (isset($seqSet[$dice[$i]]) && !isset($used[$dice[$i]])) {
                    $kept[$i] = true;
                    $used[$dice[$i]] = true;
                }
            }
            return $kept;
        }

        // Paire → garder la plus haute paire
        if ($maxCount === 2) {
            for ($i = 0; $i < 5; $i++) {
                if ($dice[$i] === $maxValue) {
                    $kept[$i] = true;
                }
            }
            return $kept;
        }

        // Rien d'intéressant → garder le dé le plus haut
        $maxDie = max($dice);
        for ($i = 0; $i < 5; $i++) {
            if ($dice[$i] === $maxDie) {
                $kept[$i] = true;
                break;
            }
        }
        return $kept;
    }

    private static function longestConsecutive(array $dice): array
    {
        $unique = array_values(array_unique($dice));
        sort($unique);
        $best = [$unique[0]];
        $current = [$unique[0]];
        for ($i = 1, $n = count($unique); $i < $n; $i++) {
            if ($unique[$i] === $unique[$i - 1] + 1) {
                $current[] = $unique[$i];
                if (count($current) > count($best)) {
                    $best = $current;
                }
            } else {
                $current = [$unique[$i]];
            }
        }
        return $best;
    }

    // ── Stratégie : choix de catégorie ────────────────────────

    private static function yamsBestCategory(array $dice, array $playerScores): string
    {
        $allCats = [
            'ones', 'twos', 'threes', 'fours', 'fives', 'sixes',
            'three_of_kind', 'four_of_kind', 'full_house',
            'small_straight', 'large_straight', 'yams', 'chance',
        ];

        $available = [];
        foreach ($allCats as $cat) {
            if (!isset($playerScores[$cat])) {
                $available[$cat] = self::calculateScore($cat, $dice);
            }
        }

        if (empty($available)) {
            return 'chance';
        }

        $maxScore = max($available);

        if ($maxScore > 0) {
            // Parmi les meilleurs scores, préférer les catégories les plus rares
            $priority = [
                'yams', 'large_straight', 'small_straight', 'full_house',
                'four_of_kind', 'three_of_kind',
                'sixes', 'fives', 'fours', 'threes', 'twos', 'ones', 'chance',
            ];
            foreach ($priority as $cat) {
                if (isset($available[$cat]) && $available[$cat] === $maxScore) {
                    return $cat;
                }
            }
            arsort($available);
            return array_key_first($available);
        }

        // Tous à 0 → sacrifier la catégorie la moins dommageable
        $sacrifice = [
            'yams', 'large_straight', 'ones', 'twos', 'small_straight',
            'threes', 'full_house', 'four_of_kind', 'three_of_kind',
            'fours', 'fives', 'sixes', 'chance',
        ];
        foreach ($sacrifice as $cat) {
            if (isset($available[$cat])) {
                return $cat;
            }
        }

        return array_key_first($available);
    }

    // ── Calcul de score YAMS ──────────────────────────────────

    public static function calculateScore(string $category, array $dice): int
    {
        $counts = array_count_values($dice);
        $sum = array_sum($dice);
        $sorted = $dice;
        sort($sorted);

        return match ($category) {
            'ones'   => ($counts[1] ?? 0) * 1,
            'twos'   => ($counts[2] ?? 0) * 2,
            'threes' => ($counts[3] ?? 0) * 3,
            'fours'  => ($counts[4] ?? 0) * 4,
            'fives'  => ($counts[5] ?? 0) * 5,
            'sixes'  => ($counts[6] ?? 0) * 6,
            'three_of_kind' => max($counts) >= 3 ? $sum : 0,
            'four_of_kind'  => max($counts) >= 4 ? $sum : 0,
            'full_house'    => (in_array(3, $counts, true) && in_array(2, $counts, true)) ? 25 : 0,
            'small_straight' => self::hasStraight($sorted, 4) ? 30 : 0,
            'large_straight' => self::hasStraight($sorted, 5) ? 40 : 0,
            'yams'   => max($counts) >= 5 ? 50 : 0,
            'chance' => $sum,
            default  => 0,
        };
    }

    private static function hasStraight(array $sorted, int $length): bool
    {
        $unique = array_values(array_unique($sorted));
        $consecutive = 1;
        for ($i = 1, $n = count($unique); $i < $n; $i++) {
            if ($unique[$i] === $unique[$i - 1] + 1) {
                $consecutive++;
                if ($consecutive >= $length) {
                    return true;
                }
            } else {
                $consecutive = 1;
            }
        }
        return false;
    }
}
