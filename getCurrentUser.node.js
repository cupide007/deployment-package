// 获取当前用户云函数
// Retinbox 平台会自动注入：req, res, db, Database, crypto

// 执行获取当前用户处理
if (req.method === 'GET') {
  // 使用 IIFE 避免嵌套函数作用域问题
  (async () => {
    try {
      // 获取 Session ID
      const sessionId = req.cookies?.sessionId || req.headers['x-session-id'];
      
      if (!sessionId) {
        res.status(401).json({ error: '未登录或登录已过期' });
        return;
      }
      
      // 验证 Session
      const sessionData = await db.get(`session:${sessionId}`);
      if (!sessionData) {
        res.status(401).json({ error: '未登录或登录已过期' });
        return;
      }
      
      // 解析 Session 数据
      let session;
      try {
        session = JSON.parse(sessionData);
      } catch (parseError) {
        console.error('解析 session 数据失败:', parseError);
        res.status(401).json({ error: '会话数据损坏' });
        return;
      }
      
      // 验证 session 数据完整性
      if (!session.expiresAt || !session.userId) {
        console.error('Session 数据不完整:', session);
        res.status(401).json({ error: '会话数据不完整' });
        return;
      }
      
      // 检查 Session 是否过期
      const now = new Date();
      const expiresAt = new Date(session.expiresAt);
      
      // 检查日期是否有效
      if (isNaN(expiresAt.getTime())) {
        console.error('Session 过期时间格式无效:', session.expiresAt);
        // 删除无效的 session 数据（写操作是同步的）
        db.delete(`session:${sessionId}`);
        res.status(401).json({ error: '会话数据无效' });
        return;
      }
      
      // 检查是否过期
      if (now > expiresAt) {
        // 删除过期 Session（写操作是同步的）
        db.delete(`session:${sessionId}`);
        res.status(401).json({ error: '登录已过期，请重新登录' });
        return;
      }
      
      // 获取用户数据
      const userData = await db.get(`user:${session.userId}`);
      if (!userData) {
        // 用户不存在，删除无效 Session
        db.delete(`session:${sessionId}`);
        res.status(404).json({ error: '用户不存在' });
        return;
      }
      
      // 解析用户数据
      let user;
      try {
        user = JSON.parse(userData);
      } catch (parseError) {
        console.error('解析用户数据失败:', parseError);
        res.status(500).json({ error: '用户数据损坏' });
        return;
      }
      
      // 返回用户信息（不包含敏感信息）
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
      console.error('获取当前用户失败:', error);
      // 如果响应尚未发送，则发送错误响应
      if (!res.headersSent) {
        res.status(500).json({ 
          error: '获取用户信息失败，请稍后重试',
          details: error.message || 'Unknown error'
        });
      }
    }
  })().catch(error => {
    console.error('获取当前用户处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ 
        error: '获取用户信息失败，请稍后重试',
        details: error.message || 'Unknown error'
      });
    }
  });
} else {
  // 不支持的请求方法
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}