// 获取用户信息云函数
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

// 处理获取用户信息请求
async function handleGetUser() {
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
    
    // 获取请求参数 - req是全局变量
    const { userId } = req.query;
    
    // 验证目标用户ID
    if (!userId) {
      // 返回响应 - res是全局变量
      res.status(400).json({ error: '请提供用户ID' });
      return;
    }
    
    // 检查权限：只能访问自己的数据，或者管理员可以访问所有数据
    const currentUserData = await db.get(`user:${session.userId}`);
    const currentUser = JSON.parse(currentUserData);
    
    if (currentUser.id !== userId && currentUser.role !== 'admin') {
      // 返回响应 - res是全局变量
      res.status(403).json({ error: '无权访问该用户信息' });
      return;
    }
    
    // 获取目标用户数据 - 读操作是异步的，需要await
    const userData = await db.get(`user:${userId}`);
    if (!userData) {
      // 返回响应 - res是全局变量
      res.status(404).json({ error: '用户不存在' });
      return;
    }
    
    const user = JSON.parse(userData);
    
    // 返回用户信息（不包含敏感信息）- res是全局变量
    res.status(200).json({
      user: {
        id: user.id,
        username: user.username,
        email: user.email,
        role: user.role,
        createdAt: user.createdAt,
        lastLogin: user.lastLogin
      }
    });
  } catch (error) {
    console.error('获取用户信息失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '获取用户信息失败，请稍后重试' });
  }
}

// 执行获取用户信息处理
// 检查请求方法 - req是全局变量
if (req.method === 'GET') {
  // 处理异步函数的错误
  handleGetUser().catch(error => {
    console.error('获取用户信息处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ error: '获取用户信息失败，请稍后重试' });
    }
  });
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}