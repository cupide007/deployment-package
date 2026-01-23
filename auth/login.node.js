// 用户登录云函数
// db、Database 和 crypto 由 server.js 通过参数传入，无需声明

// 密码验证
function verifyPassword(password, salt, hash) {
  const verifyHash = crypto.pbkdf2Sync(password, salt, 1000, 64, 'sha512').toString('hex');
  return hash === verifyHash;
}

// 生成Session ID
function generateSessionId() {
  return crypto.randomBytes(32).toString('hex');
}

// 处理登录请求
async function handleLogin() {
  try {
    // 获取请求数据 - req是全局变量
    const { email, password } = req.body;

    // 验证输入
    if (!email || !password) {
      // 返回响应 - res是全局变量
      res.status(400).json({ error: '请提供邮箱和密码' });
      return;
    }

    // 获取所有用户ID - 读操作是异步的，需要await
    let userIds;
    try {
      userIds = JSON.parse(await db.get('users') || '[]');
    } catch (parseError) {
      console.error('解析用户ID列表失败:', parseError);
      res.status(500).json({ error: '用户数据错误，请联系管理员' });
      return;
    }

    // 查找用户
    let user = null;
    for (const userId of userIds) {
      const userData = await db.get(`user:${userId}`);
      if (userData) {
        let parsedUser;
        try {
          parsedUser = JSON.parse(userData);
        } catch (parseError) {
          console.error('解析用户数据失败:', parseError);
          continue; // 跳过此用户，继续查找其他用户
        }
        if (parsedUser.email === email || parsedUser.username === email) {
          user = parsedUser;
          break;
        }
      }
    }

    // 验证用户和密码
    if (!user || !verifyPassword(password, user.salt, user.hash)) {
      // 返回响应 - res是全局变量
      res.status(401).json({ error: '邮箱/用户名或密码错误' });
      return;
    }

    // 更新最后登录时间
    user.lastLogin = new Date().toISOString();
    // 保存用户数据 - 写操作是同步的，不需要await
    db.set(`user:${user.id}`, JSON.stringify(user));

    // 生成Session ID
    const sessionId = generateSessionId();

    // 保存Session - 写操作是同步的，不需要await
    db.set(`session:${sessionId}`, JSON.stringify({
      userId: user.id,
      createdAt: new Date().toISOString(),
      expiresAt: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString() // 7天过期
    }));

    // 设置Session Cookie - res是全局变量
    res.cookie('sessionId', sessionId, {
      maxAge: 7 * 24 * 60 * 60 * 1000,
      httpOnly: true,
      secure: req.secure || req.headers['x-forwarded-proto'] === 'https'
    });

    // 返回成功响应 - res是全局变量
    res.status(200).json({
      message: '登录成功',
      user: {
        id: user.id,
        username: user.username,
        email: user.email,
        role: user.role
      }
    });
  } catch (error) {
    console.error('登录失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '登录失败，请稍后重试' });
  }
}

// 执行登录处理
// 检查请求方法 - req是全局变量
if (req.method === 'POST') {
  // 处理异步函数的错误
  handleLogin().catch(error => {
    console.error('登录处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ error: '登录失败，请稍后重试' });
    }
  });
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}