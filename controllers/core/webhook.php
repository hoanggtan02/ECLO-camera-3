<?php
require __DIR__ . '/../../vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Medoo\Medoo;
use Predis\Client as RedisClient;

// Tải cấu hình từ .env
$envPath = __DIR__ . '/../../.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    die(json_encode(['error' => 'FATAL ERROR: File .env không được tìm thấy.']));
}
$env = parse_ini_file($envPath);
$publicBaseUrl = rtrim($env['APP_URL'] ?? 'http://localhost', '/');
$facesUploadPath = __DIR__ . '/../../public/uploads/faces/';
$photosUploadPath = __DIR__ . '/../../public/uploads/photos/';
$webhookSecret = $env['WEBHOOK_SECRET'] ?? 'your-secret-key'; // Thêm secret key từ .env

// Kiểm tra và tạo thư mục
foreach ([$facesUploadPath, $photosUploadPath] as $path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true)) {
            http_response_code(500);
            die(json_encode(['error' => "FATAL ERROR: Không thể tạo thư mục {$path}."]));
        }
        error_log("✅ [INIT] Đã tạo thư mục: $path");
    }
    if (!is_writable($path)) {
        http_response_code(500);
        die(json_encode(['error' => "FATAL ERROR: Thư mục {$path} không có quyền ghi."]));
    }
}

// Kết nối Database
try {
    $database = new Medoo([
        'database_type' => $env['DB_CONNECTION'] ?? 'mysql',
        'database_name' => $env['DB_DATABASE'] ?? 'eclo-camera',
        'server'        => $env['DB_HOST'] ?? 'localhost',
        'username'      => $env['DB_USERNAME'] ?? 'root',
        'password'      => $env['DB_PASSWORD'] ?? '',
        'charset'       => $env['DB_CHARSET'] ?? 'utf8mb4',
        'error'         => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => "FATAL ERROR: Không thể kết nối đến database: " . $e->getMessage()]));
}

// Kết nối Redis
try {
    $redis = new RedisClient([
        'scheme' => 'tcp',
        'host'   => $env['REDIS_HOST'] ?? '127.0.0.1',
        'port'   => (int)($env['REDIS_PORT'] ?? 6379),
    ]);
    $redis->ping();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => "FATAL ERROR: Không thể kết nối đến Redis: " . $e->getMessage()]));
}

/**
 * Gửi một tin nhắn đến MQTT Broker.
 */
