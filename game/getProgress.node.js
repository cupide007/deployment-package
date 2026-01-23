// 获取游戏进度云函数
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
  
  let session;
  try {
    session = JSON.parse(sessionData);
  } catch (parseError) {
    console.error('解析session数据失败:', parseError);
    return null;
  }
  
  // 验证session数据完整性
  if (!session.expiresAt) {
    console.error('Session数据缺少过期时间:', session);
    return null;
  }
  
  const now = new Date();
  const expiresAt = new Date(session.expiresAt);
  
  // 检查日期是否有效
  if (isNaN(expiresAt.getTime())) {
    console.error('Session过期时间格式无效:', session.expiresAt);
    // 删除无效的session数据 - 写操作是同步的，不需要await
    db.delete(`session:${sessionId}`);
    return null;
  }
  
  // 检查Session是否过期
  if (now > expiresAt) {
    // 删除过期Session - 写操作是同步的，不需要await
    db.delete(`session:${sessionId}`);
    return null;
  }
  
  return session;
}

// 处理获取游戏进度请求
async function handleGetProgress() {
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
    
    // 获取游戏进度数据
    const progressData = await db.get(`game_progress:${session.userId}`);
    
    if (progressData) {
      // 返回响应 - res是全局变量
    res.status(200).json({ progress: JSON.parse(progressData) });
    } else {
      // 返回默认游戏进度
      const defaultProgress = {
        level: 1,
        experience: 0,
        achievements: [],
        quests: {},
        inventory: { gold: 0, items: [] },
        gameStats: { play_time: 0, missions_completed: 0, enemies_defeated: 0 },
        updatedAt: new Date().toISOString()
      };
      // 返回响应 - res是全局变量
    res.status(200).json({ progress: defaultProgress });
    }
  } catch (error) {
    console.error('获取游戏进度失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '获取游戏进度失败，请稍后重试' });
  }
}

// 执行获取游戏进度处理
// 检查请求方法 - req是全局变量
if (req.method === 'GET') {
  // 处理异步函数的错误
  handleGetProgress().catch(error => {
    console.error('获取游戏进度处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ error: '获取游戏进度失败，请稍后重试' });
    }
  });
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
