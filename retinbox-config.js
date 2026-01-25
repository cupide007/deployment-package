// Retinbox 配置文件
// 云函数调用封装

// 云函数基础URL
const CLOUD_FUNCTIONS_BASE_URL = '/'; // 使用相对路径，确保从任何页面调用都能正确访问云函数

const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// 云函数调用封装
class RetinboxCloudFunctions {
  constructor() {
    this.baseUrl = CLOUD_FUNCTIONS_BASE_URL;
    this.inflightRequests = new Map();
  }

  // 通用请求方法
  async request(endpoint, method = 'GET', data = null) {
    const inflightKey = method === 'GET' ? `${method}:${endpoint}` : null;
    if (inflightKey && this.inflightRequests.has(inflightKey)) {
      return this.inflightRequests.get(inflightKey);
    }
    const execute = async (attempt = 0) => {
      const options = {
        method,
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include', // 包含cookie
      };

      if (data) {
        options.body = JSON.stringify(data);
      }

      // 构造正确的URL，避免双斜杠
      const url = `${this.baseUrl}${this.baseUrl.endsWith('/') ? '' : '/'}${endpoint.startsWith('/') ? endpoint.slice(1) : endpoint}`;
      const response = await fetch(url, options);
      
      // 读取响应体内容，只读取一次
      const responseText = await response.text();
      
      // 先检查响应状态
      if (!response.ok) {
        if (response.status === 429 && attempt < 2) {
          await delay(500 * (attempt + 1));
          return execute(attempt + 1);
        }
        // 尝试解析错误响应
        let errorMessage = `请求失败 (${response.status})`;
        try {
          const errorData = JSON.parse(responseText);
          errorMessage = errorData.error || errorMessage;
        } catch (e) {
          // 如果无法解析JSON，获取响应文本并提供更有用的错误信息
          // 检查响应文本是否包含注释，这可能是因为直接访问了源文件
          if (responseText.startsWith('//')) {
            errorMessage = `${response.status}: 可能是直接访问了源文件而不是通过服务器执行云函数`;
          } else {
            errorMessage = `${response.status}: ${response.statusText}`;
          }
        }
        throw new Error(errorMessage);
      }
      
      // 尝试解析JSON响应
      try {
        const result = JSON.parse(responseText);
        return result;
      } catch (jsonError) {
        // 如果JSON解析失败，提供更有用的错误信息
        // 检查响应文本是否包含注释，这可能是因为直接访问了源文件
        if (responseText.startsWith('//')) {
          throw new Error(`无法解析响应为JSON: 可能是直接访问了源文件而不是通过服务器执行。响应内容: ${responseText.substring(0, 100)}...`);
        } else {
          throw new Error(`无法解析响应为JSON: ${jsonError.message}。响应内容: ${responseText.substring(0, 100)}...`);
        }
      }
    };
    const requestPromise = execute().catch((error) => {
      console.error(`云函数请求失败 [${method} ${endpoint}]:`, error);
      throw error;
    }).finally(() => {
      if (inflightKey) {
        this.inflightRequests.delete(inflightKey);
      }
    });
    if (inflightKey) {
      this.inflightRequests.set(inflightKey, requestPromise);
    }
    return requestPromise;
  }

  // 初始化相关云函数
  async initDatabase() {
    // 优先使用 PHP 版本，如果失败则尝试 Node.js 版本
    try {
      const result = await this.request('init.php', 'POST');
      // 检查是否成功
      if (result && result.success !== false) {
        return result;
      }
      throw new Error(result.error || 'PHP 初始化返回失败状态');
    } catch (phpError) {
      console.warn('PHP 初始化失败，尝试 Node.js 版本:', phpError.message);
      try {
        const result = await this.request('init.node.js', 'POST');
        if (result && result.success !== false) {
          return result;
        }
        throw new Error(result.error || 'Node.js 初始化返回失败状态');
      } catch (nodeError) {
        console.error('Node.js 初始化也失败:', nodeError.message);
        throw new Error(`初始化失败: ${phpError.message} / ${nodeError.message}`);
      }
    }
  }

