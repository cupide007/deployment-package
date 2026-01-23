// 添加交易记录云函数
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

// 生成唯一交易ID
function generateTransactionId() {
  return Date.now().toString() + Math.random().toString(36).substr(2, 9);
}

// 处理添加交易记录请求
async function handleAddTransaction() {
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
    const { amount, type, description } = req.body;
    
    // 验证请求数据
    if (amount === undefined || !type || !description) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '请提供完整的交易信息' });
      return;
    }
    
    if (type !== 'deposit' && type !== 'withdrawal') {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '交易类型无效' });
      return;
    }
    
    if (amount <= 0) {
      // 返回响应 - res是全局变量
    res.status(400).json({ error: '交易金额必须大于0' });
      return;
    }
    
    // 获取银行账户数据
    const accountData = await db.get(`bank_account:${session.userId}`);
    if (!accountData) {
      // 返回响应 - res是全局变量
    res.status(404).json({ error: '银行账户不存在' });
      return;
    }
    
    const account = JSON.parse(accountData);
    let newBalance = account.balance;
    
    // 更新余额
    if (type === 'deposit') {
      newBalance += amount;
    } else if (type === 'withdrawal') {
      if (amount > account.balance) {
        // 返回响应 - res是全局变量
    res.status(400).json({ error: '余额不足' });
        return;
      }
      newBalance -= amount;
    }
    
    // 创建新交易记录
    const transactionId = generateTransactionId();
    const newTransaction = {
      transactionId,
      accountId: session.userId,
      type,
      amount,
      description,
      balance: newBalance,
      timestamp: new Date().toISOString()
    };
    
    // 保存新交易
    db.set(`transactions:${session.userId}:${transactionId}`, JSON.stringify(newTransaction));
    
    // 更新交易ID列表
    const transactionIdsData = await db.get(`transactions:${session.userId}`);
    const transactionIds = transactionIdsData ? JSON.parse(transactionIdsData) : [];
    transactionIds.push(transactionId);
    db.set(`transactions:${session.userId}`, JSON.stringify(transactionIds));
    
    // 更新账户余额
    const updatedAccount = {
      ...account,
      balance: newBalance,
      updatedAt: new Date().toISOString()
    };
    db.set(`bank_account:${session.userId}`, JSON.stringify(updatedAccount));
    
    // 返回成功响应
    // 返回响应 - res是全局变量
    res.status(201).json({ 
      message: '交易记录添加成功', 
      transaction: newTransaction, 
      account: updatedAccount 
    });
  } catch (error) {
    console.error('添加交易记录失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '添加交易记录失败，请稍后重试' });
  }
}

// 执行添加交易记录处理
// 检查请求方法
if (req.method === 'POST') {
  handleAddTransaction();
} else {
  // 不支持的请求方法
  // 返回响应 - res是全局变量
    res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
