<?php

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

if (!defined('ECLO'))
    die("Hacking attempt");

$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');


// ROUTE GET /employee (Hiển thị trang chính)
$app->router("/employee", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang('Quản lý nhân viên');
    echo $app->render('templates/camera/employee.html', $vars);
})->setPermissions(['employee']);


// ROUTE POST /employee (Xử lý DataTables)
$app->router("/employee", 'POST', function ($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $draw = intval($_POST['draw'] ?? 0);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    $validColumns = ["id", "sn", "registration_photo", "person_name", "telephone", "gender", "birthday", "creation_time", "action"];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "id";

    $where = ["LIMIT" => [$start, $length], "ORDER" => [$orderColumn => $orderDir]];
    if (!empty($searchValue)) {
        $where["OR"] = ["sn[~]" => $searchValue, "person_name[~]" => $searchValue, "telephone[~]" => $searchValue];
    }

    $countWhere = $where;
    unset($countWhere['LIMIT'], $countWhere['ORDER']);
    $totalRecords = $app->count("employee");
    $filteredRecords = $app->count("employee", $countWhere);
    
    $datas = $app->select("employee", "*", $where) ?? [];

    $formattedData = array_map(function($data) use ($app, $jatbi) {
        $genderLabels = ["1" => $jatbi->lang("Nam"), "2" => $jatbi->lang("Nữ"), "3" => $jatbi->lang("Khác")];
        
        $imageSrc = '';
        $imagePathFromDb = (string) ($data['registration_photo'] ?? '');
        if (!empty($imagePathFromDb)) {
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            if ($basePath === '/' || $basePath === '\\') $basePath = '';
            $imageSrc = $basePath . '/public/' . ltrim($imagePathFromDb, '/');
        }

        $photoHtml = !empty($imageSrc) 
            ? '<img src="' . htmlspecialchars($imageSrc) . '" alt="Photo" class="" style="width: 60px; height: auto;">' 
            : $jatbi->lang('Chưa có ảnh');
        
       

        return [
            "checkbox" => $app->component("box", ["data" => $data['id']]),
            "sn" => htmlspecialchars($data['sn'] ?? ''),
            "registration_photo" => $photoHtml,
            "person_name" => htmlspecialchars($data['person_name'] ?? ''),
            "telephone" => htmlspecialchars($data['telephone'] ?? ''),
            "gender" => $genderLabels[$data['gender'] ?? ''] ?? $jatbi->lang("Không xác định"),
            "birthday" => ($data['birthday'] ?? null) ? date('d/m/Y', strtotime($data['birthday'])) : 'N/A',
            "creation_time" => ($data['creation_time'] ?? null) ? date('H:i:s d/m/Y', strtotime($data['creation_time'])) : 'N/A',
             "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['employee'],
                        'action' => [
                            'data-url' => '/employee-edit?id=' . $data['id'],
                            'data-action' => 'modal'
                        ]
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['employee'],
                        'action' => [
                            'data-url' => '/employee-delete?id=' . $data['id'],
                            'data-action' => 'modal'
                        ]
                    ]
                ]
            ])
        ];
    }, $datas);

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $filteredRecords,
        "data" => $formattedData
    ]);
})->setPermissions(['employee']);







// ROUTE GET /employee-add 
$app->router("/employee-add", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang('Thêm nhân viên');
    $vars['data'] = []; // Khởi tạo mảng data rỗng cho form thêm mới
    echo $app->render('templates/camera/employee-post.html', $vars, 'global');
})->setPermissions(['employee']);


