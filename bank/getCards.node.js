// 获取银行卡片云函数 - 修复版
// db 和 Database 由 server.js 通过参数传入

// 验证Session
async function verifySession(sessionId) {
  if (!sessionId) return null;
  const sessionData = await db.get(`session:${sessionId}`);
  if (!sessionData) return null;

  let session;
  try {
    session = JSON.parse(sessionData);
  } catch (parseError) {
    return null;
  }

  const now = new Date();
  const expiresAt = new Date(session.expiresAt);

  if (isNaN(expiresAt.getTime()) || now > expiresAt) {
    db.delete(`session:${sessionId}`);
    return null;
  }
  return session;
}

// 处理获取银行卡片请求
async function handleGetCards() {
  try {
    const sessionId = req.cookies.sessionId || req.headers['x-session-id'];
    const session = await verifySession(sessionId);
    if (!session) {
      res.status(401).json({ error: '未登录或登录已过期' });
      return;
    }

    const currentUserId = session.userId;

    // 检查权限
    const userData = await db.get(`user:${currentUserId}`);
    const currentUser = userData ? JSON.parse(userData) : {};
    const isAdmin = currentUser.role === 'admin';

    let allCards = [];

    const usersData = await db.get('users');
    const userIds = usersData ? JSON.parse(usersData) : [];

    if (!userIds.includes(currentUserId)) {
      userIds.push(currentUserId);
    }

    for (const userId of userIds) {
      const cardsData = await db.get(`bank_cards:${userId}`);
      if (cardsData) {
        let cards = JSON.parse(cardsData);
        if (!Array.isArray(cards)) cards = [cards];
        allCards = allCards.concat(cards);
      }
    }

    res.status(200).json({ cards: allCards });

  } catch (error) {
    console.error('获取银行卡片失败:', error);
    res.status(500).json({ error: '获取银行卡片失败，请稍后重试' });
  }
}

if (req.method === 'GET') {
  handleGetCards();
} else {
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
