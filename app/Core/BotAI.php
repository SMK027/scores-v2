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

    /** Limites de profondeur minimax par taille de grille. */
    private const MINIMAX_DEPTH = [3 => 100, 4 => 8, 5 => 4];

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
     * Facile : coup purement aléatoire.
     */
    private static function morpionMoveEasy(array $board, int $gridSize): int
    {
        $total = $gridSize * $gridSize;
        $free = [];
        for ($i = 0; $i < $total; $i++) {
            if ($board[$i] === null) {
                $free[] = $i;
            }
        }
        return $free[array_rand($free)];
    }

    /**
     * Moyen : minimax mais joue un coup aléatoire ~40 % du temps.
     */
    private static function morpionMoveMedium(array $board, string $botSymbol, int $gridSize, int $alignCount): int
    {
        if (random_int(1, 100) <= 40) {
            return self::morpionMoveEasy($board, $gridSize);
        }
        return self::morpionMoveHard($board, $botSymbol, $gridSize, $alignCount);
    }

    /**
     * Difficile : minimax avec élagage alpha-bêta.
     */
    private static function morpionMoveHard(array $board, string $botSymbol, int $gridSize, int $alignCount): int
    {
        $opponent = $botSymbol === 'X' ? 'O' : 'X';
        $lines = self::generateWinLines($gridSize, $alignCount);
        $maxDepth = self::MINIMAX_DEPTH[$gridSize] ?? 5;
        $bestScore = -100000;
        $bestMove = -1;

        $total = $gridSize * $gridSize;
        for ($i = 0; $i < $total; $i++) {
            if ($board[$i] !== null) {
                continue;
            }
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

        if ($isMaximizing) {
            $best = -100000;
            for ($i = 0; $i < $total; $i++) {
                if ($board[$i] === null) {
                    $board[$i] = $bot;
                    $score = self::minimax($board, false, $bot, $opp, $lines, $gridSize, $depth - 1, $alpha, $beta);
                    $board[$i] = null;
                    $best = max($best, $score);
                    $alpha = max($alpha, $best);
                    if ($beta <= $alpha) break;
                }
            }
            return $best;
        }

        $best = 100000;
        for ($i = 0; $i < $total; $i++) {
            if ($board[$i] === null) {
                $board[$i] = $opp;
                $score = self::minimax($board, true, $bot, $opp, $lines, $gridSize, $depth - 1, $alpha, $beta);
                $board[$i] = null;
                $best = min($best, $score);
                $beta = min($beta, $best);
                if ($beta <= $alpha) break;
            }
        }
        return $best;
    }

    /**
     * Évaluation heuristique pour les grilles > 3×3 quand la profondeur max est atteinte.
     */
    private static function evaluateBoard(array $board, string $bot, string $opp, array $lines): int
    {
        $score = 0;
        foreach ($lines as $line) {
            $botCount = 0;
            $oppCount = 0;
            foreach ($line as $idx) {
                if ($board[$idx] === $bot) $botCount++;
                elseif ($board[$idx] === $opp) $oppCount++;
            }
            // Seules les lignes non bloquées comptent
            if ($oppCount === 0 && $botCount > 0) {
                $score += $botCount * $botCount;
            } elseif ($botCount === 0 && $oppCount > 0) {
                $score -= $oppCount * $oppCount;
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