  async checkInitStatus() {
    // 优先使用 PHP 版本，如果失败则尝试 Node.js 版本
    try {
      const result = await this.request('init.php', 'GET');
      if (result && result.success !== false) {
        return result;
      }
      throw new Error(result.error || 'PHP 状态查询返回失败状态');
    } catch (phpError) {
      console.warn('PHP 状态查询失败，尝试 Node.js 版本:', phpError.message);
      try {
        const result = await this.request('init.node.js', 'GET');
        if (result && result.success !== false) {
          return result;
        }
        throw new Error(result.error || 'Node.js 状态查询返回失败状态');
      } catch (nodeError) {
        console.error('Node.js 状态查询也失败:', nodeError.message);
        throw new Error(`状态查询失败: ${phpError.message} / ${nodeError.message}`);
      }
    }
  }

  // 认证相关云函数
  async register(email, password, username) {
    return this.request('auth/register.php', 'POST', { email, password, username });
  }

  async login(email, password) {
    return this.request('auth/login.php', 'POST', { email, password });
  }

  async getCurrentUser() {
    // 优先使用 PHP 版本，如果失败则尝试 Node.js 版本
    try {
      const result = await this.request('auth/getCurrentUser.php');
      // 检查是否成功
      if (result && !result.error) {
        return result;
      }
      throw new Error(result.error || 'PHP 获取用户信息返回失败状态');
    } catch (phpError) {
      console.warn('PHP 获取用户信息失败，尝试 Node.js 版本:', phpError.message);
      try {
        const result = await this.request('auth/getCurrentUser.node.js');
        if (result && !result.error) {
          return result;
        }
        throw new Error(result.error || 'Node.js 获取用户信息返回失败状态');
      } catch (nodeError) {
        console.error('Node.js 获取用户信息也失败:', nodeError.message);
        throw new Error(`获取用户信息失败: ${phpError.message} / ${nodeError.message}`);
      }
    }
  }

  async logout() {
    return this.request('auth/logout.php', 'POST');
  }

  // 用户数据相关云函数
  async getUser(userId) {
    return this.request(`users/getUser.php?userId=${userId}`);
  }

  async updateUser(profileData) {
    return this.request('users/updateUser.php', 'POST', profileData);
  }

  async deleteUser(userId) {
    return this.request('users/deleteUser.php', 'POST', { id: userId });
  }

  async getUsers() {
    return this.request('users/getUsers.php');
  }

  // 游戏进度相关云函数
  async getGameProgress() {
    return this.request('game/getProgress.php');
  }

  async saveGameProgress(progressData) {
    return this.request('game/saveProgress.php', 'POST', progressData);
  }

  // 用户设置相关云函数
  async getUserSettings() {
    return this.request('settings/getSettings.php');
  }

  async updateUserSettings(settingsData) {
    return this.request('settings/updateSettings.php', 'POST', settingsData);
  }

  // 银行相关云函数
  async getBankAccount() {
    return this.request('bank/getAccount.php');
  }

  async createBankAccount() {
    return this.request('bank/createAccount.php', 'POST');
  }

  async updateBalance(amount, type, description) {
    return this.request('bank/updateBalance.php', 'POST', { amount, type, description });
  }

  async getTransactions() {
    return this.request('bank/getTransactions.php');
  }

  async addTransaction(amount, type, description) {
    return this.request('bank/addTransaction.php', 'POST', { amount, type, description });
  }

  async saveTransactions(transactions) {
    let payload = transactions;
    if (payload === undefined || payload === null || payload === 'undefined') {
      payload = [];
    }
    if (Array.isArray(payload) === false) {
      payload = [payload];
    }
    return this.request('bank/saveTransactions.php', 'POST', { transactions: payload });
  }

  // 银行相关云函数（补充银行卡片管理）
  async getBankCards() {
    return this.request('bank/getCards.php');
  }

  async saveBankCards(cards) {
    let payload = cards;
    if (payload === undefined || payload === null || payload === 'undefined') {
      payload = [];
    }
    if (Array.isArray(payload) === false) {
      payload = [payload];
    }
    return this.request('bank/saveCards.php', 'POST', { cards: payload });
  }

  // 图书馆相关云函数
  async getLibraryRecords() {
    return this.request('library/getRecords.php');
  }

  async addLibraryRecord(bookId, bookTitle) {
    return this.request('library/addRecord.php', 'POST', { bookId, bookTitle });
  }

  async returnBook(recordId) {
    return this.request('library/returnBook.php', 'POST', { recordId });
  }
  
