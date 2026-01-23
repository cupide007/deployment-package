// 更新用户设置云函数
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

// 处理更新用户设置请求
async function handleUpdateSettings() {
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
    const settingsData = req.body;
    
    // 验证设置数据
    if (!settingsData) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '请提供用户设置数据' });
      return;
    }
    
    // 获取当前用户设置
    const currentSettingsData = await db.get(`user_settings:${session.userId}`);
    let currentSettings = {};
    
    if (currentSettingsData) {
      currentSettings = JSON.parse(currentSettingsData);
    }
    
    // 更新用户设置，合并现有数据和新数据
    const updatedSettings = {
      ...currentSettings,
      ...settingsData,
      updatedAt: new Date().toISOString()
    };
    
    // 保存更新后的用户设置
    db.set(`user_settings:${session.userId}`, JSON.stringify(updatedSettings));
    
    // 返回成功响应
    // 返回响应 - res是全局变量
    res.status(200).json({ message: '用户设置更新成功', settings: updatedSettings });
  } catch (error) {
    console.error('更新用户设置失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '更新用户设置失败，请稍后重试' });
  }
}

// 执行更新用户设置处理
// 检查请求方法
if (req.method === 'POST') {
  handleUpdateSettings();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
