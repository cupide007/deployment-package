// 用户注册云函数
// db、Database 和 crypto 由 server.js 通过参数传入，无需声明

// 生成唯一ID
function generateId() {
  return Date.now().toString() + Math.random().toString(36).substr(2, 9);
}

// 密码加密
function hashPassword(password) {
  const salt = crypto.randomBytes(16).toString('hex');
  const hash = crypto.pbkdf2Sync(password, salt, 1000, 64, 'sha512').toString('hex');
  return { salt, hash };
}

// 处理注册请求
async function handleRegister() {
  try {
    // 获取请求数据 - req是全局变量
    const { email, password, username } = req.body;
    
    // 验证输入
    if (!email || !password || !username) {
      // 返回响应 - res是全局变量
      res.status(400).json({ error: '请提供完整的注册信息' });
      return;
    }
    
    // 获取用户ID列表 - 读操作是异步的，需要await
    let userIds;
    try {
      userIds = JSON.parse(await db.get('users') || '[]');
    } catch (parseError) {
      console.error('解析用户ID列表失败:', parseError);
      res.status(500).json({ error: '用户数据错误，请联系管理员' });
      return;
    }
    
    // 检查邮箱和用户名是否已存在 - 需要遍历查询每个用户
    for (const userId of userIds) {
      const userData = await db.get(`user:${userId}`);
      if (userData) {
        let existingUser;
        try {
          existingUser = JSON.parse(userData);
        } catch (parseError) {
          console.error('解析现有用户数据失败:', parseError);
          continue; // 跳过此用户，继续检查其他用户
        }
        if (existingUser.email === email) {
          // 返回响应 - res是全局变量
          res.status(400).json({ error: '该邮箱已被注册' });
          return;
        }
        if (existingUser.username === username) {
          // 返回响应 - res是全局变量
          res.status(400).json({ error: '该用户名已被使用' });
          return;
        }
      }
    }
    
    // 生成用户ID
    const userId = generateId();
    
    // 加密密码
    const { salt, hash } = hashPassword(password);
    
    // 创建用户对象
    const user = {
      id: userId,
      username,
      email,
      salt,
      hash,
      role: 'user',
      createdAt: new Date().toISOString(),
      lastLogin: null
    };
    
    // 保存用户数据 - 写操作是同步的，不需要await
    db.set(`user:${userId}`, JSON.stringify(user));
    
    // 更新用户列表
    userIds.push(userId);
    db.set('users', JSON.stringify(userIds));
    
    // 创建默认游戏进度 - 写操作是同步的，不需要await
    db.set(`game_progress:${userId}`, JSON.stringify({
      level: 1,
      experience: 0,
      achievements: [],
      quests: {},
      inventory: { gold: 0, items: [] },
      gameStats: { play_time: 0, missions_completed: 0, enemies_defeated: 0 },
      updatedAt: new Date().toISOString()
    }));
    
    // 创建默认用户设置 - 写操作是同步的，不需要await
    db.set(`user_settings:${userId}`, JSON.stringify({
      theme: 'light',
      language: 'zh-CN',
      notifications: { email: true, system: true, activity: false },
      notificationFrequency: 'immediately',
      privacySettings: { showEmail: false, showQQ: false, allowFriendRequests: true },
      displaySettings: { showLevel: true, showAchievements: true },
      updatedAt: new Date().toISOString()
    }));
    
    // 返回成功响应 - res是全局变量
    res.status(201).json({ message: '注册成功', userId });
  } catch (error) {
    console.error('注册失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '注册失败，请稍后重试' });
  }
}

// 执行注册处理
// 检查请求方法 - req是全局变量
if (req.method === 'POST') {
  // 处理异步函数的错误
  handleRegister().catch(error => {
    console.error('注册处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ error: '注册失败，请稍后重试' });
    }
  });
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}