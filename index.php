<?php
// ดักจับชื่อลิงก์ที่เข้าใช้งาน ถ้าในลิงก์มีคำว่า apache ให้โชว์ APACHE ถ้าไม่มีให้โชว์ NGINX
$host_url = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$server_name = (stripos($host_url, 'apache') !== false) ? 'APACHE' : 'NGINX';

// ... (ส่วนโค้ดต่อฐานข้อมูลด้านล่างปล่อยไว้เหมือนเดิม) ...

// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL (ดึงค่าจาก Environment Variables ของ Railway)
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: '3306';

// สร้างการเชื่อมต่อ
$conn = new mysqli($host, $user, $pass, $db, $port);

$db_status_message = "";
if ($conn->connect_error) {
    $db_status_message = "<div class='alert alert-danger'><strong>Database Status:</strong> เชื่อมต่อ MySQL ไม่สำเร็จ: " . $conn->connect_error . "</div>";
} else {
    $db_status_message = "<div class='alert alert-success'><strong>Database Status:</strong> เชื่อมต่อ MySQL สำเร็จ (Host: $host, Port: $port, DB: $db, Table: users)</div>";
    
    // สร้างตาราง users อัตโนมัติหากยังไม่มีตารางนี้
    $table_query = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($table_query);
}

// จัดการเมื่อมีการกดปุ่ม "บันทึกข้อมูล" (POST Request)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    if (!$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $phone);
        $stmt->execute();
        $stmt->close();
    }
    
    // รีเฟรชหน้าเพื่อป้องกันการส่งข้อมูลซ้ำ
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ดึงข้อมูลทั้งหมดมาแสดงในตาราง (เรียงจากล่าสุดไปเก่าสุด)
$total_records = 0;
$result = null;
if (!$conn->connect_error) {
    $result = $conn->query("SELECT * FROM users ORDER BY id DESC");
    if ($result) {
        $total_records = $result->num_rows;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form - <?php echo $server_name; ?></title>
    <!-- โหลด Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }
        /* ลายน้ำพื้นหลัง */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 12rem;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.03);
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }
        .content-wrapper {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 15px;
        }
        .card-header {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        .table th {
            font-weight: 600;
        }
    </style>
</head>
<body>

    <!-- แสดงลายน้ำ NGINX หรือ APACHE -->
    <div class="watermark"><?php echo $server_name; ?></div>

    <div class="content-wrapper">
        <h2 class="mb-4">Contact Form <span class="fs-5 text-secondary fw-normal">Server: <?php echo $server_name; ?></span></h2>
        
        <!-- แสดงสถานะ Database -->
        <?php echo $db_status_message; ?>

        <!-- ฟอร์มเพิ่มข้อมูล -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header">เพิ่มข้อมูลผู้ใช้</div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อ</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">เบอร์โทร</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </form>
            </div>
        </div>

        <!-- ตารางแสดงข้อมูล -->
        <div class="card shadow-sm">
            <div class="card-header">ข้อมูลผู้ใช้ (<?php echo $total_records; ?> รายการ)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>ชื่อ</th>
                                <th>Email</th>
                                <th>เบอร์โทร</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
