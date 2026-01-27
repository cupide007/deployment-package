<?php
require_once '../common.php';
$db = new Database('retinbox-main');
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['cardId']) || !isset($data['newBalance'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid params']);
    exit;
}

$cardsRaw = $db->get('bank_cards_global');
$cards = $cardsRaw ? json_decode($cardsRaw, true) : [];

$success = false;
foreach ($cards as &$card) {
    if ($card['id'] === $data['cardId']) {
        $card['balance'] = (float)$data['newBalance'];
        $success = true;
        break;
    }
}

if ($success) {
    $db->set('bank_cards_global', json_encode($cards));
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Card not found']);
}
?>