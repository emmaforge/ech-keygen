# ECH 密钥生成器（Xboard 插件）

给 Xboard 面板用的插件：生成 ECH 密钥对时可以**指定 public_name**。

面板节点设置里自带的「自动生成 ECH 密钥对」按钮，后端接口其实支持 `public_name`
参数，但按钮没有传，永远生成默认的 `ech.example.com`。这个插件就是补上这块：
在面板里打开一个小页面，填域名 → 生成 → 把两段 PEM 贴回节点设置。

生成算法与面板自带接口（`ManageController::generateEchKey`）逐字节一致：
draft-ietf-tls-esni-18、X25519（sodium）、cipher suites (1,1)/(1,3)、
config_id 随机。生成的密钥对可直接用于面板的「ECH 配置 (PEM)」和「ECH Key」。

## 安装

1. 把 `EchKeygen` 整个文件夹复制到面板根目录的 `plugins/` 下
   （最终路径形如 `plugins/EchKeygen/config.json`）；
   如果后台插件管理只提供上传安装，就把 `EchKeygen` 文件夹打包成 zip 再上传。
2. 管理后台 → 插件管理：找到「ECH 密钥生成器」，安装并**启用**。
3. 在该插件的设置里填写「页面访问密码」（自己定一个，打开生成页面时要用；
   留空则页面禁用）。

## 使用

1. 浏览器打开 `https://你的面板域名/ech-keygen`，输入访问密码；
2. 填 public_name（一般填这个节点的外层 TLS SNI / 伪装域名），点「生成密钥对」；
3. 把「ECH 配置 (PEM)」贴到节点设置的 ECH 配置框，「ECH Key」贴到 ECH Key 框；
4. 保存节点，客户端更新一次订阅。

注意：ECH Key 含私钥，只贴进面板，不要外发；换 public_name 要配置和 Key 成对换。

## 管理员接口（可选）

除了页面，插件还注册了一个接口，方便脚本化调用：

```
POST /plugin/ech-keygen/generate
参数：public_name
鉴权：面板管理员令牌（Authorization: Bearer <管理员 token>，走面板的 admin 中间件）
返回：{"key": "-----BEGIN ECH KEYS-----...", "config": "-----BEGIN ECH CONFIGS-----..."}
```

## 安全说明

- 生成接口返回的内容含私钥，所以页面用插件设置里的访问密码把关
  （密码变更后已登录的会话自动失效），接口走管理员鉴权；
- 插件不在服务器上保存任何生成的密钥，生成结果只出现在你自己的浏览器里。
