<?php
$con = mysqli_connect("localhost:3306", "root");
if (!mysqli_select_db($con, "student")) {
    die("数据库连接失败");
}
mysqli_query($con, "set names utf8");

$message = "";
$current_student = ""; // 记录当前登录/查询的学生学号

// ===== 处理各功能 =====
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST["action"];

    // ---------- 查询学生是否存在（选课第一步） ----------
    if ($action == "check_student") {
        $current_student = $_POST["学号"];
        $rs = mysqli_query($con, "SELECT * FROM 学生信息表 WHERE 学号 = '$current_student'");
        $student = mysqli_fetch_assoc($rs);
        if ($student) {
            $message = "👤 学生：{$student['姓名']}（{$student['院系']}）已确认";
        } else {
            $message = "❌ 学号 {$current_student} 不存在，请检查";
            $current_student = "";
        }
    }

    // ---------- 添加选课 ----------
    if ($action == "add_course") {
        $current_student = $_POST["学号"];
        $课程编号 = $_POST["课程编号"];

        // 检查学生是否存在
        $rs1 = mysqli_query($con, "SELECT * FROM 学生信息表 WHERE 学号 = '$current_student'");
        $student = mysqli_fetch_assoc($rs1);
        if (!$student) {
            $message = "❌ 学生不存在，请先确认学号";
        } else {
            // 检查课程是否存在
            $rs2 = mysqli_query($con, "SELECT * FROM 课程表 WHERE 课程编号 = '$课程编号'");
            $course = mysqli_fetch_assoc($rs2);
            if (!$course) {
                $message = "❌ 课程不存在，请选择正确的课程编号";
            } else {
                // 检查是否已选过这门课
                $rs3 = mysqli_query($con, "SELECT * FROM 选课记录表 WHERE 学号 = '$current_student' AND 课程编号 = '$课程编号'");
                if (mysqli_num_rows($rs3) > 0) {
                    $message = "⚠️ 您已选过该课程，请勿重复选课";
                } else {
                    // 添加选课记录
                    $q = "INSERT INTO 选课记录表 (学号, 课程编号) VALUES ('$current_student', '$课程编号')";
                    if (mysqli_query($con, $q)) {
                        $message = "✅ 选课成功！{$student['姓名']} 已选择 {$course['课程名称']}";
                    } else {
                        $message = "❌ 选课失败：" . mysqli_error($con);
                    }
                }
            }
        }
    }

    // ---------- 查询我的选课（只能查自己的） ----------
    if ($action == "my_courses") {
        $current_student = $_POST["学号"];
        $rs = mysqli_query($con, "SELECT * FROM 学生信息表 WHERE 学号 = '$current_student'");
        $student = mysqli_fetch_assoc($rs);
        if (!$student) {
            $message = "❌ 学生不存在";
        } else {
            // 查询该学生的选课记录（只查自己的）
            $q = "SELECT c.课程编号, c.课程名称 
                  FROM 选课记录表 sc 
                  JOIN 课程表 c ON sc.课程编号 = c.课程编号 
                  WHERE sc.学号 = '$current_student'";
            $rs2 = mysqli_query($con, $q);
            $courses = [];
            while ($row = mysqli_fetch_assoc($rs2)) {
                $courses[] = $row;
            }
            // 将结果存入 session 或消息中
            if (count($courses) > 0) {
                $msg = "📚 {$student['姓名']} 的选课列表：<br>";
                foreach ($courses as $c) {
                    $msg .= "▪ {$c['课程编号']} - {$c['课程名称']}<br>";
                }
                $message = $msg;
            } else {
                $message = "📭 {$student['姓名']} 暂未选课";
            }
        }
    }

    // ---------- 删除选课 ----------
    if ($action == "delete_course") {
        $current_student = $_POST["学号"];
        $课程编号 = $_POST["课程编号"];

        // 检查学生是否存在
        $rs1 = mysqli_query($con, "SELECT * FROM 学生信息表 WHERE 学号 = '$current_student'");
        $student = mysqli_fetch_assoc($rs1);
        if (!$student) {
            $message = "❌ 学生不存在";
        } else {
            // 检查是否有这门选课记录
            $rs2 = mysqli_query($con, "SELECT * FROM 选课记录表 WHERE 学号 = '$current_student' AND 课程编号 = '$课程编号'");
            if (mysqli_num_rows($rs2) == 0) {
                $message = "⚠️ 您未选过该课程，无需删除";
            } else {
                // 删除选课记录（只删除选课记录，不影响学生和课程）
                $q = "DELETE FROM 选课记录表 WHERE 学号 = '$current_student' AND 课程编号 = '$课程编号'";
                if (mysqli_query($con, $q)) {
                    $rs3 = mysqli_query($con, "SELECT 课程名称 FROM 课程表 WHERE 课程编号 = '$课程编号'");
                    $course = mysqli_fetch_assoc($rs3);
                    $message = "✅ 退课成功！{$student['姓名']} 已取消 {$course['课程名称']}";
                } else {
                    $message = "❌ 退课失败：" . mysqli_error($con);
                }
            }
        }
    }
}

