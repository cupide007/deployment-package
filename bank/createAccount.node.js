// 创建银行账户云函数
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

// 生成账号
function generateAccountNumber() {
  return `ANT-${Math.random().toString(36).substr(2, 8).toUpperCase()}`;
}

// 处理创建银行账户请求
async function handleCreateAccount() {
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
    
    // 检查是否已存在银行账户
    const existingAccountData = await db.get(`bank_account:${session.userId}`);
    if (existingAccountData) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '您已拥有银行账户' });
      return;
    }
    
    // 创建新银行账户
    const newAccount = {
      accountNumber: generateAccountNumber(),
      balance: 0,
      transactions: [],
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };
    
    // 保存银行账户
    db.set(`bank_account:${session.userId}`, JSON.stringify(newAccount));
    
    // 返回成功响应
    // 返回响应 - res是全局变量
    res.status(201).json({ message: '银行账户创建成功', account: newAccount });
  } catch (error) {
    console.error('创建银行账户失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '创建银行账户失败，请稍后重试' });
  }
}

// 执行创建银行账户处理
// 检查请求方法
if (req.method === 'POST') {
  handleCreateAccount();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
