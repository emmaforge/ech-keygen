#!/usr/bin/env python3
"""生成 ECH 密钥对（与 Xboard 面板 generateEchKey 同一套算法，draft-ietf-tls-esni-18）。

用法：
    python3 ech-keygen.py <public_name> [输出目录]

    public_name  缺省为 ech.example.com
    输出目录     缺省为 /etc/xboard-node/ech（往 /etc 下写需要 root 权限）

生成的文件（都在输出目录里）：
    ech-keys.pem    -> 贴到面板「ECH Key」        （含私钥，仅服务器持有，勿外发）
    ech-configs.pem -> 贴到面板「ECH 配置 (PEM)」
    ech-configs.b64 -> 单行 base64 的配置列表，供只需要配置列表的客户端使用

依赖：仅 Python3 标准库，不用装任何包。
私钥只落在输出目录里，不会通过网络发往任何地方。
"""

import base64
import os
import secrets
import sys

# --- X25519（RFC 7748，纯标准库实现） ---
P = 2 ** 255 - 19
A24 = 121665


def x25519(k_bytes: bytes, u_bytes: bytes) -> bytes:
    k = int.from_bytes(k_bytes, "little")
    k &= (1 << 254) - 8
    k |= 1 << 254
    x1 = int.from_bytes(u_bytes, "little") & ((1 << 255) - 1)
    x2, z2, x3, z3 = 1, 0, x1, 1
    swap = 0
    for t in range(254, -1, -1):
        kt = (k >> t) & 1
        swap ^= kt
        if swap:
            x2, x3 = x3, x2
            z2, z3 = z3, z2
        swap = kt
        a = (x2 + z2) % P
        aa = a * a % P
        b = (x2 - z2) % P
        bb = b * b % P
        e = (aa - bb) % P
        c = (x3 + z3) % P
        d = (x3 - z3) % P
        da = d * a % P
        cb = c * b % P
        x3 = (da + cb) ** 2 % P
        z3 = x1 * (da - cb) ** 2 % P
        x2 = aa * bb % P
        z2 = e * (aa + A24 * e) % P
    if swap:
        x2, x3 = x3, x2
        z2, z3 = z3, z2
    return (x2 * pow(z2, P - 2, P) % P).to_bytes(32, "little")


def pem(kind: str, payload: bytes) -> str:
    b64 = base64.b64encode(payload).decode()
    body = "\n".join(b64[i:i + 64] for i in range(0, len(b64), 64))
    return f"-----BEGIN {kind}-----\n{body}\n-----END {kind}-----\n"


def main() -> int:
    public_name = sys.argv[1] if len(sys.argv) > 1 else "ech.example.com"
    out_dir = sys.argv[2] if len(sys.argv) > 2 else "/etc/xboard-node/ech"

    if not (1 <= len(public_name.encode()) <= 253) or any(
        ch.isspace() for ch in public_name
    ):
        print("public_name 长度必须在 1-253 字节之间，且不能含空白字符", file=sys.stderr)
        return 1

    private_key = secrets.token_bytes(32)
    public_key = x25519(private_key, (9).to_bytes(32, "little"))
    config_id = secrets.randbelow(256)

    # ECHConfigContents（与面板 generateEchKey 完全一致）
    contents = b""
    contents += bytes([config_id])                    # config_id
    contents += (0x0020).to_bytes(2, "big")            # kem_id: DHKEM(X25519)
    contents += len(public_key).to_bytes(2, "big") + public_key
    contents += (8).to_bytes(2, "big")                 # cipher_suites 字节长
    contents += (0x0001).to_bytes(2, "big") + (0x0001).to_bytes(2, "big")
    contents += (0x0001).to_bytes(2, "big") + (0x0003).to_bytes(2, "big")
    contents += bytes([0])                             # maximum_name_length
    contents += bytes([len(public_name.encode())]) + public_name.encode()
    contents += (0).to_bytes(2, "big")                 # extensions: 空

    ech_config = (0xFE0D).to_bytes(2, "big") + len(contents).to_bytes(2, "big") + contents
    ech_config_list = len(ech_config).to_bytes(2, "big") + ech_config

    # ECH KEYS payload：私钥长 + 私钥 + 配置长 + 配置
    keys_payload = (
        len(private_key).to_bytes(2, "big")
        + private_key
        + len(ech_config).to_bytes(2, "big")
        + ech_config
    )

    os.makedirs(out_dir, exist_ok=True)
    keys_path = os.path.join(out_dir, "ech-keys.pem")
    configs_path = os.path.join(out_dir, "ech-configs.pem")
    b64_path = os.path.join(out_dir, "ech-configs.b64")

    with open(keys_path, "w") as f:
        f.write(pem("ECH KEYS", keys_payload))
    os.chmod(keys_path, 0o600)
    with open(configs_path, "w") as f:
        f.write(pem("ECH CONFIGS", ech_config_list))
    with open(b64_path, "w") as f:
        f.write(base64.b64encode(ech_config_list).decode() + "\n")

    print(f"public_name: {public_name}")
    print(f"config_id:   {config_id}")
    print(f"ECH Key:     {keys_path}（含私钥，仅服务器持有，勿外发）")
    print(f"ECH 配置:    {configs_path}")
    print(f"单行 base64: {b64_path}")
    print("下一步：把 ech-configs.pem 贴到面板「ECH 配置 (PEM)」，"
          "ech-keys.pem 贴到「ECH Key」，保存后客户端更新订阅。")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