  // 文档管理相关云函数
  async getDocuments() {
    return this.request('library/getDocuments.php');
  }

  async saveDocuments(documents) {
    return this.request('library/saveDocuments.php', 'POST', { documents });
  }

  // 管理后台相关云函数
  async getPermissionTemplates() {
    return this.request('admin/getPermissionTemplates.php');
  }

  async savePermissionTemplates(templates) {
    return this.request('admin/savePermissionTemplates.php', 'POST', { templates });
  }

  async getAdminPermissions() {
    return this.request('admin/getAdminPermissions.php');
  }

  async saveAdminPermissions(permissions) {
    return this.request('admin/saveAdminPermissions.php', 'POST', { permissions });
  }

  async getAdminLogs() {
    return this.request('admin/getAdminLogs.php');
  }

  async saveAdminLogs(logs) {
    return this.request('admin/saveAdminLogs.php', 'POST', { logs });
  }

  // 系统日志相关云函数
  async getSystemLogs() {
    return this.request('bank/getSystemLogs.php');
  }

  async saveSystemLogs(logs) {
    return this.request('bank/saveSystemLogs.php', 'POST', { logs });
  }
}

// 用户数据管理类（替换原Firebase实现）
class UserDataManager {
  constructor() {
    this.cloudFunctions = new RetinboxCloudFunctions();
    this.currentUser = null;
    this.authListeners = [];
    this.lastLoginCheck = 0;
    this.loginCheckPromise = null;
    
    // 初始化时检查登录状态
    this.checkLoginStatus();
  }
  
  // 初始化数据库（新增方法）
  async initDatabase() {
    try {
      const result = await this.cloudFunctions.initDatabase();
      return result;
    } catch (error) {
      console.error('初始化数据库失败:', error);
      throw error;
    }
  }
  
  // 检查初始化状态（新增方法）
  async checkInitStatus() {
    try {
      const result = await this.cloudFunctions.checkInitStatus();
      return result;
    } catch (error) {
      console.error('检查初始化状态失败:', error);
      throw error;
    }
  }
  
  // 检查登录状态
  async checkLoginStatus() {
    const now = Date.now();
    if (this.loginCheckPromise && now - this.lastLoginCheck < 5000) {
      return this.loginCheckPromise;
    }
    this.lastLoginCheck = now;
    this.loginCheckPromise = this._performLoginStatusCheck();
    try {
      return await this.loginCheckPromise;
    } finally {
      this.loginCheckPromise = null;
    }
  }

  async _performLoginStatusCheck() {
    try {
      // 首先检查sessionStorage中的本地登录状态
      const localUser = sessionStorage.getItem('currentUser');
      if (localUser) {
        try {
          this.currentUser = JSON.parse(localUser);
        } catch (e) {
          console.error('解析本地用户信息失败:', e);
          this.currentUser = null;
        }
      }
      
      // 尝试验证服务器端session（可选，失败不影响本地状态）
      try {
        const result = await this.cloudFunctions.getCurrentUser();
        // 更严格地验证返回结果
        if (result && typeof result === 'object' && result.user) {
          // 服务器验证成功，更新本地用户信息
          this.currentUser = result.user;
          sessionStorage.setItem('currentUser', JSON.stringify(result.user));
          this.notifyAuthListeners();
          this.setupRealTimeListeners();
        } else if (this.currentUser) {
          // 服务器session可能过期，但保留本地状态
          console.warn('服务器session可能已过期，使用本地缓存的用户信息');
          this.notifyAuthListeners();
        } else {
          // 既没有服务器session也没有本地状态，用户未登录
          this.currentUser = null;
          this.notifyAuthListeners();
        }
      } catch (verifyError) {
        console.warn('验证服务器session失败:', verifyError.message);
        // 验证失败但有本地状态，继续使用本地状态
        if (this.currentUser) {
          
          this.notifyAuthListeners();
        } else {
          // 既无法验证也没有本地状态
          this.currentUser = null;
          this.notifyAuthListeners();
        }
      }
    } catch (error) {
      console.error('检查登录状态失败:', error);
      // 出现严重错误，清除状态
      this.currentUser = null;
      this.notifyAuthListeners();
    }
  }
  
