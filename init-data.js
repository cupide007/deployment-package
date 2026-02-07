document.addEventListener('DOMContentLoaded', async () => {
    const waitForApi = async () => {
        while (!window.retinbox) {
            await new Promise(r => setTimeout(r, 100));
        }
    };

    await waitForApi();
    console.log('系统启动，正在检查后端连接...');

    try {
        const status = await window.retinbox.request('init', 'GET');

        if (status.initialized) {
            console.log('数据库已就绪');
        } else {
            console.warn('数据库未初始化，正在执行初始化...');

            const initRes = await window.retinbox.request('init', 'POST');

            if (initRes.success) {
                console.log('🎉 初始化成功！');
                console.log('👤 默认管理员账号已创建:');
                console.log('   账号: admin');
                console.log('   密码: test');
                console.log('   邮箱: admin@antister.com');

                if (confirm('系统初始化成功！\n默认管理员账号：admin\n密码：test\n\n是否立即登录？')) {
                    document.getElementById('login-btn')?.click();
                }
            }
        }
    } catch (error) {
        console.error('初始化检查失败:', error);
        console.error('请确保 PHP 环境正常，且 common.php 中的数据库连接正确。');
    }
});