/**
 * 获取环境信息的云函数
 * 用于响应前端环境管理界面的请求
 */

try {
  // 引入环境检测工具
  // 注意：在云函数环境中，__dirname 是 server.js 所在的目录
  const envChecker = require('./check-environment.js');
  
  async function handleRequest() {
    try {
      // 执行检测并获取数据
      const report = await envChecker.getFullReport();
      
      // 返回结果
      res.json({
        success: true,
        data: report,
        message: '环境检测成功'
      });
    } catch (error) {
      console.error('环境检测失败:', error);
      res.status(500).json({
        success: false,
        error: '执行环境检测时出错',
        details: error.message
      });
    }
  }

  // 执行处理
  handleRequest();
} catch (e) {
  res.status(500).json({
    success: false,
    error: '模块引入失败',
    details: e.message
  });
}