  // 监听用户认证状态变化
  onAuthStateChanged(callback) {
    this.authListeners.push(callback);
    // 立即调用一次，传递当前用户状态
    callback(this.currentUser);
    return () => {
      this.authListeners = this.authListeners.filter(listener => listener !== callback);
    };
  }
  
  // 通知所有认证监听器
  notifyAuthListeners() {
    this.authListeners.forEach(callback => {
      try {
        callback(this.currentUser);
      } catch (error) {
        console.error('认证监听器调用失败:', error);
      }
    });
  }
  
  // 设置实时数据监听器（模拟实现）
  setupRealTimeListeners() {
    // Retinbox KV数据库不支持实时监听，这里使用定期轮询模拟
    // 实际项目中可以根据需要调整轮询频率或使用其他方式实现
    // 
  }
  
  // 注册
  async register(email, password, username) {
    const result = await this.cloudFunctions.register(email, password, username);
    return result;
  }
  
  // 登录
  async login(email, password) {
    try {
      const result = await this.cloudFunctions.login(email, password);
      if (result.user) {
        this.currentUser = result.user;
        // 同时保存到sessionStorage，以便在云函数不可用时使用
        sessionStorage.setItem('currentUser', JSON.stringify(result.user));
        this.notifyAuthListeners();
        this.setupRealTimeListeners();
      }
      return result;
    } catch (error) {
      console.error('登录失败:', error);
      // 如果云函数失败，尝试使用本地存储的用户信息
      try {
        const localUser = sessionStorage.getItem('currentUser');
        if (localUser) {
          this.currentUser = JSON.parse(localUser);
          this.notifyAuthListeners();
          // 返回一个模拟的成功响应
          return {
            message: '本地登录成功（云函数不可用）',
            user: this.currentUser
          };
        }
      } catch (parseError) {
        console.error('解析本地用户信息失败:', parseError);
      }
      // 抛出原始错误
      throw error;
    }
  }
  
  // 登出
  async logout() {
    const result = await this.cloudFunctions.logout();
    this.currentUser = null;
    this.notifyAuthListeners();
    return result;
  }
  
  // 获取当前用户数据
  async getCurrentUserData() {
    if (!this.currentUser) return null;
    
    try {
      const result = await this.cloudFunctions.getUser(this.currentUser.id);
      return result.user;
    } catch (error) {
      console.error('获取当前用户数据失败:', error);
      return null;
    }
  }
  
  // 获取当前用户（安全版本）
  async getCurrentUserSafely() {
    try {
      if (!this.cloudFunctions) {
        console.warn('cloudFunctions 未初始化，使用本地缓存的用户信息');
        const localUser = sessionStorage.getItem('currentUser');
        return localUser ? JSON.parse(localUser) : null;
      }
      
      const result = await this.cloudFunctions.getCurrentUser();
      return result && result.user ? result.user : null;
    } catch (error) {
      console.warn('服务器登录状态检查失败，使用本地缓存的用户信息:', error.message);
      try {
        const localUser = sessionStorage.getItem('currentUser');
        return localUser ? JSON.parse(localUser) : null;
      } catch (parseError) {
        console.error('解析本地用户信息失败:', parseError);
        return null;
      }
    }
  }
  
  // 获取当前用户（兼容性方法）
  async getCurrentUser() {
    try {
      if (!this.cloudFunctions) {
        console.warn('cloudFunctions 未初始化，使用本地缓存的用户信息');
        const localUser = sessionStorage.getItem('currentUser');
        return localUser ? JSON.parse(localUser) : null;
      }
      
      const result = await this.cloudFunctions.getCurrentUser();
      return result && result.user ? result.user : null;
    } catch (error) {
      console.warn('服务器登录状态检查失败，使用本地缓存的用户信息:', error.message);
      try {
        const localUser = sessionStorage.getItem('currentUser');
        return localUser ? JSON.parse(localUser) : null;
      } catch (parseError) {
        console.error('解析本地用户信息失败:', parseError);
        return null;
      }
    }
  }
  
  // 获取用户列表
  async getUsers() {
    if (!this.currentUser) {
      // 检查是否有管理员权限或是否允许访问用户列表
      // 这里可以根据实际需求决定是否需要用户登录才能访问
    }
    
    try {
      const result = await this.cloudFunctions.getUsers();
      return result;
    } catch (error) {
      console.error('获取用户列表失败:', error);
      throw error; // 重新抛出错误，让调用者处理
    }
  }

