// 归还书籍云函数
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

// 处理归还书籍请求
async function handleReturnBook() {
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
    
    // 获取请求数据 - req是全局变量
    const { recordId } = req.body;
    
    // 验证请求数据
    if (!recordId) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '请提供借阅记录ID' });
      return;
    }
    
    // 获取借阅记录
    const recordData = await db.get(`library_records:${session.userId}:${recordId}`);
    if (!recordData) {
      // 返回响应 - res是全局变量
    res.status(404).json({ error: '借阅记录不存在' });
      return;
    }
    
    const record = JSON.parse(recordData);
    
    // 检查记录状态
    if (record.status === 'returned') {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '该书籍已归还' });
      return;
    }
    
    // 更新借阅记录
    const updatedRecord = {
      ...record,
      returnDate: new Date().toISOString(),
      status: 'returned'
    };
    
    // 保存更新后的借阅记录
    db.set(`library_records:${session.userId}:${recordId}`, JSON.stringify(updatedRecord));
    
    // 返回成功响应
    // 返回响应 - res是全局变量
    res.status(200).json({ message: '书籍归还成功', record: updatedRecord });
  } catch (error) {
    console.error('归还书籍失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '归还书籍失败，请稍后重试' });
  }
}

// 执行归还书籍处理
// 检查请求方法
if (req.method === 'POST') {
  handleReturnBook();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
