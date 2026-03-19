<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\PushDeviceToken;

class ExpoPushNotifier
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    private PushDeviceToken $pushDeviceTokenModel;

    public function __construct()
    {
        $this->pushDeviceTokenModel = new PushDeviceToken();
    }

    public function sendSpaceInvitation(int $recipientUserId, int $spaceId, int $invitationId, string $spaceName, string $inviterName): void
    {
        $tokens = $this->pushDeviceTokenModel->findTokensByUser($recipientUserId);
        if ($tokens === []) {
            return;
        }

        $messages = [];
        foreach ($tokens as $token) {
            if (!$this->pushDeviceTokenModel->isValidExpoPushToken($token)) {
                continue;
            }

            $messages[] = [
                'to' => $token,
                'title' => 'Nouvelle invitation',
                'body' => sprintf('%s vous invite à rejoindre l\'espace %s.', $inviterName, $spaceName),
                'sound' => 'default',
                'data' => [
                    'type' => 'space_invitation',
                    'space_id' => $spaceId,
                    'invitation_id' => $invitationId,
                ],
            ];
        }

        if ($messages === []) {
            return;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $accessToken = trim((string) (getenv('EXPO_ACCESS_TOKEN') ?: ''));
        if ($accessToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($messages, JSON_UNESCAPED_UNICODE),
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents(self::ENDPOINT, false, $context);
        if ($result === false && getenv('APP_DEBUG') === 'true') {
            error_log('Expo push notification failed for invitation #' . $invitationId);
        }
    }
}