// ROUTE POST /employee-add 
$app->router("/employee-add", 'POST', function ($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $uploadFilePath = null;

    try {

        $person_name = trim($_POST['person_name'] ?? '');
        if (empty($person_name)) {
            throw new Exception($jatbi->lang('Vui lòng nhập họ và tên nhân viên.'));
        }

        if (!isset($_FILES['registration_photo']) || $_FILES['registration_photo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($jatbi->lang('Vui lòng chọn ảnh đăng ký.'));
        }


        $envPath = __DIR__ . '/../../.env';
        $env = file_exists($envPath) ? parse_ini_file($envPath) : [];
        $publicBaseUrl = rtrim($env['APP_URL'] ?? '', '/');

        $uploadDir = __DIR__ . '/../../public/uploads/photos/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $fileExtension = pathinfo($_FILES['registration_photo']['name'], PATHINFO_EXTENSION);
        $sn = $_POST['sn'] ?? 'NV' . time();
        $newFileName = $sn . '_' . uniqid() . '.' . strtolower($fileExtension);
        $uploadFilePath = $uploadDir . $newFileName; 
        $dbImagePath = 'uploads/photos/' . $newFileName; 

        if (!move_uploaded_file($_FILES['registration_photo']['tmp_name'], $uploadFilePath)) {
            throw new Exception($jatbi->lang('Lỗi khi tải ảnh lên.'));
        }


        $dataToInsert = [
            "sn" => $sn,
            "person_name" => $person_name,
            "registration_photo" => $dbImagePath,
            "telephone" => $_POST['telephone'] ?? null,
            "gender" => $_POST['gender'] ?? null,
            "birthday" => empty($_POST['birthday']) ? null : $_POST['birthday'],
            "id_card" => $_POST['id_card'] ?? null,
            "address" => $_POST['address'] ?? null,
        ];

        $insertResult = $app->insert("employee", $dataToInsert);
        if (!$insertResult || $insertResult->rowCount() === 0) {
            throw new Exception($jatbi->lang('Lỗi database khi thêm nhân viên.'));
        }
        $newEmployeeId = $app->id();


        $image_data_for_mqtt = file_get_contents($uploadFilePath);
        $pic_base64_data = base64_encode($image_data_for_mqtt);

        $payload = [
            "messageId" => "ID:localhost-" . round(microtime(true) * 1000),
            "operator" => "EditPerson",
            "info" => [
                "personId" => (string) $newEmployeeId,
                "customId" => $sn,
                "name" => $person_name,
                "gender" => (int) ($dataToInsert['gender'] ?? 0),
                "birthday" => $dataToInsert['birthday'] ?? "",
                "address" => $dataToInsert['address'] ?? "",
                "idCard" => $dataToInsert['id_card'] ?? "",
                "telnum1" => $dataToInsert['telephone'] ?? "",
                "pic" => "data:image/jpeg;base64," . $pic_base64_data,
            ]
        ];

        // --- Cấu hình và thực thi cURL ---
        $apiUrl = 'https://mqtt.ellm.io/api/v5/publish';
        $apiKey = 'b894dd847286b6b5';
        $apiSecret = '9BkMrs1ttYhQ3b9CiXyFLD6GuQb8w9AUVl7jRtTb8SzbyF';

        $topic = 'mqtt/face/1018656';

        $dataToPost = ['topic' => $topic, 'payload' => json_encode($payload), 'qos' => 0, 'retain' => false];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToPost));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode("$apiKey:$apiSecret")]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch))
            throw new Exception('cURL error: ' . curl_error($ch));
        curl_close($ch);
        if ($httpCode !== 200 && $httpCode !== 204) {
            throw new Exception("HTTP API request failed with status code $httpCode. Response: $response");
        }


        echo json_encode(["status" => "success", "content" => $jatbi->lang("Thêm nhân viên thành công"), 'load' => '/employee']);

    } catch (Throwable $e) {

        if ($uploadFilePath && file_exists($uploadFilePath)) {
            unlink($uploadFilePath);
        }

        // Bắt lỗi và trả về
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "content" => $e->getMessage(),
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]);
    }
})->setPermissions(['employee']);





$app->router("/employee-edit", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    $id = $_GET['id'] ?? null;
    if (empty($id) || !is_numeric($id)) {

        die("Invalid employee ID.");
    }

    // Lấy thông tin nhân viên từ database
    $employeeData = $app->get("employee", "*", ["id" => $id]);

    if (!$employeeData) {
        die("Employee not found.");
    }

    $vars['title'] = $jatbi->lang('Sửa thông tin nhân viên');
    $vars['data'] = $employeeData;
    echo $app->render('templates/camera/employee-post.html', $vars, 'global');
})->setPermissions(['employee']);



