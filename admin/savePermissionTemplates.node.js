// 保存权限模板云函数
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

// 处理保存权限模板请求
async function handleSavePermissionTemplates() {
  try {
    // 获取Session ID - req是全局变量
    const sessionId = req.cookies.sessionId || req.headers['x-session-id'];
    
    // 验证Session
    const session = await verifySession(sessionId);
    if (!session) {
      // 返回响应 - res是全局变量
      res.status(401).json({ error: '未登录或登录已过期' });
      return;
    }
    
    // 获取请求体中的权限模板数据
    const { templates } = req.body;
    
    if (!templates || !Array.isArray(templates)) {
      // 返回响应 - res是全局变量
      res.status(400).json({ error: '权限模板数据格式错误' });
      return;
    }
    
    // 保存权限模板数据 - 写操作是同步的，不需要await
    db.set('permission_templates', JSON.stringify(templates));
    
    // 返回成功响应 - res是全局变量
    res.status(200).json({ message: '权限模板保存成功', templates: templates });
  } catch (error) {
    console.error('保存权限模板失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '保存权限模板失败，请稍后重试' });
  }
}

// 执行保存权限模板处理
// 检查请求方法 - req是全局变量
if (req.method === 'POST') {
  // 处理异步函数的错误
  handleSavePermissionTemplates().catch(error => {
    console.error('保存权限模板处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ error: '保存权限模板失败，请稍后重试' });
    }
  });
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}
