# ech-keygen

为 Xboard 面板生成 ECH（Encrypted Client Hello）密钥对，支持自定义 `public_name`。

## 为什么需要这个脚本

面板自带的“自动生成 ECH 密钥对”只能生成默认 `public_name`（`ech.example.com`），改不了。
这个脚本用同一套算法（draft-ietf-tls-esni-18，X25519）生成密钥对，`public_name` 由你指定，
比如和节点的外层 TLS SNI 填成同一个域名，让各层握手看起来是同一个站。

## 特点

- 只需要 Python3 标准库，不用装任何包，任何主流 Linux 服务器都能直接跑；
- 私钥只落在你指定的本地目录里，不经过任何第三方；
- 输出格式和面板的「ECH 配置 (PEM)」、「ECH Key」两个字段直接对应；
- 生成的 `ech-keys.pem` 权限自动设为 `0600`。

## 用法

下载脚本（单个文件）：

```bash
curl -LO https://raw.githubusercontent.com/emmaforge/ech-keygen/main/ech-keygen.py
```

生成一对密钥，`public_name` 填你想要的域名：

```bash
python3 ech-keygen.py example.com
```

默认保存到 `/etc/xboard-node/ech/`（往 `/etc` 下写需要 root 权限，目录不存在会自动创建）。
想换目录，第二个参数直接指定：

```bash
python3 ech-keygen.py example.com /root/ech
```

不传 `public_name` 时默认生成 `ech.example.com`。

## 生成的文件

| 文件 | 用途 |
| --- | --- |
| `ech-keys.pem` | 贴到面板「ECH Key」，含私钥，仅服务器持有，勿外发 |
| `ech-configs.pem` | 贴到面板「ECH 配置 (PEM)」 |
| `ech-configs.b64` | 同一份配置的单行 base64，备用 |

## 上线步骤

1. 把 `ech-configs.pem` 的内容贴到面板「ECH 配置 (PEM)」；
2. 把同一对生成的 `ech-keys.pem` 的内容贴到面板「ECH Key」，配置和 Key 必须成对，不能新配置配旧 Key；
3. 面板保存后，后端下次同步（一般一分钟左右）会自动重载内核应用新 ECH，不用手动重启服务；
4. 客户端更新一次订阅，拿到新的 ECH 配置再连接。

## 注意事项

- `ech-keys.pem` 里就是私钥，不要公开、不要发给他人、不要传到公开仓库；
- 换 `public_name` 要成对换配置和 Key，换完客户端必须更新订阅，旧配置握手会对不上；
- 面板里的「ECH 查询域名」和生成无关：它只影响客户端在 DNS 模式下向哪个域名查 HTTPS 记录，
  配置已填的情况下客户端不走 DNS 查询，那个字段留空就行。
