// 数据库初始化云函数
// db、Database 和 crypto 由 Retinbox 平台提供，无需声明

// 创建管理员账号
async function createAdminAccount() {
  try {
    // 获取用户列表
    const userIds = JSON.parse(await db.get('users') || '[]');
    
    // 检查是否已存在admin账号
    let adminExists = false;
    for (const userId of userIds) {
      const userData = await db.get(`user:${userId}`);
      if (userData) {
        const user = JSON.parse(userData);
        if (user.username === 'admin' || user.email === 'admin@antister.com') {
          adminExists = true;
          
          break;
        }
      }
    }
    
    // 如果不存在，创建管理员账号
    if (!adminExists) {
      const adminId = 'admin_' + Date.now().toString();
      const password = 'test';
      
      // 加密密码
      const salt = crypto.randomBytes(16).toString('hex');
      const hash = crypto.pbkdf2Sync(password, salt, 1000, 64, 'sha512').toString('hex');
      
      // 创建管理员用户对象
      const adminUser = {
        id: adminId,
        username: 'admin',
        email: 'admin@antister.com',
        salt: salt,
        hash: hash,
        role: 'admin',
        createdAt: new Date().toISOString(),
        lastLogin: null
      };
      
      // 保存管理员数据 - 写操作是同步的，不需要await
      db.set(`user:${adminId}`, JSON.stringify(adminUser));
      
      // 更新用户列表
      userIds.push(adminId);
      db.set('users', JSON.stringify(userIds));
      
      // 创建管理员的默认数据
      db.set(`game_progress:${adminId}`, JSON.stringify({
        level: 99,
        experience: 999999,
        achievements: [],
        quests: {},
        inventory: { gold: 999999, items: [] },
        gameStats: { play_time: 0, missions_completed: 0, enemies_defeated: 0 },
        updatedAt: new Date().toISOString()
      }));
      
      db.set(`user_settings:${adminId}`, JSON.stringify({
        theme: 'light',
        language: 'zh-CN',
        notifications: { email: true, system: true, activity: true },
        notificationFrequency: 'immediately',
        privacySettings: { showEmail: false, showQQ: false, allowFriendRequests: false },
        displaySettings: { showLevel: true, showAchievements: true },
        updatedAt: new Date().toISOString()
      }));
      
      return {
        message: '数据库初始化完成',
        adminCreated: true,
        admin: {
          username: 'admin',
          password: 'test',
          email: 'admin@antister.com',
          role: 'admin'
        }
      };
    } else {
      return {
        message: '数据库已初始化',
        adminCreated: false
      };
    }
  } catch (error) {
    console.error('创建管理员账号失败:', error);
    throw error;
  }
}

// 初始化默认数据
async function initDefaultData() {
  try {
    // 检查是否已经初始化
    const users = await db.get('users');
    if (!users) {
      // 初始化用户列表 - 写操作是同步的，不需要await
      db.set('users', JSON.stringify([]));
      
    }
    
    // 创建管理员账号
    const result = await createAdminAccount();
    return result;
  } catch (error) {
    console.error('初始化默认数据失败:', error);
    throw error;
  }
}

// 执行初始化处理
// 检查请求方法 - req是全局变量
if (req.method === 'POST') {
  // 使用立即执行的异步函数，避免作用域问题
  (async () => {
    try {
      const result = await initDefaultData();
      // 返回响应 - res是全局变量
      res.status(200).json(result);
    } catch (error) {
      console.error('初始化失败:', error);
      // 返回响应 - res是全局变量
      res.status(500).json({ 
        error: '初始化失败',
        details: error.message || 'Unknown error'
      });
    }
  })().catch(error => {
    console.error('初始化处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ 
        error: '初始化失败，请稍后重试',
        details: error.message || 'Unknown error'
      });
    }
  });
} else if (req.method === 'GET') {
  // GET请求返回初始化状态信息
  // 使用立即执行的异步函数，避免作用域问题
  (async () => {
    try {
      const users = await db.get('users');
      let userIds = [];
      
      // 添加JSON解析错误处理
      try {
        userIds = JSON.parse(users || '[]');
      } catch (parseError) {
        console.error('解析用户列表失败:', parseError);
        // 如果解析失败，认为数据库未正确初始化
        res.status(200).json({
          initialized: false,
          userCount: 0,
          message: '数据库数据损坏，需要重新初始化',
          error: 'JSON解析失败'
        });
        return;
      }
      
      res.status(200).json({
        initialized: !!users,
        userCount: userIds.length,
        message: users ? '数据库已初始化' : '数据库未初始化，请使用 POST 方法进行初始化'
      });
    } catch (error) {
      console.error('检查初始化状态失败:', error);
      res.status(500).json({ 
        error: '检查初始化状态失败',
        details: error.message || 'Unknown error'
      });
    }
  })().catch(error => {
    console.error('检查初始化状态处理失败:', error);
    if (!res.headersSent) {
      res.status(500).json({ 
        error: '检查初始化状态失败',
        details: error.message || 'Unknown error'
      });
    }
  });
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
