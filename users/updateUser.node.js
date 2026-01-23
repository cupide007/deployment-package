// 更新用户信息云函数
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

// 处理更新用户信息请求
async function handleUpdateUser() {
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
    
    // 获取请求数据
    const updateData = req.body;
    
    // 验证更新数据
    if (!updateData) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '请提供要更新的数据' });
      return;
    }
    
    // 获取当前用户数据
    const userData = await db.get(`user:${session.userId}`);
    if (!userData) {
      // 返回响应 - res是全局变量
    res.status(404).json({ error: '用户不存在' });
      return;
    }
    
    const user = JSON.parse(userData);
    
    // 只允许更新特定字段，不允许更新敏感字段
    const allowedFields = ['username', 'email', 'qq', 'gender', 'race', 'age', 'residence', 'bio', 'avatar'];
    const filteredUpdateData = {};
    
    for (const field of allowedFields) {
      if (updateData.hasOwnProperty(field)) {
        filteredUpdateData[field] = updateData[field];
      }
    }
    
    // 更新用户数据
    const updatedUser = { ...user, ...filteredUpdateData };
    db.set(`user:${session.userId}`, JSON.stringify(updatedUser));
    
    // 返回更新后的用户信息（不包含敏感信息）
    // 返回响应 - res是全局变量
    res.status(200).json({
      message: '用户信息更新成功',
      user: {
        id: updatedUser.id,
        username: updatedUser.username,
        email: updatedUser.email,
        role: updatedUser.role,
        createdAt: updatedUser.createdAt,
        lastLogin: updatedUser.lastLogin
      }
    });
  } catch (error) {
    console.error('更新用户信息失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '更新用户信息失败，请稍后重试' });
  }
}

// 执行更新用户信息处理
// 检查请求方法 - req是全局变量
if (req.method === 'POST') {
  handleUpdateUser();
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: "Unsupported method ('" + req.method + "')" });
}