$app->router("/employee-edit", 'POST', function ($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $uploadFilePath = null;

    try {
        $id = $_POST['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            throw new Exception("ID nhân viên không hợp lệ.");
        }
        
        // --- [MỚI] LẤY SN MỚI TỪ FORM VÀ VALIDATE ---
        $new_sn = trim($_POST['sn'] ?? '');
        if (empty($new_sn)) {
            throw new Exception("Mã nhân viên (SN) không được để trống.");
        }


        $existingEmployee = $app->get("employee", ["id", "sn", "registration_photo"], ["id" => $id]);
        if (!$existingEmployee) {
            throw new Exception("Không tìm thấy nhân viên.");
        }


        if ($new_sn !== $existingEmployee['sn']) {
            if ($app->has("employee", ["sn" => $new_sn])) {
                throw new Exception("Mã nhân viên (SN) này đã tồn tại. Vui lòng chọn một mã khác.");
            }
        }
        
 
        $dbImagePath = $existingEmployee['registration_photo'];
        $newImageDataForMqtt = null;
        if (isset($_FILES['registration_photo']) && $_FILES['registration_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/photos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileExtension = pathinfo($_FILES['registration_photo']['name'], PATHINFO_EXTENSION);
            $newFileName = $new_sn . '_' . uniqid() . '.' . strtolower($fileExtension); // Dùng SN mới để đặt tên file
            $uploadFilePath = $uploadDir . $newFileName;
            $dbImagePath = 'uploads/photos/' . $newFileName;
            if (!move_uploaded_file($_FILES['registration_photo']['tmp_name'], $uploadFilePath)) throw new Exception('Lỗi khi tải ảnh mới lên.');
            $oldImagePath = __DIR__ . '/../../public/' . $existingEmployee['registration_photo'];
            if (file_exists($oldImagePath) && !is_dir($oldImagePath)) unlink($oldImagePath);
            $newImageDataForMqtt = file_get_contents($uploadFilePath);
        }

   
        $dataToUpdate = [
            "sn" => $new_sn, 
            "person_name" => $_POST['person_name'] ?? $existingEmployee['person_name'],
            "registration_photo" => $dbImagePath,
            "telephone" => $_POST['telephone'] ?? null,
            "gender" => $_POST['gender'] ?? null,
            "birthday" => empty($_POST['birthday']) ? null : $_POST['birthday'],
            "id_card" => $_POST['id_card'] ?? null,
            "address" => $_POST['address'] ?? null,
        ];
        $app->update("employee", $dataToUpdate, ["id" => $id]);

  
        $payload = [
            "messageId" => "ID:localhost-" . round(microtime(true) * 1000),
            "operator" => "EditPerson",
            "info" => [
                "personId" => (string) $id,
                "customId" => $new_sn, 
                "name" => $dataToUpdate['person_name'],
                "gender" => (int)($dataToUpdate['gender'] ?? 0),
                "birthday" => $dataToUpdate['birthday'] ?? "",
                "address" => $dataToUpdate['address'] ?? "",
                "idCard" => $dataToUpdate['id_card'] ?? "",
                "telnum1" => $dataToUpdate['telephone'] ?? "",
            ]
        ];
        if ($newImageDataForMqtt) {
            $payload['info']['pic'] = "data:image/jpeg;base64," . base64_encode($newImageDataForMqtt);
        }


        $apiUrl = 'https://mqtt.ellm.io/api/v5/publish';
        $apiKey    = 'your_new_api_key_here';
        $apiSecret = 'your_new_api_secret_here';
        $topic = 'mqtt/face/1018656';
        $dataToPost = ['topic' => $topic, 'payload' => json_encode($payload), 'qos' => 0];
        $ch = curl_init();
        curl_setopt_array($ch, [  ]);
 
        curl_close($ch);

        echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật nhân viên thành công"), 'load' => '/employee']);
    } catch (Throwable $e) {
        if ($uploadFilePath && file_exists($uploadFilePath)) unlink($uploadFilePath);
        http_response_code(500);
        echo json_encode(["status" => "error", "content" => $e->getMessage()]);
    }
})->setPermissions(['employee']);


$app->router("/employee-delete", 'GET', function ($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa dịch vụ");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['employee']);


$app->router("/employee-delete", 'POST', function ($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    

    $idList = $_POST['list'] ?? $_POST['ids'] ?? $_POST['id'] ?? $_GET['box'] ?? $_GET['id'] ?? '';
    
    $ids = !empty($idList) ? explode(',', $app->xss($idList)) : [];

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Chưa chọn nhân viên để xóa.')]);
        return;
    }

    try {

        $employeesToDelete = $app->select("employee", ["id", "sn", "registration_photo"], ["id" => $ids]);
        
        if (empty($employeesToDelete)) {
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Các nhân viên đã được xóa hoặc không tồn tại.')]);
            return;
        }

        $deleteResult = $app->delete("employee", ["id" => $ids]);
        if (!$deleteResult || $deleteResult->rowCount() === 0) {
            throw new Exception("Lỗi database khi xóa nhân viên.");
        }

        $deletedCount = $deleteResult->rowCount();

  
        foreach ($employeesToDelete as $emp) {
  
            if (!empty($emp['registration_photo'])) {
                $filePath = __DIR__ . '/../../public/' . $emp['registration_photo'];
                if (file_exists($filePath) && !is_dir($filePath)) {
                    unlink($filePath);
                }
            }

            // Gửi lệnh xóa đến MQTT qua HTTP API
            $mqttPayload = [
                "messageId" => "ID:localhost-" . round(microtime(true) * 1000) . ":" . mt_rand(),
                "operator" => "DelPerson",
                "info" => [
               
                    "customId" => (string) $emp['id'] 
                ]
            ];
            
            // --- Gửi qua cURL ---
            $apiUrl = 'https://mqtt.ellm.io/api/v5/publish';
            $apiKey    = 'b894dd847286b6b5';    
            $apiSecret = '9BkMrs1ttYhQ3b9CiXyFLD6GuQb8w9AUVl7jRtTb8SzbyF';  
            $topic = 'mqtt/face/1018656';
            
            $dataToPost = ['topic' => $topic, 'payload' => json_encode($mqttPayload), 'qos' => 0];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl, CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($dataToPost),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode("$apiKey:$apiSecret")],
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 5,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Đã xóa thành công') . " $deletedCount " . $jatbi->lang("nhân viên"), 'load' => 'this']);

    } catch (Throwable $e) {
        error_log("Error on employee delete: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Đã xảy ra lỗi trong quá trình xóa.')]);
    }
})->setPermissions(['employee']);
