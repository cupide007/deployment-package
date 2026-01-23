// 用户登出云函数
// db 和 Database 由 server.js 通过参数传入，无需声明

// 处理登出请求
async function handleLogout() {
  try {
    // 获取Session ID - req是全局变量
    const sessionId = req.cookies.sessionId || req.headers['x-session-id'];
    
    if (sessionId) {
      // 删除Session - 写操作是同步的，不需要await
      db.delete(`session:${sessionId}`);
      
      // 清除Cookie - res是全局变量
      res.cookie('sessionId', '', {
        maxAge: 0,
        httpOnly: true,
        secure: req.secure || req.headers['x-forwarded-proto'] === 'https'
      });
    }
    
    // 返回成功响应 - res是全局变量
    res.status(200).json({ message: '登出成功' });
  } catch (error) {
    console.error('登出失败:', error);
    // 返回响应 - res是全局变量
    res.status(500).json({ error: '登出失败，请稍后重试' });
  }
}

// 执行登出处理
// 检查请求方法 - req是全局变量
if (req.method === 'POST') {
  // 处理异步函数的错误
  handleLogout().catch(error => {
    console.error('登出处理失败:', error);
    // 如果响应尚未发送，则发送错误响应
    if (!res.headersSent) {
      res.status(500).json({ error: '登出失败，请稍后重试' });
    }
  });
} else {
  // 不支持的请求方法 - res是全局变量
  res.status(405).json({ error: `Unsupported method ('${req.method}')` });
}