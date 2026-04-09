<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Intelligence artificielle pour les jeux interactifs (morpion et YAMS).
 */
class BotAI
{
    // ═══════════════════════════════════════════════════════════════
    //  MORPION — Algorithme Minimax (imbattable)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Retourne l'index de la meilleure case à jouer (0–8).
     */
    public static function morpionMove(array $board, string $botSymbol): int
    {
        $opponent = $botSymbol === 'X' ? 'O' : 'X';
        $bestScore = -100;
        $bestMove = -1;

        for ($i = 0; $i < 9; $i++) {
            if ($board[$i] !== null) {
                continue;
            }
            $board[$i] = $botSymbol;
            $score = self::minimax($board, false, $botSymbol, $opponent);
            $board[$i] = null;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMove = $i;
            }
        }

        return $bestMove;
    }

    private static function minimax(array $board, bool $isMaximizing, string $bot, string $opp): int
    {
        $winner = self::checkWinner($board);
        if ($winner === $bot) {
            return 10;
        }
        if ($winner === $opp) {
            return -10;
        }
        if (!in_array(null, $board, true)) {
            return 0;
        }

        if ($isMaximizing) {
            $best = -100;
            for ($i = 0; $i < 9; $i++) {
                if ($board[$i] === null) {
                    $board[$i] = $bot;
                    $best = max($best, self::minimax($board, false, $bot, $opp));
                    $board[$i] = null;
                }
            }
            return $best;
        }

        $best = 100;
        for ($i = 0; $i < 9; $i++) {
            if ($board[$i] === null) {
                $board[$i] = $opp;
                $best = min($best, self::minimax($board, true, $bot, $opp));
                $board[$i] = null;
            }
        }
        return $best;
    }

    private static function checkWinner(array $board): ?string
    {
        $lines = [
            [0, 1, 2], [3, 4, 5], [6, 7, 8],
            [0, 3, 6], [1, 4, 7], [2, 5, 8],
            [0, 4, 8], [2, 4, 6],
        ];
        foreach ($lines as [$a, $b, $c]) {
            if ($board[$a] !== null && $board[$a] === $board[$b] && $board[$b] === $board[$c]) {
                return $board[$a];
            }
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
    public static function yamsTurn(array $state, string $playerKey): array
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
