// 获取图书馆记录云函数
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

// 处理获取图书馆记录请求
async function handleGetRecords() {
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
    
    // 获取图书馆记录ID列表
    const recordIdsData = await db.get(`library_records:${session.userId}`);
    const recordIds = recordIdsData ? JSON.parse(recordIdsData) : [];
    
    // 获取所有图书馆记录
    const records = [];
    for (const recordId of recordIds) {
      const recordData = await db.get(`library_records:${session.userId}:${recordId}`);
      if (recordData) {
        records.push(JSON.parse(recordData));
      }
    }
    
    // 按借阅日期倒序排序
    records.sort((a, b) => new Date(b.borrowDate) - new Date(a.borrowDate));
    
    // 返回图书馆记录
    // 返回响应 - res是全局变量
    res.status(200).json({ records });
  } catch (error) {
    console.error('获取图书馆记录失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '获取图书馆记录失败，请稍后重试' });
  }
}

// 执行获取图书馆记录处理
// 检查请求方法
if (req.method === 'GET') {
  handleGetRecords();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