  async deleteUser(userId) {
    if (!this.currentUser) throw new Error('用户未登录');
    // 这里可以添加额外的前端权限检查逻辑

    const result = await this.cloudFunctions.deleteUser(userId);
    return result;
  }
  
  // 更新用户基本信息
  async updateUserProfile(profileData) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.updateUser(profileData);
    return result;
  }


  
  // 保存游戏进度
  async saveGameProgress(progressData) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.saveGameProgress(progressData);
    return result;
  }
  
  // 获取游戏进度
  async getGameProgress() {
    if (!this.currentUser) return null;
    
    try {
      const result = await this.cloudFunctions.getGameProgress();
      return result.progress;
    } catch (error) {
      console.error('获取游戏进度失败:', error);
      return null;
    }
  }
  
  // 更新个人设置
  async updateUserSettings(settingsData) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.updateUserSettings(settingsData);
    return result;
  }
  
  // 获取个人设置
  async getUserSettings() {
    if (!this.currentUser) return null;
    
    try {
      const result = await this.cloudFunctions.getUserSettings();
      return result.settings;
    } catch (error) {
      console.error('获取用户设置失败:', error);
      return null;
    }
  }
  
  // 银行相关方法
  async getBankAccount() {
    if (!this.currentUser) return null;
    
    try {
      const result = await this.cloudFunctions.getBankAccount();
      return result.account;
    } catch (error) {
      console.error('获取银行账户失败:', error);
      return null;
    }
  }
  
  async createBankAccount() {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.createBankAccount();
    return result;
  }
  
  async addTransaction(amount, type, description) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.addTransaction(amount, type, description);
    return result;
  }
  
  async getTransactions() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getTransactions();
      return result.transactions;
    } catch (error) {
      console.error('获取交易记录失败:', error);
      return null;
    }
  }

  async saveTransactions(transactions) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.saveTransactions(transactions);
    return result;
  }
  
  // 银行相关方法（补充银行卡片管理）
  async getBankCards() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getBankCards();
      return result.cards;
    } catch (error) {
      console.error('获取银行卡片失败:', error);
      return null;
    }
  }

  async saveBankCards(cards) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.saveBankCards(cards);
    return result;
  }

  // 图书馆相关方法
  async getLibraryRecords() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getLibraryRecords();
      return result.records;
    } catch (error) {
      console.error('获取图书馆记录失败:', error);
      return [];
    }
  }
  
  async addLibraryRecord(bookId, bookTitle) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.addLibraryRecord(bookId, bookTitle);
    return result;
  }
  
  async returnBook(recordId) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.returnBook(recordId);
    return result;
  }
  
  // 文档管理相关方法
  async getDocuments() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getDocuments();
      return result.documents;
    } catch (error) {
      console.error('获取文档失败:', error);
      return null;
    }
  }

  async saveDocuments(documents) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.saveDocuments(documents);
    return result;
  }

  // 管理后台相关方法
  async getPermissionTemplates() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getPermissionTemplates();
      return result.templates;
    } catch (error) {
      console.error('获取权限模板失败:', error);
      return null;
    }
  }

  async savePermissionTemplates(templates) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.savePermissionTemplates(templates);
    return result;
  }

  async getAdminPermissions() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getAdminPermissions();
      return result.permissions;
    } catch (error) {
      console.error('获取管理员权限失败:', error);
      return null;
    }
  }

  async saveAdminPermissions(permissions) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.saveAdminPermissions(permissions);
    return result;
  }

  async getAdminLogs() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getAdminLogs();
      return result.logs;
    } catch (error) {
      console.error('获取管理员日志失败:', error);
      return null;
    }
  }

  async saveAdminLogs(logs) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.saveAdminLogs(logs);
    return result;
  }

  // 系统日志相关方法
  async getSystemLogs() {
    if (!this.currentUser) return [];
    
    try {
      const result = await this.cloudFunctions.getSystemLogs();
      return result.logs;
    } catch (error) {
      console.error('获取系统日志失败:', error);
      return null;
    }
  }

  async saveSystemLogs(logs) {
    if (!this.currentUser) throw new Error('用户未登录');
    
    const result = await this.cloudFunctions.saveSystemLogs(logs);
    return result;
  }
}

