// 获取银行账户云函数
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
    // 删除过期Session - 写操作是同步的，不需要await
    db.delete(`session:${sessionId}`);
    return null;
  }
  
  return session;
}

// 处理获取银行账户请求
async function handleGetAccount() {
  try {
    // 获取Session ID - req是全局变量
    const sessionId = req.cookies.sessionId || req.headers['x-session-id'];
    
    // 验证Session
    const session = await verifySession(sessionId);
    if (!session) {
      // 返回响应 - res是全局变量
      res.status(401).json({ error: '未登录或登录已过期' });
      return;
    }
    
    // 获取银行账户数据 - 读操作是异步的，需要await
    const accountData = await db.get(`bank_account:${session.userId}`);
    
    if (accountData) {
      // 返回响应 - res是全局变量
      res.status(200).json({ account: JSON.parse(accountData) });
    } else {
      // 返回默认银行账户
      const defaultAccount = {
        accountNumber: `ANT-${Math.random().toString(36).substr(2, 8).toUpperCase()}`,
        balance: 0,
        transactions: [],
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString()
      };
      // 返回响应 - res是全局变量
      res.status(200).json({ account: defaultAccount });
    }
  } catch (error) {
    console.error('获取银行账户失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '获取银行账户失败，请稍后重试' });
  }
}

// 执行获取银行账户处理
// 检查请求方法 - req是全局变量
if (req.method === 'GET') {
  handleGetAccount();
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}