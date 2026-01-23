// 获取交易记录云函数
// db 和 Database 由 server.js 通过参数传入

// 验证Session
async function verifySession(sessionId) {
  if (!sessionId) return null;
  const sessionData = await db.get(`session:${sessionId}`);
  if (!sessionData) return null;

  const session = JSON.parse(sessionData);
  const now = new Date();
  const expiresAt = new Date(session.expiresAt);

  if (now > expiresAt) {
    db.delete(`session:${sessionId}`);
    return null;
  }
  return session;
}

// 处理获取交易记录请求
async function handleGetTransactions() {
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

    let userIdsToFetch = [];

    if (isAdmin) {
      const usersData = await db.get('users');
      userIdsToFetch = usersData ? JSON.parse(usersData) : [];
      if (!userIdsToFetch.includes(currentUserId)) {
        userIdsToFetch.push(currentUserId);
      }
    } else {
      userIdsToFetch = [currentUserId];
    }

    const allTransactions = [];

    // 遍历用户获取记录
    for (const userId of userIdsToFetch) {
      const transactionIdsData = await db.get(`transactions:${userId}`);
      const transactionIds = transactionIdsData ? JSON.parse(transactionIdsData) : [];

      for (const transactionId of transactionIds) {
        const transactionData = await db.get(`transactions:${userId}:${transactionId}`);
        if (transactionData) {
          allTransactions.push(JSON.parse(transactionData));
        }
      }
    }

    // 按时间倒序排序
    allTransactions.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

    res.status(200).json({ transactions: allTransactions });
  } catch (error) {
    console.error('获取交易记录失败:', error);
    res.status(500).json({ error: '获取交易记录失败，请稍后重试' });
  }
}

if (req.method === 'GET') {
  handleGetTransactions();
} else {
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}