// 获取当前选中的功能标签
$tab = isset($_GET["tab"]) ? $_GET["tab"] : "add";
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>学生选课系统</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Microsoft YaHei",sans-serif; background:#f0f2f5; }
        .header { background:#1a1a2e; color:white; padding:25px; text-align:center; }
        .header h1 { font-size:26px; }
        .nav { display:flex; justify-content:center; background:#16213e; }
        .nav a { color:white; padding:14px 28px; text-decoration:none; transition:0.3s; border-bottom:3px solid transparent; }
        .nav a:hover { background:#0f3460; }
        .nav a.active { background:#0f3460; border-bottom-color:#e94560; font-weight:bold; }
        .container { max-width:800px; margin:30px auto; padding:0 20px; }
        .message { background:white; border-radius:8px; padding:15px 20px; margin-bottom:20px; border-left:4px solid #e94560; box-shadow:0 2px 8px rgba(0,0,0,0.1); line-height:1.8; }
        .card { background:white; border-radius:8px; padding:25px; box-shadow:0 2px 8px rgba(0,0,0,0.1); margin-bottom:20px; }
        .card h3 { color:#1a1a2e; margin-bottom:15px; border-bottom:2px solid #eee; padding-bottom:10px; }
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-weight:bold; color:#555; margin-bottom:5px; }
        .form-group input, .form-group select { width:100%; padding:10px; border:1px solid #ddd; border-radius:5px; font-size:15px; }
        .form-group input:focus { border-color:#e94560; outline:none; }
        .btn { padding:10px 25px; border:none; border-radius:5px; cursor:pointer; font-size:15px; color:white; transition:0.3s; }
        .btn-primary { background:#e94560; }
        .btn-primary:hover { background:#c73652; }
        .btn-success { background:#27ae60; }
        .btn-success:hover { background:#219a52; }
        .btn-danger { background:#e74c3c; }
        .btn-danger:hover { background:#c0392b; }
        .btn-info { background:#3498db; }
        .btn-info:hover { background:#2980b9; }
        .course-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:10px; margin:15px 0; }
        .course-item { background:#f8f9fa; border:1px solid #dee2e6; border-radius:6px; padding:12px; cursor:pointer; transition:0.3s; text-align:center; }
        .course-item:hover { background:#e94560; color:white; border-color:#e94560; }
        .course-item.selected { background:#e94560; color:white; }
        .info-box { background:#e8f4fd; border:1px solid #b6d4fe; border-radius:6px; padding:12px; margin-bottom:15px; color:#0c5460; }
        .footer { text-align:center; padding:20px; color:#999; font-size:13px; }
    </style>
</head>
<body>

<div class="header">
    <h1>🎓 学生选课系统</h1>
</div>

<div class="nav">
    <a href="?tab=add" class="<?php echo $tab=='add'?'active':''; ?>">➕ 选课</a>
    <a href="?tab=my" class="<?php echo $tab=='my'?'active':''; ?>">📖 我的选课</a>
    <a href="?tab=delete" class="<?php echo $tab=='delete'?'active':''; ?>">🗑️ 退课</a>
</div>

<div class="container">

    <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- ==================== 选课功能 ==================== -->
    <?php if ($tab == "add"): ?>
    <div class="card">
        <h3>➕ 第一步：确认学生身份</h3>
        <form method="post">
            <input type="hidden" name="action" value="check_student">
            <div class="form-group">
                <label>请输入学号：</label>
                <input type="text" name="学号" value="<?php echo $current_student; ?>" required>
            </div>
            <button type="submit" class="btn btn-info">🔍 确认学生</button>
        </form>
    </div>

    <?php if (!empty($current_student)): ?>
    <div class="card">
        <h3>➕ 第二步：选择课程（点击课程即可选课）</h3>
        <div class="info-box">
            当前学生学号：<strong><?php echo $current_student; ?></strong>
        </div>
        <div class="course-list">
            <?php
            // 查询所有课程
            $rs_courses = mysqli_query($con, "SELECT * FROM 课程表");
            while ($course = mysqli_fetch_assoc($rs_courses)) {
                // 检查是否已选
                $rs_check = mysqli_query($con, "SELECT * FROM 选课记录表 WHERE 学号 = '$current_student' AND 课程编号 = '{$course['课程编号']}'");
                $already_selected = mysqli_num_rows($rs_check) > 0;
                ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="add_course">
                    <input type="hidden" name="学号" value="<?php echo $current_student; ?>">
                    <input type="hidden" name="课程编号" value="<?php echo $course['课程编号']; ?>">
                    <button type="submit" class="course-item" <?php echo $already_selected ? 'style="background:#28a745;color:white;cursor:default;" disabled' : ''; ?>>
                        <strong><?php echo $course['课程编号']; ?></strong><br>
                        <?php echo $course['课程名称']; ?>
                        <?php echo $already_selected ? '<br>✅ 已选' : ''; ?>
                    </button>
                </form>
                <?php
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== 我的选课（只能查自己的） ==================== -->
    <?php elseif ($tab == "my"): ?>
    <div class="card">
        <h3>📖 查询我的选课</h3>
        <form method="post">
            <input type="hidden" name="action" value="my_courses">
            <div class="form-group">
                <label>请输入您的学号：</label>
                <input type="text" name="学号" required>
            </div>
            <button type="submit" class="btn btn-primary">🔍 查询我的选课</button>
        </form>
    </div>

    <!-- ==================== 退课（只删除选课记录） ==================== -->
    <?php elseif ($tab == "delete"): ?>
    <div class="card">
        <h3>🗑️ 退课</h3>
        <form method="post">
            <input type="hidden" name="action" value="delete_course">
            <div class="form-group">
                <label>学号：</label>
                <input type="text" name="学号" required>
            </div>
            <div class="form-group">
                <label>课程编号：</label>
                <select name="课程编号" required>
                    <option value="">-- 请选择课程 --</option>
                    <?php
                    $rs = mysqli_query($con, "SELECT * FROM 课程表");
                    while ($c = mysqli_fetch_assoc($rs)) {
                        echo "<option value='{$c['课程编号']}'>{$c['课程编号']} - {$c['课程名称']}</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="btn btn-danger">🗑️ 确认退课</button>
        </form>
    </div>
    <?php endif; ?>

</div>

<div class="footer">学生选课系统 &copy; 2026</div>

</body>
</html>
<?php mysqli_close($con); ?>
