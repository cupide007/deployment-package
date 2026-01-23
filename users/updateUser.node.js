// 更新用户信息云函数
// db 和 Database 由 server.js 通过参数传入，无需声明

// 验证Session
async function verifySession(sessionId) {
  if (!sessionId) return null;
  const sessionData = await db.get(`session:${sessionId}`);
  if (!sessionData) return null;

  const session = JSON.parse(sessionData);
  const now = new Date();
  const expiresAt = new Date(session.expiresAt);

  if (now > expiresAt) {
    db.delete(`session:${sessionId}`);
    return null;
  }
  return session;
}

// 处理更新用户信息请求
async function handleUpdateUser() {
  try {
    // 获取Session ID
    const sessionId = req.cookies.sessionId || req.headers['x-session-id'];

    // 验证Session
    const session = await verifySession(sessionId);
    if (!session) {
      res.status(401).json({ error: '未登录或登录已过期' });
      return;
    }

    // 获取操作者信息
    const operatorId = session.userId;
    const operatorDataRaw = await db.get(`user:${operatorId}`);
    if (!operatorDataRaw) {
      res.status(401).json({ error: '操作用户不存在' });
      return;
    }
    const operator = JSON.parse(operatorDataRaw);
    const isAdmin = operator.role === 'admin';

    // 获取请求数据
    let updateData = req.body;
    if (!updateData || Object.keys(updateData).length === 0) {
      updateData = req.query;
    }

    // 确定目标用户ID
    let targetUserId = operatorId; // 默认更新自己

    if (updateData.id && String(updateData.id) !== String(operatorId)) {
      if (isAdmin) {
        targetUserId = updateData.id;
      } else {
        res.status(403).json({ error: '无权修改其他用户信息' });
        return;
      }
    }

    // 获取目标用户数据
    const userData = await db.get(`user:${targetUserId}`);
    if (!userData) {
      res.status(404).json({ error: '目标用户不存在' });
      return;
    }

    const user = JSON.parse(userData);

    // 允许更新的字段
    const allowedFields = ['username', 'email', 'qq', 'gender', 'race', 'age', 'residence', 'bio', 'avatar'];
    if (isAdmin) {
      allowedFields.push('role');
    }

    const filteredUpdateData = {};
    for (const field of allowedFields) {
      if (Object.prototype.hasOwnProperty.call(updateData, field)) {
        filteredUpdateData[field] = updateData[field];
      }
    }

    if (Object.keys(filteredUpdateData).length === 0) {
      res.status(400).json({ error: '请提供要更新的数据' });
      return;
    }

    // 更新用户数据
    const updatedUser = { ...user, ...filteredUpdateData };
    db.set(`user:${targetUserId}`, JSON.stringify(updatedUser));

    // 返回更新后的用户信息
    res.status(200).json({
      message: '用户信息更新成功',
      user: {
        id: updatedUser.id,
        username: updatedUser.username,
        email: updatedUser.email,
        role: updatedUser.role,
        createdAt: updatedUser.createdAt,
        lastLogin: updatedUser.lastLogin
      }
    });
  } catch (error) {
    console.error('更新用户信息失败:', error);
    res.status(500).json({ error: '更新用户信息失败，请稍后重试' });
  }
}

// 执行更新用户信息处理
if (req.method === 'POST') {
  handleUpdateUser();
} else {
  res.status(405).json({ error: "Unsupported method ('" + req.method + "')" });
}