# PHP 云函数

云函数让您可以运行 PHP 后端代码，处理动态 API 请求、操作文件、调用外部 API 等。只需将文件命名为 `.php` 结尾（如 `api.php`），代码就会在服务器后端执行。

## 第一个云函数

在文件列表中创建一个以 `.php` 结尾的 PHP 文件，例如 `hello.php`。这个文件将包含如下云函数代码：



```
<?php
echo "Hello, World!";
```

访问这个文件，您会看到「Hello, World!」。就这么简单！

由于代码在服务器上运行，当您通过浏览器开发者工具检查时，不会看到任何 PHP 代码，只会看到运行结果，因为代码是在服务器后端动态执行的。

## 获取请求信息

### 访问查询参数

您可以通过 [`$_GET`](https://www.php.net/manual/zh/reserved.variables.get.php) 访问 URL 查询参数。例如，当网址带有 `?name=Alice` 时，您可以这样获取 `name` 参数的值：



```
<?php
$name = $_GET["name"];
echo $name; // 输出 "Alice"
```

### 访问 POST 参数

对于 POST 请求，您可以通过 [`$_POST`](https://www.php.net/manual/zh/reserved.variables.post.php) 访问请求体中的参数。例如，假设您发送了一个包含 `message=Hello` 的 POST 请求，您可以这样获取 `message` 参数的值：



```
<?php
$message = $_POST["message"];
echo $message; // 输出 "Hello"
```

### 获取请求头

您可以通过 [`$_SERVER`](https://www.php.net/manual/zh/reserved.variables.server.php) 获取请求头。例如，获取名为 `Accept-Language` 的请求头：



```
<?php
$lang = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
echo $lang;
```

### 获取 Cookie

您可以通过 [`$_COOKIE`](https://www.php.net/manual/zh/reserved.variables.cookies.php) 访问请求中的 Cookie。例如，获取名为 `sessionId` 的 Cookie：



```
<?php
$sid = $_COOKIE["sessionId"];
echo $sid;
```

### 获取用户代理

您可以从请求头中获取用户代理信息，了解访客使用的浏览器和操作系统。



```
<?php
$ua = $_SERVER["HTTP_USER_AGENT"];
echo $ua;
```

### 获取来源网址

您可以从请求头中获取请求的来源网址，即用户从哪个页面来到当前页面。



```
<?php
$ref = $_SERVER["HTTP_REFERER"];
echo $ref;
```

### 获取访客 IP 地址

您可以通过 `$_SERVER["REMOTE_ADDR"]` 获取访客的 IP 地址：



```
<?php
$ip = $_SERVER["REMOTE_ADDR"];
echo $ip;
```

### 获取请求方法

您可以通过 `$_SERVER["REQUEST_METHOD"]` 获取当前请求方法（如 GET、POST 等）：



```
<?php
$method = $_SERVER["REQUEST_METHOD"];
echo $method; // 输出 "GET" 或 "POST"
```

## 响应请求

### 输出内容

您可以使用 `echo` 输出内容。例如：



```
<?php
echo "Hello, World!";
```

它可以被多次调用，输出的内容会按调用顺序拼接在一起，作为响应体发送给客户端。



```
<?php
echo "Hello, ";
echo "World";
echo "!";
```

您会看到输出结果为 "Hello, World!"。

### 输出 JSON

如果您想输出 JSON 格式的数据，可以输出经过 [`json_encode`](https://www.php.net/manual/zh/function.json-encode.php) 函数编码的数组：



```
<?php
$data = ["message" => "Hello, JSON!"];
echo json_encode($data);
```

您会看到输出结果为 `{"message": "Hello, JSON!"}`。

### 设置响应状态码

默认情况下，状态码为 200（成功）。您可以使用 [`http_response_code`](https://www.php.net/manual/zh/function.http-response-code.php) 函数更改响应的 HTTP 状态码。例如，设置状态码为 404（未找到）：



```
<?php
http_response_code(404);
```

您可以在 [MDN](https://developer.mozilla.org/zh-CN/docs/Web/HTTP/Status) 或 [HTTP Cats](https://http.cat/) 查看各个状态码的含义。

### 设置响应头

您可以使用 [`header`](https://www.php.net/manual/zh/function.header.php) 函数设置响应头。例如，设置缓存时长为 1 小时：



```
<?php
header("Cache-Control: max-age=3600");
```

### 设置 Cookie

您可以使用 [`setcookie`](https://www.php.net/manual/zh/function.setcookie.php) 函数设置 Cookie。例如，设置一个名为 `username` 的 Cookie，值为 `Alice`，并设置过期时间为 7 天：



```
<?php
setcookie("username", "Alice", time() + (7 * 24 * 60 * 60));
```

### 重定向跳转

您可以通过设置 `Location` 响应头来实现重定向跳转。例如，将当前请求重定向到百度首页：



```
<?php
header("Location: https://www.baidu.com/");
exit;
```

### 提前退出程序

如果您想在某些条件下提前终止程序执行，可以使用 [`exit`](https://www.php.net/manual/zh/function.exit.php)，这会立即停止程序运行。



```
<?php
echo "1";
exit;
echo "2";
```

您会看到输出结果只有 "1"，因为程序在执行到 `exit` 时就停止了，后续代码不会被执行。

## 使用 Session

Session 允许您在多个请求之间存储用户的临时数据，例如登录状态、购物车内容等。Session 数据存储在服务器端，通过 Cookie 中的 Session ID 来识别用户。

### 启动 Session

在使用 Session 之前，需要先调用 [`session_start`](https://www.php.net/manual/zh/function.session-start.php) 启动 Session。这个函数必须在任何输出（包括空格和换行）之前调用。



```
<?php
session_start();
```

### 存储 Session 数据

您可以通过 [`$_SESSION`](https://www.php.net/manual/zh/reserved.variables.session.php) 超全局数组存储数据。例如，存储用户名：



```
<?php
session_start();
$_SESSION["username"] = "Alice";
echo "用户名已保存";
```

### 读取 Session 数据

在其他页面或下次请求时，只要调用了 `session_start()`，就可以读取之前存储的 Session 数据：



```
<?php
session_start();
if (isset($_SESSION["username"])) {
	echo "欢迎回来，" . $_SESSION["username"];
} else {
	echo "请先登录";
}
```

### 删除 Session 数据

您可以使用 `unset` 删除特定的 Session 变量：



```
<?php
session_start();
unset($_SESSION["username"]);
echo "用户名已删除";
```

### 销毁整个 Session

如果您想清除所有 Session 数据（例如用户退出登录时），可以使用 [`session_destroy`](https://www.php.net/manual/zh/function.session-destroy.php)：



```
<?php
session_start();
session_destroy();
echo "Session 已销毁";
```

## 读写文件

### 读取文件

您可以使用 [`file_get_contents`](https://www.php.net/manual/zh/function.file-get-contents.php) 读取文件内容。例如，读取当前网站下的 `data.txt` 文件：



```
<?php
$content = file_get_contents("data.txt");
echo $content;
```

请注意

如果您要读写的文件位于某个文件夹中，不管云函数文件在哪里，都需要使用完整的绝对路径。在热铁盒网页托管，`/` 是文件名的一部分，因此不支持相对路径。假设您要读写的文件在 `databases` 文件夹中，那么您的代码应该是这样的：



```
<?php
$content = file_get_contents("databases/data.txt");
echo $content;
```

不要使用 `__DIR__` 常量获取当前文件夹路径，会导致路径错误。

### 读取 JSON 文件

您可以使用 `file_get_contents` 读取 JSON 文件内容，然后通过 `json_decode` 将其转换为 PHP 数组。例如，读取当前网站下的 `data.json` 文件：



```
<?php
$data = json_decode(file_get_contents("data.json"), true);
```

### 写入文件

您可以使用 [`file_put_contents`](https://www.php.net/manual/zh/function.file-put-contents.php) 写入文件内容。例如，向当前网站下的 `data.txt` 文件写入新内容覆盖原有内容：



```
<?php
file_put_contents("data.txt", "Hello, File!");
```

出于安全原因，不能写入 CSS、JavaScript、PHP 等类型的代码文件。

请注意

由于技术限制，目前只有当网站的文本文件总大小不超过 4 MB 时，才能读写文件和引入其他文件。您可以在写入前使用 [`disk_free_space`](https://www.php.net/manual/zh/function.disk-free-space.php) 检查剩余空间：



```
<?php
$free_space = disk_free_space(".");
echo "剩余空间：$free_space 字节";
```

如需存储用户数据，请使用[数据库](https://docs.retiehe.com/host/database.html)。数据库不限制总空间大小。

### 写入 JSON 文件

您可以使用 `json_encode` 将 PHP 数组转换为字符串，然后使用 `file_put_contents` 写入 JSON 文件。例如，向当前网站下的 `data.json` 文件写入数据：



```
<?php
$data = ["key" => "value"];
file_put_contents("data.json", json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
```

### 列出文件

您可以使用 [`scandir`](https://www.php.net/manual/zh/function.scandir.php) 列出当前网站下的所有文件和文件夹。例如，列出当前网站下的所有文件：



```
<?php
$files = scandir(".");
echo implode("<br>", $files);
```

## 引入其他文件

您可以使用 [`require_once`](https://www.php.net/manual/zh/function.require-once.php)、[`require`](https://www.php.net/manual/zh/function.require.php)、[`include_once`](https://www.php.net/manual/zh/function.include-once.php) 或 [`include`](https://www.php.net/manual/zh/function.include.php) 引入同网站上的其他 PHP 文件，实现代码模块化。

### 使用 require_once 引入

`require_once` 会引入指定的文件，并确保文件只被引入一次，避免重复引入导致的函数重定义错误。如果文件不存在会报错并停止执行。例如，创建一个 `utils.php` 文件：



```
<?php
function add($a, $b) {
	return $a + $b;
}

function greet($name) {
	return "Hello, " . $name . "!";
}
```

在其他 PHP 文件中引入并使用：



```
<?php
require_once "utils.php";

$message = greet("Alice");
echo $message; // 输出 "Hello, Alice!"

$sum = add(3, 5);
echo $sum; // 输出 8
```

即使多次调用 `require_once`，文件也只会被引入一次：



```
<?php
require_once "utils.php";
require_once "utils.php"; // 不会重复引入
```

请注意

与读写文件类似，如果被引入的文件位于某个文件夹中，需要使用完整的路径。例如，如果 `utils.php` 在 `lib` 文件夹中，应该这样引入：



```
<?php
require_once "lib/utils.php";
```

### 其他引入方式

- `require`：不检查文件是否已被引入，可能重复引入导致报错。
- `include` 和 `include_once`：如果文件不存在，程序会忽略并继续执行。

出于安全原因，云函数一次运行最多只能调用 10 次引入操作。

## 与标准的差异

云函数运行在热铁盒自研的 Serverless 环境中，和传统的 PHP 运行环境有一些差异。目前暂不支持运行需要安装的 PHP 程序，例如 WordPress、Discuz! 等。