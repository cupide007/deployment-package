// 获取管理员权限云函数
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
    db.delete(`session:${sessionId}`);
    return null;
  }
  
  // 检查Session是否过期
  if (now > expiresAt) {
    db.delete(`session:${sessionId}`);
    return null;
  }
  
  return session;
}

// 处理获取管理员权限请求
async function handleGetAdminPermissions() {
  try {
    // 获取Session ID - req是全局变量
    const sessionId = req.cookies.sessionId || req.headers['x-session-id'];
    
    // 验证Session
    const session = await verifySession(sessionId);
    if (!session) {
      res.status(401).json({ error: '未登录或登录已过期' });
      return;
    }
    
    // 获取管理员权限数据 - 读操作是异步的，需要await
    const permissionsData = await db.get('admin_permissions');
    
    if (permissionsData) {
      try {
        const permissions = JSON.parse(permissionsData);
        res.status(200).json({ permissions: permissions });
      } catch (parseError) {
        console.error('解析管理员权限数据失败:', parseError);
        res.status(500).json({ error: '管理员权限数据格式错误' });
      }
    } else {
      // 返回默认管理员权限数组
      res.status(200).json({ permissions: [] });
    }
  } catch (error) {
    console.error('获取管理员权限失败:', error);
    res.status(500).json({ error: '获取管理员权限失败，请稍后重试' });
  }
}

// 执行获取管理员权限处理
if (req.method === 'GET') {
  handleGetAdminPermissions().catch(error => {
    console.error('获取管理员权限处理失败:', error);
    if (!res.headersSent) {
      res.status(500).json({ error: '获取管理员权限失败，请稍后重试' });
    }
  });
} else {
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
