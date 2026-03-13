<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Models\PasswordPolicy;
use App\Models\ActivityLog;
use App\Config\Database;

/**
 * Contrôleur de profil utilisateur.
 */
class ProfileController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Affiche le profil de l'utilisateur connecté.
     */
    public function show(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $user   = $this->userModel->find($userId);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/');
        }

        $pdo = Database::getInstance()->getConnection();

        $this->render('profile/show', [
            'title'    => 'Mon profil',
            'user'     => $user,
            'winStats' => $this->computeGlobalWinRate($userId, $pdo),
        ]);
    }

    /**
     * Formulaire de modification du profil.
     */
    public function editForm(): void
    {
        $this->requireAuth();

        $user = $this->userModel->find($this->getCurrentUserId());
        $policyModel = new PasswordPolicy();

        $this->render('profile/edit', [
            'title'         => 'Modifier mon profil',
            'user'          => $user,
            'policySummary' => $policyModel->getSummary(),
            'policyJson'    => $policyModel->toJson(),
        ]);
    }

    /**
     * Traite la modification du profil.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data = $this->getPostData(['username', 'email', 'bio', 'current_password', 'new_password', 'new_password_confirm']);

        $errors = [];

        // Validation username
        if (empty($data['username'])) {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } elseif (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
            $errors[] = 'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères.';
        } else {
            $existing = $this->userModel->findByUsername($data['username']);
            if ($existing && $existing['id'] !== $userId) {
                $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
            }
        }

        // Validation email
        if (empty($data['email'])) {
            $errors[] = 'L\'email est requis.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        } else {
            $existing = $this->userModel->findByEmail($data['email']);
            if ($existing && $existing['id'] !== $userId) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }
        }

        // Gestion de l'avatar
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 Mo

            if (!in_array($file['type'], $allowedTypes, true)) {
                $errors[] = 'Le format d\'image n\'est pas supporté (JPG, PNG, GIF, WebP).';
            }
            if ($file['size'] > $maxSize) {
                $errors[] = 'L\'image ne doit pas dépasser 5 Mo.';
            }

            if (empty($errors)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                
                // Utiliser le chemin absolu depuis la racine du projet
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
                
                // Créer le dossier si nécessaire et forcer les permissions
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                @chmod($uploadDir, 0777);

                $targetPath = $uploadDir . $filename;
                
                // Tenter le move avec suppression d'erreur pour voir si ça passe
                $moved = @move_uploaded_file($file['tmp_name'], $targetPath);
                
                if ($moved) {
                    @chmod($targetPath, 0644);
                    $avatarPath = '/uploads/' . $filename;
                } else {
                    // Si ça échoue toujours, essayer avec copy + unlink
                    if (@copy($file['tmp_name'], $targetPath)) {
                        @unlink($file['tmp_name']);
                        @chmod($targetPath, 0644);
                        $avatarPath = '/uploads/' . $filename;
                    } else {
                        $errors[] = 'Erreur lors du téléchargement de l\'avatar.';
                    }
                }
            }
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Erreur d'upload
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
                UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale du formulaire.',
                UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement téléchargé.',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'enregistrer le fichier sur le disque.',
                UPLOAD_ERR_EXTENSION  => 'Une extension PHP a bloqué l\'upload.',
            ];
            $errors[] = $uploadErrors[$_FILES['avatar']['error']] ?? 'Erreur inconnue lors de l\'upload.';
        }

        // Changement de mot de passe
        if (!empty($data['new_password'])) {
            if (empty($data['current_password'])) {
                $errors[] = 'Le mot de passe actuel est requis pour changer de mot de passe.';
            } else {
                $user = $this->userModel->find($userId);
                if (!password_verify($data['current_password'], $user['password_hash'])) {
                    $errors[] = 'Le mot de passe actuel est incorrect.';
                }
            }

            // Valider contre la politique de mot de passe
            $policyModel = new PasswordPolicy();
            $policyErrors = $policyModel->validate($data['new_password']);
            $errors = array_merge($errors, $policyErrors);

            if ($data['new_password'] !== $data['new_password_confirm']) {
                $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect('/profile/edit');
        }

        // Mise à jour du profil
        $updateData = [
            'username' => $data['username'],
            'email'    => $data['email'],
            'bio'      => $data['bio'],
        ];

        if ($avatarPath) {
            $updateData['avatar'] = $avatarPath;
        }

        $this->userModel->updateProfile($userId, $updateData);

        ActivityLog::logAuth('profile.update', $userId);

        // Mise à jour du mot de passe si demandé
        if (!empty($data['new_password'])) {
            $this->userModel->updatePassword($userId, $data['new_password']);
            ActivityLog::logAuth('password.change', $userId);
        }

        // Mettre à jour la session
        Session::set('username', $data['username']);
        if ($avatarPath) {
            Session::set('avatar', $avatarPath);
        }

        $this->setFlash('success', 'Profil mis à jour avec succès.');
        $this->redirect('/profile');
    }

    /**
     * Calcule le taux de victoire global d'un utilisateur, tous espaces confondus.
     * Une manche est comptée comme gagnée si le joueur a le meilleur score
     * selon la win_condition du type de jeu (ex-aequo inclus).
     * Seules les manches avec status = 'completed' sont prises en compte.
     */
    private function computeGlobalWinRate(int $userId, \PDO $pdo): ?array
    {
        // 1. Joueurs liés à l'utilisateur dans ses espaces
        $stmt = $pdo->prepare("
            SELECT p.id AS player_id, p.space_id, s.name AS space_name
            FROM players p
            JOIN spaces s ON s.id = p.space_id
            JOIN space_members sm ON sm.space_id = p.space_id AND sm.user_id = :member_user_id
            WHERE p.user_id = :player_user_id
        ");
        $stmt->execute([
            'member_user_id' => $userId,
            'player_user_id' => $userId,
        ]);
        $playerRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($playerRows)) {
            return null;
    }

        $playerIdToSpace = [];
        $spaceNames = [];
        foreach ($playerRows as $row) {
            $playerIdToSpace[(int) $row['player_id']] = (int) $row['space_id'];
            $spaceNames[(int) $row['space_id']] = $row['space_name'];
        }
        $playerIds = array_keys($playerIdToSpace);

        // 2. Manches terminées où ces joueurs ont un score
        $ph = implode(',', array_fill(0, count($playerIds), '?'));
        $stmt = $pdo->prepare("
            SELECT DISTINCT r.id AS round_id, gt.win_condition
            FROM round_scores rs
            JOIN rounds r ON r.id = rs.round_id AND r.status = 'completed'
            JOIN games g ON g.id = r.game_id
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE rs.player_id IN ($ph)
        ");
        $stmt->execute($playerIds);
        $rounds = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rounds)) {
            return null;
        }

        $roundIds = array_column($rounds, 'round_id');

        // 3. Tous les scores de ces manches en une seule requête
        $rph = implode(',', array_fill(0, count($roundIds), '?'));
        $stmt = $pdo->prepare("
            SELECT round_id, player_id, score
            FROM round_scores
            WHERE round_id IN ($rph)
        ");
        $stmt->execute($roundIds);
        $allScores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $scoresByRound = [];
        foreach ($allScores as $s) {
            $scoresByRound[(int) $s['round_id']][] = $s;
        }

        // 4. Comptabilisation par manche
        $spaceStats  = [];
        $totalPlayed = 0;
        $totalWon    = 0;

        foreach ($rounds as $round) {
            $roundId      = (int) $round['round_id'];
            $winCondition = $round['win_condition'];
            $scores       = $scoresByRound[$roundId] ?? [];

            if (empty($scores)) {
                continue;
            }

            $vals = array_map(fn($s) => (float) $s['score'], $scores);
            $best = ($winCondition === 'lowest_score' || $winCondition === 'ranking')
                ? min($vals)
                : max($vals);

            foreach ($scores as $s) {
                $pid = (int) $s['player_id'];
                if (!isset($playerIdToSpace[$pid])) {
                    continue;
                }
                $sid = $playerIdToSpace[$pid];

                $spaceStats[$sid] = $spaceStats[$sid] ?? ['played' => 0, 'won' => 0];
                $spaceStats[$sid]['played']++;
                $totalPlayed++;

                if ((float) $s['score'] === $best) {
                    $spaceStats[$sid]['won']++;
                    $totalWon++;
                }
            }
        }

        if ($totalPlayed === 0) {
            return null;
        }

        // 5. Détail par espace (espaces sans manche inclus)
        $breakdown = [];
        foreach ($spaceNames as $sid => $name) {
            $st = $spaceStats[$sid] ?? ['played' => 0, 'won' => 0];
            $breakdown[] = [
                'space_name' => $name,
                'played'     => $st['played'],
                'won'        => $st['won'],
                'rate'       => $st['played'] > 0
                    ? round($st['won'] * 100.0 / $st['played'], 1)
                    : null,
            ];
        }
        usort($breakdown, fn($a, $b) => $b['played'] <=> $a['played']);

        return [
            'rounds_played' => $totalPlayed,
            'rounds_won'    => $totalWon,
            'win_rate'      => round($totalWon * 100.0 / $totalPlayed, 2),
            'breakdown'     => $breakdown,
        ];
    }
}
