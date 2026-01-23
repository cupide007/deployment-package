// 保存游戏进度云函数
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

// 处理保存游戏进度请求
async function handleSaveProgress() {
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
    const progressData = req.body;
    
    // 验证进度数据
    if (!progressData) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '请提供游戏进度数据' });
      return;
    }
    
    // 获取当前游戏进度
    const currentProgressData = await db.get(`game_progress:${session.userId}`);
    let currentProgress = {};
    
    if (currentProgressData) {
      currentProgress = JSON.parse(currentProgressData);
    }
    
    // 更新游戏进度，合并现有数据和新数据
    const updatedProgress = {
      ...currentProgress,
      ...progressData,
      updatedAt: new Date().toISOString()
    };
    
    // 保存更新后的游戏进度
    db.set(`game_progress:${session.userId}`, JSON.stringify(updatedProgress));
    
    // 返回成功响应
    // 返回响应 - res是全局变量
    res.status(200).json({ message: '游戏进度保存成功', progress: updatedProgress });
  } catch (error) {
    console.error('保存游戏进度失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '保存游戏进度失败，请稍后重试' });
  }
}

// 执行保存游戏进度处理
// 检查请求方法
if (req.method === 'POST') {
  handleSaveProgress();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