// 创建全局实例，挂载到window对象上，方便其他脚本访问
// 添加延迟初始化以确保对象被正确创建
function initializeRetinboxConfig() {
  try {
    if (!window.retinboxCloudFunctions) {
      window.retinboxCloudFunctions = new RetinboxCloudFunctions();
    }
    if (!window.userDataManager) {
      window.userDataManager = new UserDataManager();
    }
  } catch (error) {
    console.error('初始化Retinbox配置失败:', error);
    // 创建备用对象以防构造函数失败
    window.retinboxCloudFunctions = {
      getCurrentUser: async () => { throw new Error('配置未正确初始化'); },
      request: async () => { throw new Error('配置未正确初始化'); }
    };
    window.userDataManager = {
      currentUser: null,
      checkLoginStatus: async () => {},
      onAuthStateChanged: () => () => {}
    };
  }
}

// 立即初始化
initializeRetinboxConfig();

// 额外的安全检查：如果文档已经加载完成，立即执行初始化
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeRetinboxConfig);
} else {
  // 如果文档已经加载完成，稍后执行初始化
  setTimeout(initializeRetinboxConfig, 0);
}

// 安全的云函数调用辅助函数
window.safeCallCloudFunction = async function(functionName, ...args) {
  try {
    // 确保云函数对象存在
    if (!window.retinboxCloudFunctions) {
      throw new Error('retinboxCloudFunctions 未初始化');
    }
    
    const func = window.retinboxCloudFunctions[functionName];
    if (typeof func !== 'function') {
      throw new Error(`云函数 ${functionName} 不存在或不是函数`);
    }
    
    return await func.apply(window.retinboxCloudFunctions, args);
  } catch (error) {
    console.error(`调用云函数 ${functionName} 失败:`, error);
    throw error;
  }
};

// 安全的登录状态检查函数
window.checkLoginStatusSafely = async function() {
  try {
    if (!window.retinboxCloudFunctions) {
      console.warn('retinboxCloudFunctions 未初始化，检查本地存储中的用户信息');
      const localUser = sessionStorage.getItem('currentUser');
      return localUser ? JSON.parse(localUser) : null;
    }
    
    const result = await window.safeCallCloudFunction('getCurrentUser');
    return result && result.user ? result.user : null;
  } catch (error) {
    console.warn('服务器登录状态检查失败，使用本地缓存的用户信息:', error.message);
    try {
      const localUser = sessionStorage.getItem('currentUser');
      return localUser ? JSON.parse(localUser) : null;
    } catch (parseError) {
      console.error('解析本地用户信息失败:', parseError);
      return null;
    }
  }
};

// 安全的数据访问函数（支持云函数和localStorage降级）
window.safeGetData = async function(dataType, defaultValue = []) {
  try {
    // 首先尝试通过云函数获取数据
    if (window.userDataManager) {
      switch(dataType) {
        case 'bankCards':
          return await window.userDataManager.getBankCards() ?? defaultValue;
        case 'documents':
          return await window.userDataManager.getDocuments() ?? defaultValue;
        case 'transactions':
          return await window.userDataManager.getTransactions() ?? defaultValue;
        case 'permissionTemplates':
          return await window.userDataManager.getPermissionTemplates() ?? defaultValue;
        case 'adminPermissions':
          return await window.userDataManager.getAdminPermissions() ?? defaultValue;
        case 'adminLogs':
          return await window.userDataManager.getAdminLogs() ?? defaultValue;
        case 'systemLogs':
          return await window.userDataManager.getSystemLogs() ?? defaultValue;
        default:
          console.warn(`未知的数据类型: ${dataType}`);
          return defaultValue;
      }
    } else {
      console.warn(`${dataType} 云函数未初始化，尝试从localStorage获取`);
      // 降级到localStorage（仅为兼容现有代码，实际部署时应移除）
      const data = localStorage.getItem(dataType);
      return data ? JSON.parse(data) : defaultValue;
    }
  } catch (error) {
    console.warn(`获取 ${dataType} 失败，使用降级方案:`, error.message);
    try {
      // 降级到localStorage（仅为兼容现有代码，实际部署时应移除）
      const data = localStorage.getItem(dataType);
      return data ? JSON.parse(data) : defaultValue;
    } catch (parseError) {
      console.error(`解析本地 ${dataType} 数据失败:`, parseError);
      return defaultValue;
    }
  }
};

