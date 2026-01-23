// 获取用户列表云函数
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

// 处理获取用户列表请求
async function handleGetUsers() {
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
    
    // 获取当前用户信息
    const currentUserData = await db.get(`user:${session.userId}`);
    const currentUser = JSON.parse(currentUserData);
    
    // 获取所有用户ID
    const userIds = JSON.parse(await db.get('users') || '[]');
    
    // 获取所有用户信息
    const users = [];
    for (const userId of userIds) {
      const userData = await db.get(`user:${userId}`);
      if (userData) {
        const user = JSON.parse(userData);
        
        // 根据用户角色返回不同级别的信息
        if (currentUser.role === 'admin') {
          // 管理员：返回完整用户信息
          users.push({
            id: user.id,
            username: user.username,
            email: user.email,
            role: user.role,
            createdAt: user.createdAt,
            lastLogin: user.lastLogin
          });
        } else {
          // 普通用户：只返回基本的公开信息，用于转账等功能
          users.push({
            id: user.id,
            username: user.username
            // 不包含 email, role, createdAt, lastLogin 等敏感信息
          });
        }
      }
    }
    
    // 返回用户列表
    // 返回响应 - res是全局变量
    res.status(200).json({ users });
  } catch (error) {
    console.error('获取用户列表失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '获取用户列表失败，请稍后重试' });
  }
}

// 执行获取用户列表处理
// 检查请求方法 - req是全局变量
if (req.method === 'GET') {
  handleGetUsers();
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: "Unsupported method ('" + req.method + "')" });
}