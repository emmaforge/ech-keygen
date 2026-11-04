<?php

namespace Plugin\EchKeygen\Controllers;

use App\Services\Plugin\PluginConfigService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EchController extends Controller
{
    private const PLUGIN_CODE = 'ech_keygen';
    private const SESSION_FLAG = 'ech_keygen_authed';
    private const SESSION_KEY_HASH = 'ech_keygen_key_hash';

    /**
     * 生成一对 ECH 密钥。算法与面板自带 ManageController::generateEchKey 完全一致，
     * 唯一区别是 public_name 由调用方指定（面板自带的按钮固定用 ech.example.com）。
     *
     * @return array{key: string, config: string}
     */
    private function generatePair(string $publicName): array
    {
        // Generate X25519 key pair
        $privateKey = random_bytes(32);
        $publicKey = sodium_crypto_scalarmult_base($privateKey);

        $configId = random_int(0, 255);

        // Build ECHConfigContents (draft-ietf-tls-esni-18)
        $contents = '';
        $contents .= pack('C', $configId);                // config_id
        $contents .= pack('n', 0x0020);                   // kem_id: DHKEM(X25519)
        $contents .= pack('n', 32) . $publicKey;          // public_key (length-prefixed)
        // cipher_suites: 2 suites × 4 bytes = 8 bytes
        $contents .= pack('n', 8);                        // cipher_suites byte length
        $contents .= pack('nn', 0x0001, 0x0001);          // HKDF-SHA256 + AES-128-GCM
        $contents .= pack('nn', 0x0001, 0x0003);          // HKDF-SHA256 + ChaCha20Poly1305
        $contents .= pack('C', 0);                        // max_name_length
        $contents .= pack('C', strlen($publicName)) . $publicName;
        $contents .= pack('n', 0);                        // extensions: empty

        // ECHConfig = version(2) + length(2) + contents
        $echConfig = pack('n', 0xfe0d) . pack('n', strlen($contents)) . $contents;

        // ECHConfigList = total_length(2) + configs
        $echConfigList = pack('n', strlen($echConfig)) . $echConfig;

        // ECH Keys = private_key_len(2) + key(32) + config_len(2) + config
        $echKeysPayload = pack('n', 32) . $privateKey . pack('n', strlen($echConfig)) . $echConfig;

        $keyPem = "-----BEGIN ECH KEYS-----\n"
            . chunk_split(base64_encode($echKeysPayload), 64, "\n")
            . "-----END ECH KEYS-----";

        $configPem = "-----BEGIN ECH CONFIGS-----\n"
            . chunk_split(base64_encode($echConfigList), 64, "\n")
            . "-----END ECH CONFIGS-----";

        return ['key' => $keyPem, 'config' => $configPem];
    }

    /** 校验 public_name，合法返回 null，否则返回错误信息。 */
    private function validatePublicName(string $publicName): ?string
    {
        if (strlen($publicName) < 1 || strlen($publicName) > 253) {
            return 'public_name 长度必须在 1-253 字节之间';
        }
        $label = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';
        if (!preg_match('/^' . $label . '(\.' . $label . ')*$/', $publicName)) {
            return 'public_name 不是合法的域名';
        }
        return null;
    }

    /** 读取插件设置里的页面访问密码（未设置时返回空字符串）。 */
    private function accessKey(): string
    {
        $config = app(PluginConfigService::class)->getConfig(self::PLUGIN_CODE);
        return (string)($config['access_key']['value'] ?? '');
    }

    private function isAuthed(Request $request): bool
    {
        $accessKey = $this->accessKey();
        if ($accessKey === '') {
            return false;
        }
        if ($request->session()->get(self::SESSION_FLAG) !== true) {
            return false;
        }
        return hash_equals(
            (string)$request->session()->get(self::SESSION_KEY_HASH, ''),
            hash('sha256', $accessKey)
        );
    }

    private function render(Request $request, array $extra = [])
    {
        return view('EchKeygen::generate', array_merge([
            'configured' => $this->accessKey() !== '',
            'authed' => $this->isAuthed($request),
            'publicName' => '',
            'result' => null,
            'error' => null,
        ], $extra));
    }

    public function page(Request $request)
    {
        return $this->render($request);
    }

    public function submit(Request $request)
    {
        // 表单带 access_key 字段时是解锁动作
        if ($request->has('access_key')) {
            $accessKey = $this->accessKey();
            if ($accessKey !== '' && hash_equals($accessKey, (string)$request->input('access_key'))) {
                $request->session()->put(self::SESSION_FLAG, true);
                $request->session()->put(self::SESSION_KEY_HASH, hash('sha256', $accessKey));
                return $this->render($request);
            }
            return $this->render($request, ['error' => '访问密码不正确']);
        }

        if (!$this->isAuthed($request)) {
            return $this->render($request, ['error' => '请先输入访问密码']);
        }

        $publicName = trim((string)$request->input('public_name', ''));
        if ($error = $this->validatePublicName($publicName)) {
            return $this->render($request, ['error' => $error, 'publicName' => $publicName]);
        }

        return $this->render($request, [
            'publicName' => $publicName,
            'result' => $this->generatePair($publicName),
        ]);
    }

    /**
     * 管理员接口：POST /plugin/ech-keygen/generate
     * 参数 public_name，返回 JSON {"key": "...", "config": "..."}（该路由挂 admin 鉴权）。
     */
    public function generateApi(Request $request)
    {
        $publicName = trim((string)$request->input('public_name', ''));
        if ($error = $this->validatePublicName($publicName)) {
            return response()->json(['message' => $error], 422);
        }
        return response()->json($this->generatePair($publicName));
    }
}