function publishMqttMessage(array $env, string $topic, array $payload): bool
{
    $mqttServer = $env['MQTT_HOST'] ?? 'mqtt.ellm.io';
    $mqttPort = (int)($env['MQTT_PORT_TCP'] ?? 1883);
    $mqttUsername = $env['MQTT_USERNAME'] ?? 'eclo';
    $mqttPassword = $env['MQTT_PASSWORD'] ?? 'Eclo@123';
    $mqttClientId = 'backend-publisher-' . uniqid();

    try {
        $mqtt = new MqttClient($mqttServer, $mqttPort, $mqttClientId);
        $connectionSettings = (new ConnectionSettings)
            ->setUsername($mqttUsername)
            ->setPassword($mqttPassword)
            ->setConnectTimeout(5);
        $mqtt->connect($connectionSettings, true);
        $mqtt->publish($topic, json_encode($payload, JSON_UNESCAPED_UNICODE), 0);
        $mqtt->disconnect();
        error_log("✅ MQTT Publish Success to topic [{$topic}]");
        return true;
    } catch (Exception $e) {
        error_log("❌ MQTT Publish Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Lưu hình ảnh từ chuỗi base64 và trả về mảng đường dẫn.
 */
function save_image_from_base64(?string $picBase64, string $facesUploadPath, string $photosUploadPath, string $prefix, string $uniqueId): ?array
{
    if (empty($picBase64)) {
        error_log("⚠️ [save_image] Chuỗi base64 rỗng.");
        return null;
    }

    error_log("📷 [save_image] Chuỗi base64 đầu vào (50 ký tự đầu): " . substr($picBase64, 0, 50));

    if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $picBase64, $matches)) {
        error_log("⚠️ [save_image] Chuỗi base64 không đúng định dạng (không phải jpeg/jpg/png).");
        return null;
    }

    if (!is_writable($facesUploadPath) || !is_writable($photosUploadPath)) {
        error_log("⚠️ [save_image] Một hoặc cả hai thư mục không thể ghi: $facesUploadPath, $photosUploadPath");
        return null;
    }

    try {
        list(, $data) = explode(',', $picBase64);
        $imageData = base64_decode($data);
        if ($imageData === false) {
            error_log("⚠️ [save_image] Lỗi giải mã base64: Chuỗi không hợp lệ.");
            return null;
        }

        $imageExtension = ($matches[1] === 'jpeg') ? 'jpg' : $matches[1];
        $imageName = $prefix . str_replace('.', '_', $uniqueId) . '_' . time() . '.' . $imageExtension;
        
        $facesFilePath = rtrim($facesUploadPath, '/') . '/' . $imageName;
        $photosFilePath = rtrim($photosUploadPath, '/') . '/' . $imageName;

        error_log("📁 [save_image] Đang lưu ảnh vào: $facesFilePath");

        if (file_put_contents($facesFilePath, $imageData) === false) {
            error_log("⚠️ [save_image] Không thể ghi file vào: $facesFilePath. Lỗi: " . error_get_last()['message']);
            return null;
        }
        if (!file_exists($facesFilePath)) {
            error_log("⚠️ [save_image] File không tồn tại sau khi ghi: $facesFilePath");
            return null;
        }
        chmod($facesFilePath, 0644);

        error_log("📁 [save_image] Đang sao chép ảnh sang: $photosFilePath");

        if (!copy($facesFilePath, $photosFilePath)) {
            error_log("⚠️ [save_image] Không thể sao chép file sang: $photosFilePath. Lỗi: " . error_get_last()['message']);
            unlink($facesFilePath);
            return null;
        }
        if (!file_exists($photosFilePath)) {
            error_log("⚠️ [save_image] File không tồn tại sau khi sao chép: $photosFilePath");
            unlink($facesFilePath);
            return null;
        }
        chmod($photosFilePath, 0644);
        
        error_log("✅ [save_image] Đã lưu và sao chép ảnh thành công: $imageName");

        return [
            'faces_path' => 'uploads/faces/' . $imageName,
            'photos_path' => 'uploads/photos/' . $imageName
        ];
    } catch (Exception $e) {
        error_log("⚠️ [save_image] Lỗi ngoại lệ khi xử lý ảnh: " . $e->getMessage());
        return null;
    }
}

// Cấu hình HTTP response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST');

// Xác thực webhook
if (!isset($_SERVER['HTTP_X_WEBHOOK_SECRET']) || $_SERVER['HTTP_X_WEBHOOK_SECRET'] !== $webhookSecret) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Nhận dữ liệu từ webhook
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!$payload || !isset($payload['info']) || !isset($payload['topic'])) {
    error_log("❌ [WEBHOOK] Payload không hợp lệ hoặc thiếu info/topic: " . $input);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$info = $payload['info'];
$topic = $payload['topic'];
$eventType = basename($topic);
error_log("📸 [WEBHOOK] Payload cho topic [$topic]: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

// Xử lý logic dựa trên event type
if ($eventType === 'Rec') {
    $imagePaths = save_image_from_base64($info['pic'] ?? null, $facesUploadPath, $photosUploadPath, 'rec_', $info['personId'] ?? uniqid());

    $recData = [
        'event_type'  => 'Rec',
        'person_name' => $info['personName'] ?? ($info['persionName'] ?? 'N/A'),
        'person_id'   => $info['personId'] ?? 'N/A',
        'similarity'  => (float)($info['similarity1'] ?? 0),
        'record_id'   => (int)($info['RecordID'] ?? 0),
        'event_time'  => $info['time'] ?? date('Y-m-d H:i:s'),
        'image_path'  => $imagePaths['faces_path'] ?? null,
    ];
    $database->insert('mqtt_messages', $recData);
    error_log("✅ [WEBHOOK] Đã ghi nhận sự kiện Rec cho: " . $recData['person_name']);
    echo json_encode(['status' => 'success', 'event' => 'Rec']);
}

elseif ($eventType === 'Snap') {
    $picBase64 = $info['pic'] ?? null;

    $snapData = [
        'event_type'  => 'Snap',
        'person_name' => 'Người lạ',
        'event_time'  => $info['time'] ?? date('Y-m-d H:i:s'),
        'image_path'  => null,
    ];

    if (!$picBase64) {
        error_log("❌ [WEBHOOK] Payload thiếu pic base64.");
        $database->insert('mqtt_messages', $snapData);
        echo json_encode(['status' => 'success', 'event' => 'Snap', 'message' => 'No image']);
        exit;
    }

    $imageHash = md5($picBase64);
    $redisKey = 'snap_cooldown:' . $imageHash;
    $lockKey = 'snap_lock:' . $imageHash;
    $lockAcquired = $redis->set($lockKey, 1, 'NX', 'EX', 10);

    if (!$lockAcquired) {
        error_log("ℹ️ [WEBHOOK] Bỏ qua vì tin nhắn đang được xử lý bởi luồng khác.");
        $database->insert('mqtt_messages', $snapData);
        echo json_encode(['status' => 'success', 'event' => 'Snap', 'message' => 'Locked']);
        exit;
    }

    try {
        if ($redis->exists($redisKey)) {
            error_log("ℹ️ [WEBHOOK] Bỏ qua vì gương mặt này đã được xử lý gần đây.");
            $database->insert('mqtt_messages', $snapData);
            echo json_encode(['status' => 'success', 'event' => 'Snap', 'message' => 'Cooldown']);
            exit;
        }

        $newSn = uniqid('NV_');
        $imagePaths = save_image_from_base64($picBase64, $facesUploadPath, $photosUploadPath, 'snap_', $newSn);

        if ($imagePaths === null) {
            error_log("⚠️ [WEBHOOK] Không thể lưu ảnh. Dừng quá trình tự động đăng ký.");
            $database->insert('mqtt_messages', $snapData);
            echo json_encode(['status' => 'success', 'event' => 'Snap', 'message' => 'Image save failed']);
            exit;
        }

        $snapData['person_name'] = 'Người lạ (Auto-Reg)';
        $snapData['image_path'] = $imagePaths['faces_path'];
        $database->insert('mqtt_messages', $snapData);

        $newPersonName = 'Người lạ ' . date('d/m H:i');
        try {
            $database->insert("employee", [
                'sn' => $newSn,
                'person_name' => $newPersonName,
                'registration_photo' => $imagePaths['photos_path'],
            ]);

            $publicImageUrl = $publicBaseUrl . '/' . $imagePaths['photos_path'];
            $mqttPayload = [
                "messageId" => uniqid(),
                "operator" => "EditPerson",
                "info" => [ "customId" => $newSn, "name" => $newPersonName, "personType" => 0, "picURI" => $publicImageUrl ]
            ];
            publishMqttMessage($env, 'mqtt/face/1018656', $mqttPayload);

            $redis->setex($redisKey, 300, 1);
            echo json_encode(['status' => 'success', 'event' => 'Snap', 'message' => 'Processed']);
        } catch (Exception $e) {
            if (file_exists($facesUploadPath . basename($imagePaths['faces_path']))) {
                unlink($facesUploadPath . basename($imagePaths['faces_path']));
            }
            if (file_exists($photosUploadPath . basename($imagePaths['photos_path']))) {
                unlink($photosUploadPath . basename($imagePaths['photos_path']));
            }
            error_log("❌ [WEBHOOK] Lỗi DB khi tự động thêm nhân viên: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'event' => 'Snap', 'message' => 'DB error']);
        }
    } finally {
        $redis->del($lockKey);
    }
} else {
    error_log("❌ [WEBHOOK] Event type không được hỗ trợ: $eventType");
    echo json_encode(['status' => 'error', 'message' => 'Unsupported event type']);
}