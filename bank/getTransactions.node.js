// 获取交易记录云函数
// db 和 Database 由 server.js 通过参数传入，无需声明

// 验证Session
async function verifySession(sessionId) {
  if (!sessionId) {
    return null;
  }
  
  const sessionData = await db.get(`session:${sessionId}`);
  if (!sessionData) {
    return null;
  }
  
  const session = JSON.parse(sessionData);
  const now = new Date();
  const expiresAt = new Date(session.expiresAt);
  
  // 检查Session是否过期
  if (now > expiresAt) {
    // 删除过期Session
    db.delete(`session:${sessionId}`);
    return null;
  }
  
  return session;
}

// 处理获取交易记录请求
async function handleGetTransactions() {
  try {
    // 获取Session ID
    // 获取Session ID - req是全局变量
    const sessionId = req.cookies.sessionId || req.headers['x-session-id'];
    
    // 验证Session
    const session = await verifySession(sessionId);
    if (!session) {
      // 返回响应 - res是全局变量
    res.status(401).json({ error: '未登录或登录已过期' });
      return;
    }
    
    // 获取交易记录ID列表
    const transactionIdsData = await db.get(`transactions:${session.userId}`);
    const transactionIds = transactionIdsData ? JSON.parse(transactionIdsData) : [];
    
    // 获取所有交易记录
    const transactions = [];
    for (const transactionId of transactionIds) {
      const transactionData = await db.get(`transactions:${session.userId}:${transactionId}`);
      if (transactionData) {
        transactions.push(JSON.parse(transactionData));
      }
    }
    
    // 按时间倒序排序
    transactions.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
    
    // 返回交易记录
    // 返回响应 - res是全局变量
    res.status(200).json({ transactions });
  } catch (error) {
    console.error('获取交易记录失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '获取交易记录失败，请稍后重试' });
  }
}

// 执行获取交易记录处理
// 检查请求方法
if (req.method === 'GET') {
  handleGetTransactions();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
