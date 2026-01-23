// 添加借书记录云函数
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

// 生成唯一记录ID
function generateRecordId() {
  return Date.now().toString() + Math.random().toString(36).substr(2, 9);
}

// 处理添加借阅记录请求
async function handleAddRecord() {
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
    const { bookId, bookTitle } = req.body;
    
    // 验证请求数据
    if (!bookId || !bookTitle) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '请提供完整的借阅信息' });
      return;
    }
    
    // 创建新借阅记录
    const recordId = generateRecordId();
    const borrowDate = new Date();
    const dueDate = new Date(borrowDate.getTime() + 14 * 24 * 60 * 60 * 1000); // 14天到期
    
    const newRecord = {
      recordId,
      bookId,
      bookTitle,
      borrowDate: borrowDate.toISOString(),
      dueDate: dueDate.toISOString(),
      returnDate: null,
      status: 'borrowed'
    };
    
    // 保存借阅记录
    db.set(`library_records:${session.userId}:${recordId}`, JSON.stringify(newRecord));
    
    // 更新借阅记录ID列表
    const recordIdsData = await db.get(`library_records:${session.userId}`);
    const recordIds = recordIdsData ? JSON.parse(recordIdsData) : [];
    recordIds.push(recordId);
    db.set(`library_records:${session.userId}`, JSON.stringify(recordIds));
    
    // 返回成功响应
    // 返回响应 - res是全局变量
    res.status(201).json({ message: '借阅记录添加成功', record: newRecord });
  } catch (error) {
    console.error('添加借阅记录失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '添加借阅记录失败，请稍后重试' });
  }
}

// 执行添加借阅记录处理
// 检查请求方法
if (req.method === 'POST') {
  handleAddRecord();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