// 安全的数据保存函数（支持云函数和localStorage降级）
window.safeSaveData = async function(dataType, data) {
  try {
    // 首先尝试通过云函数保存数据
    if (window.userDataManager) {
      switch(dataType) {
        case 'bankCards':
          return await window.userDataManager.saveBankCards(data);
        case 'documents':
          return await window.userDataManager.saveDocuments(data);
        case 'permissionTemplates':
          return await window.userDataManager.savePermissionTemplates(data);
        case 'adminPermissions':
          return await window.userDataManager.saveAdminPermissions(data);
        case 'adminLogs':
          return await window.userDataManager.saveAdminLogs(data);
        case 'systemLogs':
          return await window.userDataManager.saveSystemLogs(data);
        case 'transactions':
          return await window.userDataManager.saveTransactions(data);
        default:
          console.warn(`未知的数据类型: ${dataType}`);
          return { error: '未知的数据类型' };
      }
    } else {
      console.warn(`${dataType} 云函数未初始化，尝试保存到localStorage（仅为兼容）`);
      // 降级到localStorage（仅为兼容现有代码，实际部署时应移除）
      localStorage.setItem(dataType, JSON.stringify(data));
      return { message: '数据已保存到本地（仅为兼容）' };
    }
  } catch (error) {
    console.warn(`保存 ${dataType} 失败，使用降级方案:`, error.message);
    try {
      // 降级到localStorage（仅为兼容现有代码，实际部署时应移除）
      localStorage.setItem(dataType, JSON.stringify(data));
      return { message: '数据已保存到本地（降级方案）' };
    } catch (saveError) {
      console.error(`保存本地 ${dataType} 数据失败:`, saveError);
      throw saveError;
    }
  }
};

const remoteStorageKeys = new Set([
  'bankCards',
  'documents',
  'transactions',
  'permissionTemplates',
  'adminPermissions',
  'adminLogs',
  'systemLogs'
]);
const remoteStorageCache = new Map();
const remoteStorageLoads = new Map();
const remoteStorageDirty = new Set();
const originalLocalStorageGetItem = localStorage.getItem.bind(localStorage);
const originalLocalStorageSetItem = localStorage.setItem.bind(localStorage);
const originalLocalStorageRemoveItem = localStorage.removeItem.bind(localStorage);

function fetchRemoteStorageKey(key) {
  if (remoteStorageLoads.has(key)) {
    return remoteStorageLoads.get(key);
  }
  const loader = (async () => {
    if (!window.userDataManager) return;
    let data;
    switch (key) {
      case 'bankCards':
        data = await window.userDataManager.getBankCards();
        break;
      case 'documents':
        data = await window.userDataManager.getDocuments();
        break;
      case 'transactions':
        data = await window.userDataManager.getTransactions();
        break;
      case 'permissionTemplates':
        data = await window.userDataManager.getPermissionTemplates();
        break;
      case 'adminPermissions':
        data = await window.userDataManager.getAdminPermissions();
        break;
      case 'adminLogs':
        data = await window.userDataManager.getAdminLogs();
        break;
      case 'systemLogs':
        data = await window.userDataManager.getSystemLogs();
        break;
      default:
        data = null;
    }
    if (data !== undefined && data !== null && !remoteStorageDirty.has(key)) {
      const existing = originalLocalStorageGetItem(key);
      let parsedExisting = null;
      if (existing) {
        try {
          parsedExisting = JSON.parse(existing);
        } catch (error) {
          parsedExisting = null;
        }
      }
      if (Array.isArray(parsedExisting) && parsedExisting.length > 0 && Array.isArray(data) && data.length === 0) {
        data = parsedExisting;
        remoteStorageCache.set(key, data);
        originalLocalStorageSetItem(key, JSON.stringify(data ?? []));
        const savePromise = saveRemoteStorageKey(key, data);
        if (savePromise && typeof savePromise.then === 'function') {
          savePromise.catch(() => {});
        }
        return;
      }
      if (key === 'documents' && Array.isArray(data) && Array.isArray(parsedExisting)) {
        const existingById = new Map(parsedExisting.filter(Boolean).map((doc) => [doc.id, doc]));
        const merged = data.map((doc) => {
          const existingDoc = doc && doc.id ? existingById.get(doc.id) : null;
          if (!existingDoc) return doc;
          if (doc && typeof doc === 'object' && (!doc.fileUrl || doc.fileUrl === '#') && existingDoc.fileUrl && existingDoc.fileUrl !== '#') {
            return { ...doc, fileUrl: existingDoc.fileUrl };
          }
          return doc;
        });
        const mergedIds = new Set(merged.filter(Boolean).map((doc) => doc.id));
        const additional = parsedExisting.filter((doc) => doc && !mergedIds.has(doc.id));
        data = merged.concat(additional);
      }
      remoteStorageCache.set(key, data);
      originalLocalStorageSetItem(key, JSON.stringify(data ?? []));
    }
  })();
  remoteStorageLoads.set(key, loader);
  return loader;
}

