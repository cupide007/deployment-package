<?php
// 获取银行卡片云函数 - PHP版本
// 使用 Retinbox Database 类

header('Content-Type: application/json; charset=utf-8');

function verifySession($db, $sessionId) {
    if (!$sessionId) return null;

    $sessionData = $db->get("session_$sessionId");
    if (!$sessionData) return null;

    try {
        $session = json_decode($sessionData, true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;
    } catch (Exception $e) {
        return null;
    }

    if (empty($session['expiresAt']) || empty($session['userId'])) return null;

    $now = new DateTime();
    try {
        $expiresAt = new DateTime($session['expiresAt']);
    } catch (Exception $e) {
        $db->delete("session_$sessionId");
        return null;
    }

    if ($now > $expiresAt) {
        $db->delete("session_$sessionId");
        return null;
    }

    return $session;
}

try {
    $db = new Database('antister_virtual_country');
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $sessionId = $_COOKIE['sessionId'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? null;

        $session = verifySession($db, $sessionId);
        if (!$session) {
            http_response_code(401);
            echo json_encode(['error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $currentUserId = $session['userId'];

        $allCards = [];

        $usersData = $db->get('users');
        $userIds = $usersData ? json_decode($usersData, true) : [];

        if (!is_array($userIds)) {
            $userIds = $userIds === null ? [] : [$userIds];
        }

        $normalizedUserIds = [];
        foreach ($userIds as $userId) {
            if ($userId === null || $userId === '') {
                continue;
            }
            if (!in_array($userId, $normalizedUserIds, true)) {
                $normalizedUserIds[] = $userId;
            }
        }

        if (!in_array($currentUserId, $normalizedUserIds, true)) {
            $normalizedUserIds[] = $currentUserId;
        }

        $seenCards = [];

        foreach ($normalizedUserIds as $userId) {
            $cardsData = $db->get("bank_cards_$userId");
            if ($cardsData) {
                $cards = json_decode($cardsData, true);
                if (is_string($cards)) {
                    $decodedCards = json_decode($cards, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $cards = $decodedCards;
                    }
                }
                if (!is_array($cards)) {
                    $cards = $cards === null ? [] : [$cards];
                }
                foreach ($cards as $card) {
                    if (!is_array($card)) {
                        continue;
                    }
                    $cardKey = $card['id'] ?? $card['cardNumber'] ?? null;
                    if ($cardKey === null || isset($seenCards[$cardKey])) {
                        continue;
                    }
                    $seenCards[$cardKey] = true;
                    $allCards[] = $card;
                }
            }
        }

        http_response_code(200);
        echo json_encode(['cards' => $allCards], JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(405);
        echo json_encode(['error' => "Unsupported method ('$method')"], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log('获取银行卡片失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => '获取银行卡片失败，请稍后重试',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
