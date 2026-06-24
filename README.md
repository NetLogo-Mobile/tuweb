# Turtle Universe Web
海龟实验室（Turtle Universe）的网页版本，使用 PHP 构建。

## 在线测试
您可以直接访问已部署的在线版本进行体验：
[https://tuweb.esimple.top/](https://tuweb.esimple.top/)
线上版本通常版本更高，支持更多新功能。

## 本地运行详细指南
请按顺序完成以下步骤，即可在您的电脑上成功运行本项目。

### 第一步：检查并安装 PHP
1.  **打开终端**（Windows：CMD 或 PowerShell；Mac/Linux：终端）。
2.  **输入以下命令检查是否已安装 PHP 及版本**：
    `php -v`
3.  **查看结果**：
    *   如果显示了类似 `PHP 7.4.x` 或 `PHP 8.x.x` 的版本信息，说明已安装，请跳至第二步。
    *   如果显示“找不到命令”或版本低于 7.4，则需要安装。
4.  **安装 PHP**：
    *   **Windows**：访问 [php.net/downloads](https://www.php.net/downloads) 下载“Non Thread Safe”版本的 ZIP 包，解压后，将 `php.exe` 所在文件夹路径（如 `C:\php\`）添加到系统的**环境变量 PATH** 中。
    *   **Mac**：可使用 Homebrew 安装：`brew install php@8.1`
    *   **Linux (Debian/Ubuntu)**：使用 apt：`sudo apt update && sudo apt install php`

### 第二步：获取项目文件
您可以选择以下任一方式：
*   **方式一（推荐，使用 Git）**：在您想存放项目的文件夹中打开终端，运行：
    `git clone NetLogo-Mobile/tuweb`
*   **方式二（直接下载）**：从项目仓库页面直接下载 ZIP 压缩包，然后在本地解压。

### 第三步：启动本地 PHP 服务器
1.  在终端中，**进入**您刚下载的项目根目录。例如：
    `cd /path/to/your/turtle-universe-web`
    *小技巧：在文件夹空白处按住 Shift 键并点击鼠标右键，可选择“在此处打开终端/命令窗口”。*
2.  在项目根目录下，**执行启动命令**：
    `php -S localhost:8080`
3.  如果成功，终端将显示类似以下信息：
    `PHP 7.4.3 Development Server started at http://localhost:8080`

### 第四步：在浏览器中访问
1.  打开您的浏览器（如 Chrome, Firefox, Edge）。
2.  在地址栏输入终端中显示的地址：
    `http://localhost:8080`
3.  按下回车，您应该就能看到和在线测试网站一样的 Turtle Universe Web 界面了。

### 第五步：停止服务器
当您想停止本地服务器时，只需回到启动它的那个终端窗口，按下键盘快捷键：
`Ctrl + C`

---

## 常见问题排查
*   **访问 `http://localhost:8080` 显示“无法连接”或白屏**：
    *   请确认第三步的服务器是否成功启动（终端有无错误信息）。
    *   请确认浏览器地址栏输入的是 `http://localhost:8080`，而不是 `https`。
*   **页面显示 PHP 代码或出现下载提示**：
    *   说明 PHP 没有正常运行，请返回第一步仔细检查 PHP 安装和环境变量。
*   **页面样式错乱或功能不全**：
    *   可能是资源加载路径问题，请确保通过 `http://localhost:8080` 访问，且服务器是从项目**根目录**启动的。

按照以上步骤操作，您应该可以顺利在本地运行项目。如果遇到其他问题，请提供终端或浏览器显示的具体错误信息，并提交至issue。