function saveRemoteStorageKey(key, data) {
  if (!window.userDataManager) return;
  let payload = data;
  if (payload === undefined || payload === null || payload === 'undefined') {
    payload = [];
  }
  if (Array.isArray(payload) === false) {
    payload = [payload];
  }
  switch (key) {
    case 'bankCards':
      return window.userDataManager.saveBankCards(payload);
    case 'documents':
      return window.userDataManager.saveDocuments(payload);
    case 'transactions':
      return window.userDataManager.saveTransactions(payload);
    case 'permissionTemplates':
      return window.userDataManager.savePermissionTemplates(payload);
    case 'adminPermissions':
      return window.userDataManager.saveAdminPermissions(payload);
    case 'adminLogs':
      return window.userDataManager.saveAdminLogs(payload);
    case 'systemLogs':
      return window.userDataManager.saveSystemLogs(payload);
    default:
      break;
  }
}

localStorage.getItem = function(key) {
  if (remoteStorageKeys.has(key)) {
    if (!remoteStorageCache.has(key)) {
      const existing = originalLocalStorageGetItem(key);
      if (existing) {
        try {
          remoteStorageCache.set(key, JSON.parse(existing));
        } catch (error) {
          remoteStorageCache.set(key, []);
        }
        fetchRemoteStorageKey(key);
        return existing;
      }
      remoteStorageCache.set(key, []);
      fetchRemoteStorageKey(key);
      return JSON.stringify([]);
    }
    const value = remoteStorageCache.get(key);
    if (value === undefined || value === null) return null;
    return JSON.stringify(value);
  }
  return originalLocalStorageGetItem(key);
};

localStorage.setItem = function(key, value) {
  if (remoteStorageKeys.has(key)) {
    let parsedValue = value;
    try {
      parsedValue = JSON.parse(value);
    } catch (error) {
      parsedValue = value;
    }
    if (parsedValue === undefined || parsedValue === null || parsedValue === 'undefined') {
      parsedValue = [];
    }
    remoteStorageDirty.add(key);
    remoteStorageCache.set(key, parsedValue);
    originalLocalStorageSetItem(key, JSON.stringify(parsedValue));
    let savePayload = parsedValue;
    if (key === 'documents' && Array.isArray(parsedValue)) {
      savePayload = parsedValue.map((doc) => {
        if (doc && typeof doc === 'object' && doc.fileUrl && typeof doc.fileUrl === 'string' && doc.fileUrl.startsWith('data:')) {
          return { ...doc, fileUrl: null };
        }
        return doc;
      });
    }
    const savePromise = saveRemoteStorageKey(key, savePayload);
    if (savePromise && typeof savePromise.then === 'function') {
      savePromise.then(() => {
        remoteStorageDirty.delete(key);
      }).catch(() => {});
    }
    return;
  }
  return originalLocalStorageSetItem(key, value);
};

localStorage.removeItem = function(key) {
  if (remoteStorageKeys.has(key)) {
    remoteStorageCache.delete(key);
    originalLocalStorageRemoveItem(key);
    remoteStorageDirty.add(key);
    const savePromise = saveRemoteStorageKey(key, []);
    if (savePromise && typeof savePromise.then === 'function') {
      savePromise.then(() => {
        remoteStorageDirty.delete(key);
      }).catch(() => {});
    }
    return;
  }
  return originalLocalStorageRemoveItem(key);
};
